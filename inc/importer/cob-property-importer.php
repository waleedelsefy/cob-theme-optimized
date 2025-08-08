<?php
/**
 * AJAX WordPress Importer for 'properties' posts from a CSV file.
 *
 * Version 13.1: Patched for WordPress 6.7+ JIT translation notice.
 * Moved asset enqueuing from 'load-{$hook}' to 'admin_enqueue_scripts'
 * to ensure textdomain is loaded before localization functions are called.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Get the importer configuration array.
 * This function prevents translation functions from running too early.
 *
 * @return array The configuration array.
 */
function cob_get_property_importer_config() {
    return [
        'post_type' => 'properties',
        'source_id_meta_key' => '_property_source_id',
        'csv_delimiter' => ',',
        'batch_size' => 1, // Keep this low (1-5) for imports with images. Increase (e.g., 30+) when skipping images.
        'ajax_timeout_seconds' => 300,
        'status_option_name' => 'cob_property_importer_status',
        'taxonomies_map' => [
            'compound_name'           => 'compound',
            'compound_developer_name' => 'developer',
            'compound_area_name'      => 'city',
            'property_type_name'      => 'type',
            'finishing'               => 'finishing',
        ],
        'csv_column_map_en' => [
            'id' => 'id', 'name' => 'meta_title_en', 'slug' => 'all_slugs_en', 'description' => 'meta_description_en',
            'gallery_img_base' => 'Property_img', 'gallery_img_count' => 8,
            'source_url_col' => 'source_url', // Added for logging
            'images_col' => 'images', // Added for JSON images
        ],
        'csv_column_map_ar' => [
            'id' => 'id', 'name' => 'name', 'slug' => 'all_slugs_ar', 'description' => 'description',
            'gallery_img_base' => 'Property_img', 'gallery_img_count' => 8,
            'source_url_col' => 'source_url', // Added for logging
            'images_col' => 'images', // Added for JSON images
        ],
        'meta_fields_map' => [
            'number_of_bathrooms'     => 'bathrooms',
            'number_of_bedrooms'      => 'bedrooms',
            'min_unit_area'           => 'min_unit_area',
            'min_price'               => 'min_price',
            'max_price'               => 'max_price',
            'resale'                  => 'resale',
            'ready_by'                => 'ready_by',
            'source_url'              => 'source_url',
            'max_garden_area'         => 'max_garden_area',
            'compound_map_path'       => 'compound_map_path',
            'compound_polygon_points' => 'compound_polygon_points',
            'properties_views'        => 'properties_views',
        ],
        'json_fields_to_array' => [
            'payment_plans'  => [
                'meta_key'  => 'down_payment_details',
                'json_path' => 'down_payment'
            ]
        ]
    ];
}

/**
 * Custom table for import logging.
 */
function cob_property_importer_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $log_table_name = $wpdb->prefix . 'cob_property_import_log'; // Changed table name for properties

    $sql_log = "CREATE TABLE {$log_table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        source_id VARCHAR(255) NOT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        post_type VARCHAR(50) NOT NULL,
        lang VARCHAR(10) NOT NULL,
        source_url TEXT NULL,
        images_downloaded TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        last_checked DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_post_type_lang (source_id, post_type, lang),
        KEY post_id (post_id)
    ) {$charset_collate};";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_log );
}
register_activation_hook(__FILE__, 'cob_property_importer_activate'); // Register activation hook for the table

// 1. Register Admin Page & Enqueue Assets
add_action('admin_menu', 'cob_prop_importer_register_page');
function cob_prop_importer_register_page() {
    $hook_suffix = add_submenu_page(
        'tools.php',
        __('Property Importer (AJAX)', 'cob_theme'),
        __('Import Properties', 'cob_theme'),
        'manage_options',
        'cob-property-importer',
        'cob_prop_importer_render_page'
    );

    add_action('admin_enqueue_scripts', function($hook) use ($hook_suffix) {
        if ($hook === $hook_suffix) {
            cob_prop_importer_enqueue_assets();
        }
    });
}

