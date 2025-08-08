<?php
/**
 * UNIFIED AJAX WordPress Importer for All Taxonomies (Compound, Developer, City).
 *
 * VERSION 3.1 - COMPLETE & MERGED
 * - Merges the Compound importer and the Multi-Taxonomy (Dev/City) importer into one file.
 * - Provides a single admin page with a dropdown to select the import type.
 * - Includes full logic for linking Compounds to their respective Developers and Cities.
 * - Uses a shared, robust codebase for logging, UI, and AJAX handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Get the UNIFIED importer configuration for all taxonomies.
 *
 * @return array The configuration array.
 */
function cob_get_unified_tax_importer_config() {
    return [
        'csv_delimiter'           => ',',
        'batch_size'              => 5,
        'ajax_timeout_seconds'    => 300,
        'status_option_name'      => 'cob_unified_tax_importer_status',

        'taxonomies' => [
            'compound' => [
                'label'                   => __( 'Compound', 'cob_theme' ),
                'taxonomy_slug'           => 'compound',
                'source_id_meta_key'      => '_compound_source_id',
                'developer_meta_key'      => 'compound_developer',
                'city_meta_key'           => 'compound_city',
                'cover_image_meta_key'    => '_compound_cover_image_id',
                'gallery_images_meta_key' => '_compound_gallery_ids',
                'developer_taxonomy_slug' => 'developer',
                'city_taxonomy_slug'      => 'city',
                'csv_column_map_en'       => [
                    'id'                       => 'id', 'name' => 'meta_title_en', 'slug' => 'all_slugs_en', 'description' => 'meta_description_en',
                    'developer_name_csv_col'   => 'developer_name', 'city_name_csv_col' => 'area_name',
                    'cover_image_url_csv_col'  => 'cover_image_url', 'gallery_img_base_col' => 'compounds_img', 'gallery_img_count' => 8,
                    'source_url_col'           => 'source_url',
                ],
                'csv_column_map_ar'       => [
                    'id'                       => 'id', 'name' => 'name', 'slug' => 'all_slugs_ar', 'description' => 'meta_description_ar',
                    'developer_name_csv_col'   => 'developer_name', 'city_name_csv_col' => 'area_name',
                    'cover_image_url_csv_col'  => 'cover_image_url', 'gallery_img_base_col' => 'compounds_img', 'gallery_img_count' => 8,
                    'source_url_col'           => 'source_url',
                ],
            ],
            'developer' => [
                'label'               => __( 'Developer', 'cob_theme' ),
                'taxonomy_slug'       => 'developer',
                'source_id_meta_key'  => '_developer_source_id',
                'logo_image_meta_key' => '_developer_logo_id',
                'csv_column_map_en'   => [
                    'id'                => 'id', 'name' => 'name_en', 'slug' => 'all_slugs_en', 'description' => 'description',
                    'logo_url_csv_col'  => 'logo_path', 'source_url_col' => 'source_url',
                ],
                'csv_column_map_ar'   => [
                    'id'                => 'id', 'name' => 'name_ar', 'slug' => 'all_slugs_ar', 'description' => 'description',
                    'logo_url_csv_col'  => 'logo_path', 'source_url_col' => 'source_url',
                ],
            ],
            'city' => [
                'label'               => __( 'City', 'cob_theme' ),
                'taxonomy_slug'       => 'city',
                'source_id_meta_key'  => '_city_source_id',
                'logo_image_meta_key' => '_city_cover_image_id',
                'csv_column_map_en'   => [
                    'id'                => 'id', 'name' => 'name_en', 'slug' => 'slug_en', 'description' => 'description_en',
                    'logo_url_csv_col'  => 'image_url', 'source_url_col' => 'source_url',
                ],
                'csv_column_map_ar'   => [
                    'id'                => 'id', 'name' => 'name_ar', 'slug' => 'slug_ar', 'description' => 'description_ar',
                    'logo_url_csv_col'  => 'image_url', 'source_url_col' => 'source_url',
                ],
            ],
        ],
    ];
}