function cob_prop_importer_enqueue_assets() {
    $config = cob_get_property_importer_config();
    $js_path = get_stylesheet_directory_uri() . '/inc/importer/cob-property-importer.js';

    wp_enqueue_script('cob-prop-importer-js', $js_path, ['jquery'], '3.3.0', true);

    wp_localize_script('cob-prop-importer-js', 'cobPropImporter', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('cob_prop_importer_nonce'),
        'i18n' => [
            'confirm_new_import' => __('Are you sure you want to start a new import? This will clear any previously unfinished import.', 'cob_theme'),
            'confirm_resume' => __('Do you want to resume the previous import?', 'cob_theme'),
            'confirm_cancel' => __('Are you sure you want to cancel the process?', 'cob_theme'),
            'error_selecting_file' => __('Please select a CSV file to import.', 'cob_theme'),
            'preparing_import' => __('Preparing import...', 'cob_theme'),
            'import_complete' => __('🎉 Import completed successfully! 🎉', 'cob_theme'),
            'connection_error' => __('❌ Server connection error', 'cob_theme'),
            'processed' => __('Processed', 'cob_theme'),
            'of' => __('of', 'cob_theme'),
            'imported' => __('Imported', 'cob_theme'),
            'updated' => __('Updated', 'cob_theme'),
            'failed' => __('Failed', 'cob_theme'),
            'retrying' => __('Connection failed. Retrying in 5 seconds...', 'cob_theme'),
            'max_retries_reached' => __('Maximum retries reached. The import has been stopped. Please check your server logs or network connection.', 'cob_theme'),
        ]
    ]);

    wp_add_inline_style('wp-admin', "
        .cob-progress-bar-container { border: 1px solid #ccc; padding: 2px; width: 100%; max-width: 600px; border-radius: 5px; background: #f1f1f1; margin-bottom:10px; }
        .cob-progress-bar { background-color: #0073aa; height: 24px; width: 0%; text-align: center; line-height: 24px; color: white; border-radius: 3px; transition: width 0.3s ease-in-out; }
        #importer-log { background: #1e1e1e; color: #f1f1f1; border: 1px solid #e5e5e5; padding: 10px; margin-top: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; border-radius: 4px; }
        .importer-source-choice, .importer-language-choice, .importer-options { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; background: #fff; border-radius: 4px; }
        #source-server-container, #source-upload-container { padding-left: 20px; }
        #importer-notice { display: none; margin: 10px 0; padding: 10px; border-left-width: 4px; border-left-style: solid; }
        #importer-notice.notice-error { border-color: #d63638; background-color: #f8d7da; }
        #importer-notice.notice-warning { border-color: #dba617; background-color: #fff3cd; }
    ");
}

// 2. Render Importer Page HTML
function cob_prop_importer_render_page() {
    $config = cob_get_property_importer_config();
    $import_status = get_option($config['status_option_name'], false);

    $imports_dir = WP_CONTENT_DIR . '/csv-imports/';
    $server_files = [];
    if (is_dir($imports_dir)) {
        $files = array_diff(scandir($imports_dir), ['..', '.']);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
                $server_files[] = $file;
            }
        }
    } else {
        wp_mkdir_p($imports_dir);
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Property Importer (AJAX)', 'cob_theme'); ?></h1>
        <p><?php _e('This tool imports and updates properties from a CSV file, with support for translation linking.', 'cob_theme'); ?></p>

        <?php if ($import_status && isset($import_status['progress']) && $import_status['progress'] < 100) : ?>
            <div id="resume-notice" class="notice notice-warning is-dismissible"><p><?php printf(__('An unfinished import of %s was found. You can resume or cancel it.', 'cob_theme'), '<code>' . esc_html($import_status['original_filename']) . '</code>'); ?></p></div>
        <?php endif; ?>

        <form id="cob-importer-form" method="post" enctype="multipart/form-data">
            <div id="importer-notice" class="notice notice-error"></div>
            <h2><?php _e('Step 1: Choose File Source', 'cob_theme'); ?></h2>
            <div class="importer-source-choice">
                <p><label><input type="radio" name="import_source" value="upload" checked> <?php _e('Upload file from your computer', 'cob_theme'); ?></label></p>
                <div id="source-upload-container">
                    <input type="file" id="property_csv" name="property_csv" accept=".csv,text/csv">
                </div>
                <hr>
                <p><label><input type="radio" name="import_source" value="server"> <?php _e('Select a file from the server', 'cob_theme'); ?></label></p>
                <div id="source-server-container" style="display:none;">
                    <?php if (!empty($server_files)) : ?>
                        <select id="server_csv_file" name="server_csv_file" style="min-width: 300px;">
                            <option value=""><?php _e('-- Select a file --', 'cob_theme'); ?></option>
                            <?php foreach ($server_files as $file) : ?><option value="<?php echo esc_attr($file); ?>"><?php echo esc_html($file); ?></option><?php endforeach; ?>
                        </select>
                        <p class="description"><?php printf(__('Path: %s', 'cob_theme'), '<code>' . esc_html(trailingslashit($imports_dir)) . '</code>'); ?></p>
                    <?php else : ?><p><?php printf(__('No CSV files found. Please upload files to %s', 'cob_theme'), '<code>' . esc_html(trailingslashit($imports_dir)) . '</code>'); ?></p><?php endif; ?>
                </div>
            </div>

            <h2><?php _e('Step 2: Import Options', 'cob_theme'); ?></h2>
            <div class="importer-options">
                <p>
                    <label for="import_language"><?php _e('Import Language:', 'cob_theme'); ?></label><br>
                    <select id="import_language" name="import_language" style="min-width: 300px;">
                        <option value="en"><?php _e('English', 'cob_theme'); ?></option>
                        <option value="ar" selected><?php _e('Arabic', 'cob_theme'); ?></option>
                    </select>
                </p>
                <p>
                    <label for="skip_images">
                        <input type="checkbox" id="skip_images" name="skip_images" value="1">
                        <strong><?php _e('Skip image import', 'cob_theme'); ?></strong>
                    </label>
                    <br>
                    <span class="description"><?php _e('Check this for a much faster import of text and meta data. You can run the import again later with this box unchecked to import images for existing properties.', 'cob_theme'); ?></span>
                </p>
            </div>

            <button type="submit" class="button button-primary"><?php _e('Start New Import', 'cob_theme'); ?></button>
            <button type="button" id="resume-import" class="button" style="<?php echo ($import_status && $import_status['progress'] < 100) ? '' : 'display:none;'; ?>"><?php _e('Resume Import', 'cob_theme'); ?></button>
            <button type="button" id="cancel-import" class="button button-secondary" style="<?php echo $import_status ? '' : 'display:none;'; ?>"><?php _e('Cancel & Reset', 'cob_theme'); ?></button>
        </form>

        <div id="importer-progress-container" style="display:none; margin-top: 20px;">
            <h3><?php _e('Import Progress', 'cob_theme'); ?></h3>
            <div class="cob-progress-bar-container"><div id="importer-progress-bar" class="cob-progress-bar">0%</div></div>
            <p id="importer-stats"></p>
            <h4><?php _e('Log:', 'cob_theme'); ?></h4><div id="importer-log"></div>
        </div>
    </div>
    <?php
}

/**
 * Inserts or updates an entry in the cob_property_import_log table.
 *
 * @param string $source_id The unique ID from the CSV.
 * @param int    $post_id   The WordPress post ID.
 * @param string $post_type The post type.
 * @param string $lang      The language code (e.g., 'en', 'ar').
 * @param string $source_url The URL of the original source.
 * @param bool   $images_downloaded Whether images were downloaded for this entry.
 * @param string $status    'active', 'completed', 'failed'.
 */
function cob_log_property_import_status($source_id, $post_id, $post_type, $lang, $source_url, $images_downloaded, $status = 'active') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cob_property_import_log';

    $data = [
        'source_id'         => $source_id,
        'post_id'           => $post_id,
        'post_type'         => $post_type,
        'lang'              => $lang,
        'source_url'        => $source_url,
        'images_downloaded' => (int) $images_downloaded,
        'status'            => $status,
        'last_checked'      => current_time('mysql', 1)
    ];

    $format = ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s'];

    // Try to find an existing entry based on source_id, post_type, and lang
    $existing_entry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE source_id = %s AND post_type = %s AND lang = %s",
            $source_id,
            $post_type,
            $lang
        )
    );

    if ($existing_entry) {
        // Update existing entry
        $wpdb->update(
            $table_name,
            $data,
            ['id' => $existing_entry->id],
            $format,
            ['%d']
        );
    } else {
        // Insert new entry
        $wpdb->insert(
            $table_name,
            $data,
            $format
        );
    }
}


// 3. AJAX Handler
add_action('wp_ajax_cob_run_property_importer', 'cob_ajax_run_property_importer_callback');
function cob_ajax_run_property_importer_callback() {
    $config = cob_get_property_importer_config();
    check_ajax_referer('cob_prop_importer_nonce', 'nonce');

    if (!current_user_can('manage_options')) { wp_send_json_error(['message' => __('Insufficient permissions.', 'cob_theme')]); }

    set_time_limit($config['ajax_timeout_seconds']);
    ini_set('memory_limit', '512M');
    wp_raise_memory_limit('admin');

    if (!function_exists('media_sideload_image')) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }

    $action = isset($_POST['importer_action']) ? sanitize_text_field($_POST['importer_action']) : '';
    $log_messages = [];

    switch ($action) {
        case 'prepare':
            $old_status = get_option($config['status_option_name']);
            if ($old_status && !empty($old_status['file_path']) && file_exists($old_status['file_path'])) {
                wp_delete_file($old_status['file_path']);
            }
            delete_option($config['status_option_name']);

            $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'upload';
            $file_path = '';
            $original_filename = '';

            if ($source_type === 'upload') {
                if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    wp_send_json_error(['message' => __('No file uploaded or an upload error occurred.', 'cob_theme')]);
                }
                $move_file = wp_handle_upload($_FILES['csv_file'], ['test_form' => false, 'mimes' => ['csv' => 'text/csv']]);
                if (!$move_file || isset($move_file['error'])) {
                    wp_send_json_error(['message' => __('Error handling uploaded file:', 'cob_theme') . ' ' . ($move_file['error'] ?? __('Unknown error', 'cob_theme'))]);
                }
                $file_path = $move_file['file'];
                $original_filename = sanitize_file_name($_FILES['csv_file']['name']);
            } elseif ($source_type === 'server') {
                $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
                $server_file_path = WP_CONTENT_DIR . '/csv-imports/' . $file_name;

                if (empty($file_name) || !file_exists($server_file_path) || !is_readable($server_file_path)) {
                    wp_send_json_error(['message' => __('File not found on server or is not readable.', 'cob_theme')]);
                }
                $upload_dir = wp_upload_dir();
                $temp_file_path = wp_unique_filename($upload_dir['path'], basename($server_file_path));
                $temp_file_full_path = $upload_dir['path'] . '/' . $temp_file_path;
                if (!copy($server_file_path, $temp_file_full_path)) {
                    wp_send_json_error(['message' => __('Failed to copy file from server to temporary directory.', 'cob_theme')]);
                }
                $file_path = $temp_file_full_path;
                $original_filename = $file_name;
            }

            $total_rows = 0;
            $headers = [];
            $handle = fopen($file_path, "r");
            if ($handle !== FALSE) {
                $headers = array_map('trim', fgetcsv($handle, 0, $config['csv_delimiter']));
                while (fgetcsv($handle, 0, $config['csv_delimiter']) !== FALSE) $total_rows++;
                fclose($handle);
            } else {
                if (file_exists($file_path)) wp_delete_file($file_path);
                wp_send_json_error(['message' => __('Failed to open the CSV file for reading.', 'cob_theme')]);
            }

            $status = [
                'file_path' => $file_path, 'original_filename' => $original_filename, 'total_rows' => $total_rows,
                'processed' => 0, 'imported_count' => 0, 'updated_count' => 0, 'failed_count' => 0,
                'progress' => 0, 'language' => isset($_POST['import_language']) ? sanitize_text_field($_POST['import_language']) : 'en',
                'headers' => $headers,
                'skip_images' => isset($_POST['skip_images']) && $_POST['skip_images'] === 'true', // Store the state of skip_images
            ];
            update_option($config['status_option_name'], $status, 'no');
            wp_send_json_success(['status' => $status, 'log' => [sprintf(__('Preparation successful. Found %d data rows.', 'cob_theme'), $total_rows)]]);
            break;

        case 'run':
            $status = get_option($config['status_option_name']);
            if (!$status || empty($status['file_path']) || !file_exists($status['file_path'])) {
                wp_send_json_error(['message' => __('Could not find a valid import process to run.', 'cob_theme')]);
            }

            if ($status['processed'] >= $status['total_rows']) {
                wp_send_json_success(['status' => $status, 'log' => [__('Import is already complete.', 'cob_theme')], 'done' => true]);
            }

            $config['target_language'] = $status['language'];
            $config['skip_images'] = $status['skip_images'] ?? false; // Pass skip_images state to the import function

            $handle = fopen($status['file_path'], "r");
            if ($handle !== FALSE) {
                fgetcsv($handle); // Skip header row
                for ($i = 0; $i < $status['processed']; $i++) { if(fgetcsv($handle) === FALSE) break; }

                $raw_row_data = fgetcsv($handle, 0, $config['csv_delimiter']);
                if($raw_row_data !== FALSE) {
                    $status['processed']++;
                    if (count($status['headers']) !== count($raw_row_data)) {
                        $log_messages[] = sprintf('(%d) <span style="color:red;">%s</span>', $status['processed'], sprintf(__('Fatal Error: Column count does not match header count (Found: %d, Expected: %d).', 'cob_theme'), count($raw_row_data), count($status['headers'])));
                        $status['failed_count']++;
                    } else {
                        $row_data = array_combine($status['headers'], $raw_row_data);
                        if ($row_data === false) {
                            $log_messages[] = sprintf('(%d) <span style="color:red;">%s</span>', $status['processed'], __('Fatal Error: Failed to combine headers with row data.', 'cob_theme'));
                            $status['failed_count']++;
                        } else {
                            $import_result = cob_import_single_property($row_data, $config, $status['processed']);
                            if (isset($import_result['log'])) $log_messages = array_merge($log_messages, $import_result['log']);
                            if ($import_result['status'] === 'imported') $status['imported_count']++;
                            elseif ($import_result['status'] === 'updated') $status['updated_count']++;
                            else $status['failed_count']++;
                        }
                    }
                } else {
                    $status['processed'] = $status['total_rows'];
                }
                fclose($handle);
            }

            $status['progress'] = ($status['total_rows'] > 0) ? round(($status['processed'] / $status['total_rows']) * 100) : 100;
            $done = ($status['processed'] >= $status['total_rows']);
            if ($done) {
                if (file_exists($status['file_path'])) {
                    wp_delete_file($status['file_path']);
                }
                $status['file_path'] = null;
                $log_messages[] = __('Import finished. Temporary file has been deleted.', 'cob_theme');
            }

            update_option($config['status_option_name'], $status, 'no');
            wp_send_json_success(['status' => $status, 'log' => $log_messages, 'done' => $done]);
            break;

        case 'cancel':
            $status = get_option($config['status_option_name']);
            if ($status && !empty($status['file_path']) && file_exists($status['file_path'])) {
                wp_delete_file($status['file_path']);
            }
            delete_option($config['status_option_name']);
            wp_send_json_success(['message' => __('Process cancelled and status cleared successfully.', 'cob_theme')]);
            break;
        case 'get_status':
            $status = get_option($config['status_option_name']);
            if ($status && isset($status['progress']) && $status['progress'] < 100 && !empty($status['original_filename'])) {
                wp_send_json_success(['status' => $status]);
            } else {
                delete_option($config['status_option_name']);
                wp_send_json_error(['message' => __('No resumable import process was found.', 'cob_theme')]);
            }
            break;

        default:
            wp_send_json_error(['message' => __('Unknown action requested.', 'cob_theme')]);
    }
}