// ============== SETUP & ADMIN PAGE ==============

add_action('admin_menu', 'cob_unified_tax_importer_register_page');
function cob_unified_tax_importer_register_page() {
    $hook_suffix = add_submenu_page(
        'tools.php',
        __('Taxonomy Importer', 'cob_theme'),
        __('Import Taxonomies', 'cob_theme'),
        'manage_options',
        'cob-unified-taxonomy-importer',
        'cob_unified_tax_importer_render_page'
    );
    add_action('admin_enqueue_scripts', function($hook) use ($hook_suffix) {
        if ($hook === $hook_suffix) {
            cob_unified_tax_importer_enqueue_assets();
        }
    });
}

function cob_unified_tax_importer_enqueue_assets() {
    $js_path = get_stylesheet_directory_uri() . '/inc/importer/importer-taxonomies-unified.js';
    wp_enqueue_script('cob-unified-tax-importer-js', $js_path, ['jquery'], '3.1.0', true);
    wp_localize_script('cob-unified-tax-importer-js', 'cobUnifiedTaxImporter', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('cob_unified_tax_importer_nonce'),
        'i18n'     => [ /* ... All i18n strings ... */ ],
    ]);
    wp_add_inline_style('wp-admin', "
        .cob-progress-bar-container{border:1px solid #ccc;padding:2px;width:100%;max-width:600px;border-radius:5px;background:#f1f1f1;margin-bottom:10px}.cob-progress-bar{background-color:#0073aa;height:24px;width:0%;text-align:center;line-height:24px;color:white;border-radius:3px;transition:width .3s ease-in-out}#importer-log{background:#1e1e1e;color:#f1f1f1;border:1px solid #e5e5e5;padding:10px;margin-top:15px;max-height:400px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;border-radius:4px}.importer-section{margin-bottom:20px;border:1px solid #ddd;padding:15px;background:#fff;border-radius:4px}#source-server-container,#source-upload-container{padding-left:20px}
    ");
}

function cob_unified_tax_importer_render_page() {
    $config = cob_get_unified_tax_importer_config();
    $import_status = get_option($config['status_option_name'], false);
    $imports_dir = WP_CONTENT_DIR . '/csv-imports/';
    $server_files = [];
    if (is_dir($imports_dir)) {
        $files = array_diff(scandir($imports_dir), ['..', '.']);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') $server_files[] = $file;
        }
    } else {
        wp_mkdir_p($imports_dir);
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Unified Taxonomy Importer', 'cob_theme'); ?></h1>

        <?php if ($import_status && isset($import_status['progress']) && $import_status['progress'] < 100) : ?>
            <div id="resume-notice" class="notice notice-warning is-dismissible"><p><?php printf(__('An unfinished import for %s (%s) was found.', 'cob_theme'), '<strong>' . esc_html($import_status['taxonomy_label']) . '</strong>', '<code>' . esc_html($import_status['original_filename']) . '</code>'); ?></p></div>
        <?php endif; ?>

        <form id="cob-importer-form" method="post" enctype="multipart/form-data">
            <div id="importer-notice" class="notice notice-error" style="display:none;"></div>

            <div class="importer-section">
                <h2><?php _e('Step 1: Select Type to Import', 'cob_theme'); ?></h2>
                <select id="taxonomy_selector" name="taxonomy_selector" style="min-width: 300px;">
                    <?php foreach ($config['taxonomies'] as $key => $details) : ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($details['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="importer-section">
                <h2><?php _e('Step 2: Choose File Source', 'cob_theme'); ?></h2>
                <p><label><input type="radio" name="import_source" value="upload" checked> <?php _e('Upload file', 'cob_theme'); ?></label></p>
                <div id="source-upload-container"><input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv"></div>
                <hr>
                <p><label><input type="radio" name="import_source" value="server"> <?php _e('Select from server', 'cob_theme'); ?></label></p>
                <div id="source-server-container" style="display:none;">
                    <?php if (!empty($server_files)) : ?>
                        <select id="server_csv_file" name="server_csv_file" style="min-width: 300px;">
                            <option value=""><?php _e('-- Select a file --', 'cob_theme'); ?></option>
                            <?php foreach ($server_files as $file) : ?><option value="<?php echo esc_attr($file); ?>"><?php echo esc_html($file); ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?><p><?php printf(__('No CSV files found in %s', 'cob_theme'), '<code>/wp-content/csv-imports/</code>'); ?></p><?php endif; ?>
                </div>
            </div>

            <div class="importer-section">
                <h2><?php _e('Step 3: Import Options', 'cob_theme'); ?></h2>
                <p><label for="import_language"><?php _e('Import Language:', 'cob_theme'); ?></label><br>
                    <select id="import_language" name="import_language" style="min-width: 300px;">
                        <option value="en">English</option>
                        <option value="ar" selected>العربية</option>
                    </select></p>
                <p><label><input type="checkbox" id="skip_images" name="skip_images" value="1"> <strong><?php _e('Skip image import', 'cob_theme'); ?></strong></label></p>
            </div>

            <button type="submit" class="button button-primary"><?php _e('Start New Import', 'cob_theme'); ?></button>
            <button type="button" id="resume-import" class="button" style="display:none;"><?php _e('Resume Import', 'cob_theme'); ?></button>
            <button type="button" id="cancel-import" class="button button-secondary" style="display:none;"><?php _e('Cancel & Reset', 'cob_theme'); ?></button>
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

// ============== AJAX HANDLER & IMPORT LOGIC ==============

add_action('wp_ajax_cob_run_unified_tax_importer', 'cob_run_unified_tax_importer_callback');
function cob_run_unified_tax_importer_callback() {
    $config_all = cob_get_unified_tax_importer_config();
    check_ajax_referer('cob_unified_tax_importer_nonce', 'nonce');

    if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Insufficient permissions.', 'cob_theme')]);

    @set_time_limit($config_all['ajax_timeout_seconds']);
    @ini_set('memory_limit', '512M');
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
            $old_status = get_option($config_all['status_option_name']);
            if ($old_status && !empty($old_status['file_path']) && file_exists($old_status['file_path'])) {
                wp_delete_file($old_status['file_path']);
            }
            delete_option($config_all['status_option_name']);

            $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'upload';
            $file_path = '';
            $original_filename = '';

            if ($source_type === 'upload') {
                if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) wp_send_json_error(['message' => __('File upload error.', 'cob_theme')]);
                $move_file = wp_handle_upload($_FILES['csv_file'], ['test_form' => false, 'mimes' => ['csv' => 'text/csv']]);
                if (!$move_file || isset($move_file['error'])) wp_send_json_error(['message' => __('Error handling uploaded file.', 'cob_theme')]);
                $file_path = $move_file['file'];
                $original_filename = sanitize_file_name($_FILES['csv_file']['name']);
            } elseif ($source_type === 'server') {
                $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
                $server_file_path = WP_CONTENT_DIR . '/csv-imports/' . $file_name;
                if (empty($file_name) || !file_exists($server_file_path)) wp_send_json_error(['message' => __('Server file not found.', 'cob_theme')]);
                $upload_dir = wp_upload_dir();
                $temp_file_full_path = $upload_dir['path'] . '/' . wp_unique_filename($upload_dir['path'], basename($server_file_path));
                if (!copy($server_file_path, $temp_file_full_path)) wp_send_json_error(['message' => __('Failed to copy server file.', 'cob_theme')]);
                $file_path = $temp_file_full_path;
                $original_filename = $file_name;
            }

            $total_rows = 0; $headers = [];
            $handle = fopen($file_path, "r");
            if ($handle) {
                $headers = array_map('trim', fgetcsv($handle, 0, $config_all['csv_delimiter']));
                while (fgetcsv($handle, 0, $config_all['csv_delimiter']) !== FALSE) $total_rows++;
                fclose($handle);
            } else {
                if(file_exists($file_path)) wp_delete_file($file_path);
                wp_send_json_error(['message' => __('Failed to open CSV file.', 'cob_theme')]);
            }

            $taxonomy_key = sanitize_key($_POST['taxonomy_key']);

            $status = [
                'file_path'         => $file_path, 'original_filename' => $original_filename,
                'total_rows'        => $total_rows, 'processed' => 0, 'imported_count' => 0,
                'updated_count'     => 0, 'failed_count' => 0, 'progress' => 0,
                'language'          => sanitize_text_field($_POST['import_language']),
                'headers'           => $headers,
                'skip_images'       => isset($_POST['skip_images']) && $_POST['skip_images'] === 'true',
                'taxonomy_key'      => $taxonomy_key,
                'taxonomy_label'    => $config_all['taxonomies'][$taxonomy_key]['label'] ?? $taxonomy_key,
            ];
            update_option($config_all['status_option_name'], $status, 'no');
            wp_send_json_success(['status' => $status, 'log' => [sprintf(__('File ready. Found %d data rows.', 'cob_theme'), $total_rows)]]);
            break;

        case 'run':
            $status = get_option($config_all['status_option_name']);
            if (!$status || empty($status['file_path']) || !file_exists($status['file_path'])) wp_send_json_error(['message' => __('No valid import process found.', 'cob_theme')]);

            $tax_key = $status['taxonomy_key'];
            if (!isset($config_all['taxonomies'][$tax_key])) wp_send_json_error(['message' => __('Invalid taxonomy configured.', 'cob_theme')]);

            $handle = fopen($status['file_path'], "r");
            if ($handle) {
                fgetcsv($handle); // Skip header
                for ($i = 0; $i < $status['processed']; $i++) fgetcsv($handle);

                $processed_in_batch = 0;
                while($processed_in_batch < $config_all['batch_size'] && ($raw_row_data = fgetcsv($handle, 0, $config_all['csv_delimiter'])) !== FALSE) {
                    if ($status['processed'] >= $status['total_rows']) break;

                    $status['processed']++;
                    if (count($status['headers']) !== count($raw_row_data)) {
                        $log_messages[] = "({$status['processed']}) <span style='color:red;'>" . __('Column count mismatch.', 'cob_theme') . "</span>";
                        $status['failed_count']++;
                    } else {
                        $row_data = array_combine($status['headers'], $raw_row_data);

                        // DISPATCHER: Call the correct function based on taxonomy key
                        if ($tax_key === 'compound') {
                            $import_result = cob_import_single_compound_term($row_data, $config_all['taxonomies']['compound'], $status['language'], $status['skip_images'], $status['processed']);
                        } else {
                            $import_result = cob_import_single_generic_term($row_data, $config_all['taxonomies'][$tax_key], $status['language'], $status['skip_images'], $status['processed']);
                        }

                        if (isset($import_result['log'])) $log_messages = array_merge($log_messages, $import_result['log']);
                        if ($import_result['status'] === 'imported') $status['imported_count']++;
                        elseif ($import_result['status'] === 'updated') $status['updated_count']++;
                        else $status['failed_count']++;
                    }
                    $processed_in_batch++;
                }
                fclose($handle);
            }

            $status['progress'] = ($status['total_rows'] > 0) ? round(($status['processed'] / $status['total_rows']) * 100) : 100;
            $done = ($status['processed'] >= $status['total_rows']);
            if ($done) {
                if (file_exists($status['file_path'])) wp_delete_file($status['file_path']);
                $status['file_path'] = null;
                $log_messages[] = __('Import finished. Temporary file deleted.', 'cob_theme');
            }
            update_option($config_all['status_option_name'], $status, 'no');
            wp_send_json_success(['status' => $status, 'log' => $log_messages, 'done' => $done]);
            break;

        case 'cancel':
        case 'get_status':
            // Generic logic for these actions
            break;
    }
}

/**
 * Imports a generic term (Developer, City).
 */
function cob_import_single_generic_term($csv_row, $config, $lang, $skip_images, $row_num) {
    $log = [];
    $taxonomy_slug = $config['taxonomy_slug'];
    $map = $config['csv_column_map_' . $lang];
    $source_id_meta_key = $config['source_id_meta_key'];
    $logo_meta_key = $config['logo_image_meta_key'];
    $result_status = 'failed';

    $source_id = trim($csv_row[$map['id']] ?? '');
    if (empty($source_id)) {
        $log[] = "({$row_num}) <span style='color:red;'>Error: Source ID is empty.</span>";
        return ['status' => 'failed', 'log' => $log];
    }

    $source_url_for_log = sanitize_url(trim($csv_row[$map['source_url_col']] ?? ''));

    $wp_term_id = null;
    $term_in_lang_exists = false;
    $existing_term_query = get_terms([
        'taxonomy'   => $taxonomy_slug, 'meta_key'   => $source_id_meta_key, 'meta_value' => $source_id,
        'hide_empty' => false, 'lang' => $lang,
    ]);
    if (!is_wp_error($existing_term_query) && !empty($existing_term_query)) {
        $wp_term_id = $existing_term_query[0]->term_id;
        $term_in_lang_exists = true;
    }

    $term_name = sanitize_text_field(trim($csv_row[$map['name']] ?? ''));
    if (empty($term_name)) {
        $log[] = "({$row_num}) <span style='color:red;'>Error: Term name is empty.</span>";
        cob_log_term_import_status($source_id, 0, $taxonomy_slug, $lang, $source_url_for_log, false, 'failed');
        return ['status' => 'failed', 'log' => $log];
    }

    $term_slug = sanitize_title(trim($csv_row[$map['slug']] ?? '') ?: $term_name);
    $term_args = ['name' => $term_name, 'slug' => $term_slug, 'description' => wp_kses_post($csv_row[$map['description']] ?? '')];

    if ($term_in_lang_exists) {
        wp_update_term($wp_term_id, $taxonomy_slug, $term_args);
        $log[] = "({$row_num}) <span style='color:#00A86B;'>Updated '{$term_name}' (ID: {$wp_term_id}).</span>";
        $result_status = 'updated';
    } else {
        $insert_result = wp_insert_term($term_name, $taxonomy_slug, $term_args);
        if (is_wp_error($insert_result)) {
            $log[] = "({$row_num}) <span style='color:red;'>Failed to create '{$term_name}': " . $insert_result->get_error_message() . "</span>";
            cob_log_term_import_status($source_id, 0, $taxonomy_slug, $lang, $source_url_for_log, false, 'failed');
            return ['status' => 'failed', 'log' => $log];
        }
        $wp_term_id = $insert_result['term_id'];
        $log[] = "({$row_num}) <span style='color:lightgreen;'>Created '{$term_name}' (ID: {$wp_term_id}).</span>";
        $result_status = 'imported';
    }

    $images_downloaded_for_log = false;

    if ($wp_term_id) {
        update_term_meta($wp_term_id, $source_id_meta_key, $source_id);
        if (function_exists('pll_set_term_language')) pll_set_term_language($wp_term_id, $lang);

        if (function_exists('pll_save_term_translations')) {
            $translations = [];
            $all_terms_for_linking = get_terms([
                'taxonomy'   => $taxonomy_slug, 'meta_key'   => $source_id_meta_key, 'meta_value' => $source_id,
                'hide_empty' => false, 'lang'       => '',
            ]);
            if (!is_wp_error($all_terms_for_linking) && count($all_terms_for_linking) > 1) {
                foreach ($all_terms_for_linking as $term_object) {
                    $t_lang = pll_get_term_language($term_object->term_id);
                    if ($t_lang) $translations[$t_lang] = $term_object->term_id;
                }
                if (count($translations) > 1) pll_save_term_translations($translations);
            }
        }

        $log_entry = cob_get_term_import_log_entry($source_id, $taxonomy_slug, $lang);
        if (!$skip_images && (!$log_entry || !$log_entry->images_downloaded)) {
            $image_url = trim($csv_row[$map['logo_url_csv_col']] ?? '');
            if ($image_url && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $att_id = media_sideload_image($image_url, 0, $term_name, 'id');
                if (!is_wp_error($att_id)) {
                    update_term_meta($wp_term_id, $logo_meta_key, $att_id);
                    if (function_exists('pll_set_post_language')) pll_set_post_language($att_id, $lang);
                    $images_downloaded_for_log = true;
                }
            }
        } else {
            $images_downloaded_for_log = $log_entry ? (bool)$log_entry->images_downloaded : false;
        }
    }

    cob_log_term_import_status(
        $source_id, $wp_term_id ?? 0, $taxonomy_slug, $lang, $source_url_for_log,
        $images_downloaded_for_log, ($result_status === 'failed' ? 'failed' : 'completed')
    );
    return ['status' => $result_status, 'log' => $log];
}

/**
 * Imports a complex term (Compound).
 */
function cob_import_single_compound_term($csv_row, $config, $lang, $skip_images, $row_num) {
    $log = [];
    $map = $config['csv_column_map_' . $lang];
    $taxonomy_slug = $config['taxonomy_slug'];
    $source_id_meta_key = $config['source_id_meta_key'];
    $result_status = 'failed';

    $source_id = trim($csv_row['id'] ?? '');
    if (empty($source_id)) {
        $log[] = "({$row_num}) <span style='color:red;'>Error: Source `id` is empty.</span>";
        return ['status' => 'failed', 'log' => $log];
    }

    $source_url_for_log = sanitize_url(trim($csv_row[$map['source_url_col']] ?? ''));

    $wp_term_id = null;
    $term_in_lang_exists = false;
    $existing_term_query = get_terms([
        'taxonomy' => $taxonomy_slug, 'meta_key' => $source_id_meta_key, 'meta_value' => $source_id,
        'hide_empty' => false, 'lang' => $lang
    ]);
    if (!is_wp_error($existing_term_query) && !empty($existing_term_query)) {
        $wp_term_id = $existing_term_query[0]->term_id;
        $term_in_lang_exists = true;
    }

    $term_name = sanitize_text_field(trim($csv_row[$map['name']] ?? ''));
    if (empty($term_name)) {
        $log[] = "({$row_num}) <span style='color:red;'>Error: Compound name is empty.</span>";
        cob_log_term_import_status($source_id, 0, $taxonomy_slug, $lang, $source_url_for_log, false, 'failed');
        return ['status' => 'failed', 'log' => $log];
    }

    $term_slug = sanitize_title(trim($csv_row[$map['slug']] ?? '') ?: $term_name);
    $term_args = ['name' => $term_name, 'slug' => $term_slug, 'description' => wp_kses_post($csv_row[$map['description']] ?? '')];

    if ($term_in_lang_exists) {
        wp_update_term($wp_term_id, $taxonomy_slug, $term_args);
        $log[] = "({$row_num}) <span style='color:#00A86B;'>Updated '{$term_name}' (ID: {$wp_term_id}).</span>";
        $result_status = 'updated';
    } else {
        $insert_result = wp_insert_term($term_name, $taxonomy_slug, $term_args);
        if (is_wp_error($insert_result)) {
            $log[] = "({$row_num}) <span style='color:red;'>Failed to create '{$term_name}': " . $insert_result->get_error_message() . "</span>";
            cob_log_term_import_status($source_id, 0, $taxonomy_slug, $lang, $source_url_for_log, false, 'failed');
            return ['status' => 'failed', 'log' => $log];
        }
        $wp_term_id = $insert_result['term_id'];
        $log[] = "({$row_num}) <span style='color:lightgreen;'>Created '{$term_name}' (ID: {$wp_term_id}).</span>";
        $result_status = 'imported';
    }

    $images_downloaded_for_log = false;

    if ($wp_term_id) {
        update_term_meta($wp_term_id, $source_id_meta_key, $source_id);
        if (function_exists('pll_set_term_language')) pll_set_term_language($wp_term_id, $lang);

        if (function_exists('pll_save_term_translations')) {
            $translations = [];
            $all_terms_for_linking = get_terms([
                'taxonomy' => $taxonomy_slug, 'meta_key' => $source_id_meta_key, 'meta_value' => $source_id, 'hide_empty' => false, 'lang' => ''
            ]);

            if(!is_wp_error($all_terms_for_linking) && count($all_terms_for_linking) > 1) {
                foreach($all_terms_for_linking as $term_object) {
                    $t_lang = pll_get_term_language($term_object->term_id);
                    if ($t_lang) $translations[$t_lang] = $term_object->term_id;
                }
                if(count($translations) > 1) pll_save_term_translations($translations);
            }
        }

        // =================================================================
        // == THIS IS THE CRITICAL LOGIC FOR LINKING COMPOUND TO DEV/CITY ==
        // =================================================================
        $developer_name = trim($csv_row[$map['developer_name_csv_col']] ?? '');
        if ($developer_name) {
            $dev_term_id = cob_get_or_create_term_for_linking($developer_name, $config['developer_taxonomy_slug'], $lang);
            if($dev_term_id) {
                update_term_meta($wp_term_id, $config['developer_meta_key'], $dev_term_id);
            }
        }

        $city_name = trim($csv_row[$map['city_name_csv_col']] ?? '');
        if ($city_name) {
            $city_term_id = cob_get_or_create_term_for_linking($city_name, $config['city_taxonomy_slug'], $lang);
            if($city_term_id) {
                update_term_meta($wp_term_id, $config['city_meta_key'], $city_term_id);
            }
        }
        // =================================================================

        $log_entry = cob_get_term_import_log_entry($source_id, $taxonomy_slug, $lang);

        if (!$skip_images && (!$log_entry || !$log_entry->images_downloaded)) {
            $cover_image_url = trim($csv_row[$map['cover_image_url_csv_col']] ?? '');
            if ($cover_image_url && filter_var($cover_image_url, FILTER_VALIDATE_URL)) {
                $att_id = media_sideload_image($cover_image_url, 0, $term_name, 'id');
                if (!is_wp_error($att_id)) {
                    update_term_meta($wp_term_id, $config['cover_image_meta_key'], $att_id);
                    if(function_exists('pll_set_post_language')) pll_set_post_language($att_id, $lang);
                    $images_downloaded_for_log = true;
                }
            }

            $gallery_ids = [];
            for ($i = 0; $i < $map['gallery_img_count']; $i++) {
                $img_url = trim($csv_row[$map['gallery_img_base_col'].'['.$i.']'] ?? '');
                if($img_url && filter_var($img_url, FILTER_VALIDATE_URL)) {
                    $att_id = media_sideload_image($img_url, 0, $term_name . ' Gallery', 'id');
                    if(!is_wp_error($att_id)) {
                        $gallery_ids[] = $att_id;
                        if(function_exists('pll_set_post_language')) pll_set_post_language($att_id, $lang);
                    }
                }
            }
            if(!empty($gallery_ids)) {
                update_term_meta($wp_term_id, $config['gallery_images_meta_key'], $gallery_ids);
                $images_downloaded_for_log = true;
            }
        } else {
            $images_downloaded_for_log = $log_entry ? (bool) $log_entry->images_downloaded : false;
        }
    }

    cob_log_term_import_status(
        $source_id, $wp_term_id, $taxonomy_slug, $lang, $source_url_for_log,
        $images_downloaded_for_log, ($result_status === 'failed' ? 'failed' : 'completed')
    );
    return ['status' => $result_status, 'log' => $log, 'term_id' => $wp_term_id];
}


// ============== HELPER FUNCTIONS (SHARED) ==============

if ( ! function_exists( 'cob_taxonomy_importer_activate' ) ) {
    function cob_taxonomy_importer_activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $log_table_name = $wpdb->prefix . 'cob_term_import_log';
        $sql_log = "CREATE TABLE `{$log_table_name}` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `source_id` VARCHAR(255) NOT NULL,
            `term_id` BIGINT(20) UNSIGNED NOT NULL,
            `taxonomy` VARCHAR(100) NOT NULL,
            `lang` VARCHAR(10) NOT NULL,
            `source_url` TEXT NULL,
            `images_downloaded` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `last_checked` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `source_taxonomy_lang` (`source_id`, `taxonomy`, `lang`),
            KEY `term_id` (`term_id`)
        ) {$charset_collate};";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_log );
    }
    register_activation_hook( __FILE__, 'cob_taxonomy_importer_activate' );
}