// 4. Import Single Property Post
function cob_import_single_property($csv_row, &$config, $row_num) {
    $log = [];
    $result_status = 'failed';

    $lang = $config['target_language'];
    $map = $config['csv_column_map_' . $lang];
    $post_type = $config['post_type'];
    $source_id_meta_key = $config['source_id_meta_key'];
    $skip_images = $config['skip_images'] ?? false;

    $source_id = trim($csv_row['id'] ?? '');
    if (empty($source_id)) {
        $log[] = sprintf('(%d) <span style="color:red;">%s</span>', $row_num, __('Error: Source `id` column is empty.', 'cob_theme'));
        return ['status' => 'failed', 'log' => $log];
    }

    $post_title = sanitize_text_field(trim($csv_row[$map['name']] ?? ''));
    if (empty($post_title)) {
        $log[] = sprintf('(%d) <span style="color:red;">%s</span>', $row_num, sprintf(__('Error: Property name (`%s`) is empty.', 'cob_theme'), $map['name']));
        return ['status' => 'failed', 'log' => $log];
    }

    $post_slug = sanitize_title(trim($csv_row[$map['slug']] ?? ''));
    if(empty($post_slug)) $post_slug = sanitize_title($post_title);

    $post_content = wp_kses_post($csv_row[$map['description']] ?? '');
    $source_url_for_log = sanitize_url(trim($csv_row[$map['source_url_col']] ?? ''));

    $post_id = null;
    $post_in_lang_exists = false;

    // Check if a post with this source_id and language already exists
    if (function_exists('pll_get_post_language')) {
        $existing_posts_query = new WP_Query([
            'post_type' => $post_type, 'meta_key' => $source_id_meta_key, 'meta_value' => $source_id,
            'post_status' => 'any', 'posts_per_page' => 1, 'lang' => $lang, 'fields' => 'ids'
        ]);
        if ($existing_posts_query->have_posts()) {
            $post_id = $existing_posts_query->posts[0];
            $post_in_lang_exists = true;
        }
    } else {
        // Fallback for non-Polylang environments, relies only on source_id
        $existing_posts_query = new WP_Query([
            'post_type' => $post_type, 'meta_key' => $source_id_meta_key, 'meta_value' => $source_id,
            'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids'
        ]);
        if ($existing_posts_query->have_posts()) {
            $post_id = $existing_posts_query->posts[0];
            $post_in_lang_exists = true;
        }
    }


    $post_data = ['post_title' => $post_title, 'post_name' => $post_slug, 'post_content' => $post_content, 'post_type' => $post_type, 'post_status' => 'publish'];

    if ($post_in_lang_exists) {
        $post_data['ID'] = $post_id;
        wp_update_post($post_data);
        $log[] = sprintf('(%d) <span style="color:#00A86B;">%s</span>', $row_num, sprintf(__('Updated "%s" (ID: %d).', 'cob_theme'), $post_title, $post_id));
        $result_status = 'updated';
    } else {
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            $log[] = sprintf('(%d) <span style="color:red;">%s</span>', $row_num, sprintf(__('Failed to create "%s": %s', 'cob_theme'), $post_title, $post_id->get_error_message()));
            // Log failed creation
            cob_log_property_import_status($source_id, 0, $post_type, $lang, $source_url_for_log, false, 'failed');
            return ['status' => 'failed', 'log' => $log];
        }
        $log[] = sprintf('(%d) <span style="color:lightgreen;">%s</span>', $row_num, sprintf(__('Created "%s" as a new post (ID: %d).', 'cob_theme'), $post_title, $post_id));
        $result_status = 'imported';
    }

    $images_downloaded_for_log = false; // Default to false, updated if images are processed

    if ($post_id) {
        update_post_meta($post_id, $source_id_meta_key, $source_id);
        if (function_exists('pll_set_post_language')) { pll_set_post_language($post_id, $lang); }

        if (function_exists('pll_save_post_translations')) {
            $translations = [];
            // Get all posts linked by source_id, regardless of language
            $all_posts_with_id = get_posts([
                'post_type' => $post_type,
                'meta_key' => $source_id_meta_key,
                'meta_value' => $source_id,
                'numberposts' => -1,
                'fields' => 'ids',
                'lang' => '' // Important: query all languages
            ]);

            if (count($all_posts_with_id) > 0) { // Should be > 0 because the current post_id is included
                foreach ($all_posts_with_id as $p_id) {
                    $p_lang = function_exists('pll_get_post_language') ? pll_get_post_language($p_id) : null;
                    if($p_lang) $translations[$p_lang->slug] = $p_id; // Use slug for language
                }
                if(count($translations) > 1) {
                    pll_save_post_translations($translations);
                    $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:cyan;">%s</span>', $row_num, __('Linked translations.', 'cob_theme'));
                }
            }
        }

        // Handle taxonomies
        foreach($config['taxonomies_map'] as $csv_col => $tax_slug) {
            if (!empty($csv_row[$csv_col])) {
                $term_name = trim($csv_row[$csv_col]);
                $term_id = cob_get_or_create_term_for_linking($term_name, $tax_slug, $lang);
                if($term_id) {
                    wp_set_object_terms($post_id, (int)$term_id, $tax_slug, true);
                }
            }
        }

        // Handle additional meta fields
        if (isset($config['meta_fields_map'])) {
            $updated_meta_count = 0;
            foreach($config['meta_fields_map'] as $csv_col => $meta_key) {
                if (isset($csv_row[$csv_col]) && $csv_row[$csv_col] !== '') {
                    $meta_value = trim($csv_row[$csv_col]);
                    update_post_meta($post_id, $meta_key, $meta_value);
                    $updated_meta_count++;

                    if ($csv_col === 'min_unit_area') {
                        update_post_meta($post_id, 'area', $meta_value);
                    }
                }
            }
            if ($updated_meta_count > 0) {
                $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:#A8A8A8;">%s</span>', $row_num, sprintf(_n('Saved %d additional data field.', 'Saved %d additional data fields.', $updated_meta_count, 'cob_theme'), $updated_meta_count));
            }
        }

        // Handle special JSON-encoded fields
        if (isset($config['json_fields_to_array'])) {
            $updated_json_meta_count = 0;
            foreach($config['json_fields_to_array'] as $csv_col => $json_config) {
                if (isset($csv_row[$csv_col]) && !empty(trim($csv_row[$csv_col]))) {
                    $json_data = json_decode(trim($csv_row[$csv_col]), true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $path = $json_config['json_path'];
                        if (isset($json_data[$path])) {
                            $value_to_save = $json_data[$path];
                            update_post_meta($post_id, $json_config['meta_key'], $value_to_save);
                            $updated_json_meta_count++;
                        }
                    } else {
                        $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:orange;">%s</span>', $row_num, sprintf(__('Warning: Could not decode JSON from column `%s`.', 'cob_theme'), $csv_col));
                    }
                }
            }
            if ($updated_json_meta_count > 0) {
                $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:#A8A8A8;">%s</span>', $row_num, sprintf(_n('Saved %d JSON data field.', 'Saved %d JSON data fields.', $updated_json_meta_count, 'cob_theme'), $updated_json_meta_count));
            }
        }

        // Intelligent gallery image import - wrapped in a condition to skip if requested.
        // Also checks if images were already downloaded for this source_id and language
        $log_entry = cob_get_property_import_log_entry($source_id, $post_type, $lang);

        if (!$skip_images && (!$log_entry || !$log_entry->images_downloaded)) {
            $gallery_ids = [];
            $image_urls = [];

            // Prioritize 'images' column for JSON array of image paths
            if (!empty($csv_row[$map['images_col']])) {
                $image_json_strings = explode('|', $csv_row[$map['images_col']]);
                foreach ($image_json_strings as $json_string) {
                    $image_data = json_decode(trim($json_string), true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($image_data['image_path'])) {
                        $image_urls[] = $image_data['image_path'];
                    }
                }
            }

            // Fallback to gallery_img_base[i] columns if 'images' is empty
            if (empty($image_urls)) {
                for ($i = 0; $i < $map['gallery_img_count']; $i++) {
                    $img_url_key = $map['gallery_img_base'] . '[' . $i . ']';
                    $img_url = trim($csv_row[$img_url_key] ?? '');
                    if (!empty($img_url)) {
                        $image_urls[] = $img_url;
                    }
                }
            }

            foreach ($image_urls as $img_url) {
                if ($img_url && filter_var($img_url, FILTER_VALIDATE_URL)) {
                    $att_id = media_sideload_image($img_url, $post_id, $post_title, 'id');
                    if (!is_wp_error($att_id)) {
                        $gallery_ids[] = $att_id;
                        if (function_exists('pll_set_post_language')) {
                            pll_set_post_language($att_id, $lang);
                        }
                    } else {
                        $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:orange;">%s "%s": %s</span>', $row_num, __('Warning: Failed to import image from URL', 'cob_theme'), esc_html($img_url), $att_id->get_error_message());
                    }
                }
            }

            if (!empty($gallery_ids)) {
                if (!has_post_thumbnail($post_id)) {
                    set_post_thumbnail($post_id, $gallery_ids[0]);
                    $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:lightgreen;">%s</span>', $row_num, __('Set first image as featured image.', 'cob_theme'));
                }
                update_post_meta($post_id, '_cob_gallery_images', $gallery_ids);
                $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:lightgreen;">%s</span>', $row_num, sprintf(_n('Saved %d gallery image.', 'Saved %d gallery images.', count($gallery_ids), 'cob_theme'), count($gallery_ids)));
                $images_downloaded_for_log = true;
            }
        } elseif ($skip_images) {
            $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:#A8A8A8;">%s</span>', $row_num, __('Image import skipped as requested.', 'cob_theme'));
            // If skipping, we check the existing log entry for image status
            $images_downloaded_for_log = $log_entry ? (bool) $log_entry->images_downloaded : false;
        } elseif ($log_entry && $log_entry->images_downloaded) {
            $log[] = sprintf('(%d) &nbsp;&nbsp;&hookrightarrow; <span style="color:#A8A8A8;">%s</span>', $row_num, __('Images previously imported, skipping.', 'cob_theme'));
            $images_downloaded_for_log = true;
        }


    }

    // Log the import status
    cob_log_property_import_status(
        $source_id,
        $post_id,
        $post_type,
        $lang,
        $source_url_for_log,
        $images_downloaded_for_log,
        ($result_status === 'failed' ? 'failed' : 'completed')
    );

    return ['status' => $result_status, 'log' => $log];
}


// 5. Helper function for creating/finding terms and linking translations.
if (!function_exists('cob_get_or_create_term_for_linking')) {
    function cob_get_or_create_term_for_linking($term_name, $taxonomy_slug, $language_code = null) {
        if (empty($term_name) || empty($taxonomy_slug)) { return null; }

        if ($language_code && function_exists('pll_get_term_language')) {
            // First, try to find an existing term in the specified language
            $args = ['taxonomy' => $taxonomy_slug, 'name' => $term_name, 'hide_empty' => false, 'lang' => $language_code];
            $terms = get_terms($args);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    if (strcasecmp($term->name, $term_name) == 0) {
                        return $term->term_id;
                    }
                }
            }

            // If not found in the specified language, try to find it in any language
            // This is crucial for linking translations later if the term exists in another language
            $args_any_lang = ['taxonomy' => $taxonomy_slug, 'name' => $term_name, 'hide_empty' => false, 'lang' => ''];
            $terms_any_lang = get_terms($args_any_lang);
            if (!is_wp_error($terms_any_lang) && !empty($terms_any_lang)) {
                foreach ($terms_any_lang as $term) {
                    if (strcasecmp($term->name, $term_name) == 0) {
                        // Found in another language, set its language and return
                        pll_set_term_language($term->term_id, $language_code);
                        return $term->term_id;
                    }
                }
            }
        } else {
            // Non-Polylang environment or no language specified
            $existing_term = term_exists($term_name, $taxonomy_slug);
            if ($existing_term) { return is_array($existing_term) ? $existing_term['term_id'] : $existing_term; }
        }

        // If term doesn't exist at all, create it
        $new_term = wp_insert_term($term_name, $taxonomy_slug, []);
        if (is_wp_error($new_term) || !isset($new_term['term_id'])) { return null; }

        $term_id = $new_term['term_id'];

        if ($term_id && $language_code && function_exists('pll_set_term_language')) {
            pll_set_term_language($term_id, $language_code);
        }
        return $term_id;
    }
}