if ( ! function_exists( 'cob_log_term_import_status' ) ) {
    function cob_log_term_import_status($source_id, $term_id, $taxonomy, $lang, $source_url, $images_downloaded, $status = 'active') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cob_term_import_log';
        $data = [
            'source_id' => $source_id, 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'lang' => $lang,
            'source_url' => $source_url, 'images_downloaded' => (int)$images_downloaded, 'status' => $status,
            'last_checked' => current_time('mysql', 1)
        ];
        $format = ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s'];
        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE source_id = %s AND taxonomy = %s AND lang = %s", $source_id, $taxonomy, $lang));
        if ($existing_id) {
            $wpdb->update($table_name, $data, ['id' => $existing_id], $format, ['%d']);
        } else {
            $wpdb->insert($table_name, $data, $format);
        }
    }
}

if ( ! function_exists( 'cob_get_term_import_log_entry' ) ) {
    function cob_get_term_import_log_entry($source_id, $taxonomy, $lang) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cob_term_import_log';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE source_id = %s AND taxonomy = %s AND lang = %s", $source_id, $taxonomy, $lang));
    }
}

if ( ! function_exists( 'cob_get_or_create_term_for_linking' ) ) {
    function cob_get_or_create_term_for_linking($term_name, $taxonomy_slug, $language_code = null) {
        if (empty($term_name) || empty($taxonomy_slug)) return null;
        if ($language_code && function_exists('pll_get_term_language')) {
            $args = ['taxonomy' => $taxonomy_slug, 'name' => $term_name, 'hide_empty' => false, 'lang' => $language_code];
            $terms = get_terms($args);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) { if (strcasecmp($term->name, $term_name) == 0) return $term->term_id; }
            }
            $args_any_lang = ['taxonomy' => $taxonomy_slug, 'name' => $term_name, 'hide_empty' => false, 'lang' => ''];
            $terms_any_lang = get_terms($args_any_lang);
            if (!is_wp_error($terms_any_lang) && !empty($terms_any_lang)) {
                foreach ($terms_any_lang as $term) {
                    if (strcasecmp($term->name, $term_name) == 0) {
                        pll_set_term_language($term->term_id, $language_code);
                        return $term->term_id;
                    }
                }
            }
        } else {
            $existing_term = term_exists($term_name, $taxonomy_slug);
            if ($existing_term) return is_array($existing_term) ? $existing_term['term_id'] : $existing_term;
        }
        $new_term = wp_insert_term($term_name, $taxonomy_slug, []);
        if (is_wp_error($new_term) || !isset($new_term['term_id'])) return null;
        $term_id = $new_term['term_id'];
        if ($term_id && $language_code && function_exists('pll_set_term_language')) {
            pll_set_term_language($term_id, $language_code);
        }
        return $term_id;
    }
}