/**
 * Retrieves a log entry from the cob_property_import_log table.
 *
 * @param string $source_id The unique ID from the CSV.
 * @param string $post_type The post type.
 * @param string $lang      The language code (e.g., 'en', 'ar').
 * @return object|null The log entry object or null if not found.
 */
function cob_get_property_import_log_entry($source_id, $post_type, $lang) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cob_property_import_log';
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE source_id = %s AND post_type = %s AND lang = %s",
            $source_id,
            $post_type,
            $lang
        )
    );
}

// Add an action to run the activation hook when the theme is activated or plugin is initialized if this is part of a plugin.
// If this is a theme, place it in functions.php and it will run on theme activation.
// If this is a standalone plugin, wrap the function in a plugin header and call register_activation_hook.
// For demonstration, let's assume this is a theme's functions.php.
// If this code is in a plugin, use register_activation_hook(__FILE__, 'cob_property_importer_activate');
// For a theme, you might call it once or use a persistent option to ensure it runs only when needed.
// add_action( 'after_setup_theme', 'cob_property_importer_activate_on_theme_setup' );
// function cob_property_importer_activate_on_theme_setup() {
//     if ( ! get_option( 'cob_property_importer_table_created' ) ) {
//         cob_property_importer_activate();
//         update_option( 'cob_property_importer_table_created', true );
//     }
// }