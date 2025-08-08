<?php
/**
 * WordPress Admin Tool: Find, Edit, and Upload Images for Compounds, Developers, etc.
 *
 * This tool uses AJAX for a fast, in-place editing experience without page reloads.
 *
 * It adds an admin page under "Tools" to list terms missing featured images.
 * It provides a form to upload an image directly from a URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- Configuration ---
define( 'COB_MISSING_IMG_COMPOUND_TAX', 'compound' );
define( 'COB_MISSING_IMG_COMPOUND_META_KEY', '_compound_cover_image_id' );

define( 'COB_MISSING_IMG_DEVELOPER_TAX', 'developer' );
define( 'COB_MISSING_IMG_DEVELOPER_META_KEY', '_developer_logo_id' );

// Optional: City Image Configuration
// define( 'COB_MISSING_IMG_CITY_TAX', 'city' );
// define( 'COB_MISSING_IMG_CITY_META_KEY', '_city_image_id' );
// --- End Configuration ---

/**
 * Register the admin page for the Missing Images tool.
 */
function cob_register_missing_images_tool_page() {
    add_management_page(
        __( 'Manage Missing Images', 'cob_theme' ),
        __( 'Missing Images', 'cob_theme' ),
        'manage_categories',
        'cob-missing-images-tool',
        'cob_render_missing_images_tool_page'
    );
}
add_action( 'admin_menu', 'cob_register_missing_images_tool_page' );

/**
 * Enqueue scripts and styles only on our tool page.
 */
function cob_tool_enqueue_scripts( $hook ) {
    // Only load on our specific admin page.
    if ( 'tools_page_cob-missing-images-tool' !== $hook ) {
        return;
    }

    // Path to the new JS file. Assumes it's in the theme root.
    // Change if you place it elsewhere, e.g., in an 'assets/js' folder.
    $js_path = get_template_directory_uri() . '/inc/importer/cob-admin-ajax.js';
    $js_version = filemtime( get_template_directory() . '/inc/importer/cob-admin-ajax.js' ); // Auto-versioning

    wp_enqueue_script(
        'cob-tool-ajax-script',
        $js_path,
        [], // No dependencies
        $js_version,
        true // Load in footer
    );

    // Pass data to JavaScript, including the AJAX URL and a security nonce.
    wp_localize_script( 'cob-tool-ajax-script', 'cob_ajax_obj', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'cob_upload_image_nonce' ),
        'uploading_text' => __( 'Uploading...', 'cob_theme' ),
        'button_text' => __( 'Upload & Set Image', 'cob_theme' ),
    ]);
}
add_action( 'admin_enqueue_scripts', 'cob_tool_enqueue_scripts' );


/**
 * The server-side handler for our AJAX request.
 */
function cob_ajax_handle_image_upload() {
    // 1. Security Checks
    check_ajax_referer( 'cob_upload_image_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'cob_theme' ) ] );
    }

    // 2. Sanitize and validate input from the AJAX request
    $term_id   = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
    $taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
    $meta_key  = isset( $_POST['meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
    $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';

    if ( ! $term_id || ! $taxonomy || ! $meta_key || ! $image_url ) {
        wp_send_json_error( [ 'message' => __( 'Missing required data.', 'cob_theme' ) ] );
    }

    // 3. Include necessary WordPress files for image handling
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // 4. Download and process the image
    $temp_file = download_url( $image_url );
    if ( is_wp_error( $temp_file ) ) {
        wp_send_json_error( [ 'message' => $temp_file->get_error_message() ] );
    }

    $file = [ 'name' => basename( $image_url ), 'tmp_name' => $temp_file ];
    $attachment_id = media_handle_sideload( $file, 0 );

    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $temp_file );
        wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
    }

    // 5. Update term meta with the new attachment ID
    $update_result = update_term_meta( $term_id, $meta_key, $attachment_id );
    if ( ! $update_result ) {
        wp_send_json_error( [ 'message' => __( 'Image uploaded, but failed to link to term.', 'cob_theme' ) ] );
    }

    // 6. Success! Send a success response.
    $term = get_term($term_id);
    wp_send_json_success( [ 'message' => sprintf( __( 'Image set for %s.', 'cob_theme' ), $term->name ) ] );
}
// Hook for logged-in users
add_action( 'wp_ajax_cob_upload_image_via_ajax', 'cob_ajax_handle_image_upload' );


/**
 * Render the admin page for the Missing Images tool.
 */
function cob_render_missing_images_tool_page() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cob_theme' ) );
    }
    ?>
    <div class="wrap cob-missing-images-tool">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p><?php esc_html_e( 'This tool helps you quickly find and fix terms missing images. The process is done via AJAX without page reloads.', 'cob_theme' ); ?></p>

        <style>
            .cob-missing-images-tool .section { margin-bottom: 30px; padding: 15px; background-color: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .cob-missing-images-tool .section h2 { margin-top: 0; }
            .cob-missing-images-tool ul { list-style: none; margin-left: 0; }
            .cob-missing-images-tool li { margin-bottom: 12px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; transition: opacity 0.5s ease-out; }
            .cob-missing-images-tool li.fading-out { opacity: 0; }
            .cob-missing-images-tool .term-info { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 10px; }
            .cob-missing-images-tool .term-name { font-size: 1.1em; font-weight: bold; }
            .cob-missing-images-tool .no-items { color: #0073aa; font-weight: bold; }
            .cob-missing-images-tool .upload-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
            .cob-missing-images-tool .upload-form input[type="url"] { flex-grow: 1; }
            .cob-missing-images-tool .status-message { font-size: 0.9em; margin-left: 5px; }
            .cob-missing-images-tool .status-message.success { color: green; }
            .cob-missing-images-tool .status-message.error { color: #d63638; }
        </style>

        <?php
        cob_display_terms_missing_image_section(
            COB_MISSING_IMG_COMPOUND_TAX, COB_MISSING_IMG_COMPOUND_META_KEY,
            __( 'Compounds Missing Cover Image', 'cob_theme' ),
            __( 'All compounds have a cover image assigned.', 'cob_theme' ),
            true
        );

        cob_display_terms_missing_image_section(
            COB_MISSING_IMG_DEVELOPER_TAX, COB_MISSING_IMG_DEVELOPER_META_KEY,
            __( 'Developers Missing Logo', 'cob_theme' ),
            __( 'All developers have a logo assigned.', 'cob_theme' ),
            true
        );

        if ( defined( 'COB_MISSING_IMG_CITY_TAX' ) && defined( 'COB_MISSING_IMG_CITY_META_KEY' ) ) {
            cob_display_terms_missing_image_section(
                COB_MISSING_IMG_CITY_TAX, COB_MISSING_IMG_CITY_META_KEY,
                __( 'Cities Missing Image', 'cob_theme' ),
                __( 'All cities have an image assigned.', 'cob_theme' ),
                true
            );
        }
        ?>
    </div>
    <?php
}

/**
 * Helper function to display a section for terms missing a specific image meta.
 */
function cob_display_terms_missing_image_section( $taxonomy_slug, $meta_key, $section_title, $no_items_message, $allow_upload = false ) {
    echo '<div class="section">';
    echo '<h2>' . esc_html( $section_title ) . '</h2>';

    if ( ! taxonomy_exists( $taxonomy_slug ) ) {
        echo '<p class="error">' . sprintf( esc_html__( 'Error: Taxonomy "%s" does not exist.', 'cob_theme' ), esc_html( $taxonomy_slug ) ) . '</p></div>';
        return;
    }

    $terms_missing_image = get_terms([
        'taxonomy'   => $taxonomy_slug,
        'hide_empty' => false,
        'fields'     => 'all',
        'meta_query' => [['relation' => 'OR', ['key' => $meta_key, 'compare' => 'NOT EXISTS'], ['key' => $meta_key, 'value' => '', 'compare' => '='], ['key' => $meta_key, 'value' => '0', 'compare' => '=']]],
    ]);

    if ( is_wp_error( $terms_missing_image ) ) {
        echo '<p class="error">' . sprintf( esc_html__( 'Error fetching terms: %s', 'cob_theme' ), esc_html( $terms_missing_image->get_error_message() ) ) . '</p>';
    } elseif ( empty( $terms_missing_image ) ) {
        echo '<p class="no-items">' . esc_html( $no_items_message ) . '</p>';
    } else {
        echo '<ul id="taxonomy-' . esc_attr($taxonomy_slug) . '-list">';
        foreach ( $terms_missing_image as $term ) {
            if ( ! $term instanceof WP_Term ) continue;
            echo '<li id="term-item-' . esc_attr( $term->term_id ) . '">';
            echo '<div class="term-info">';
			echo '<span class="term-name">' . esc_html( $term->name ) . '</span>';
            $edit_link = get_edit_term_link( $term->term_id, $taxonomy_slug );
            if ( $edit_link ) {
                echo ' <a href="' . esc_url( $edit_link ) . '" target="_blank" class="button button-small">' . esc_html__( 'Edit in New Tab', 'cob_theme' ) . '</a>';
            }
            echo '</div>';

            if ( $allow_upload ) {
                echo '<form class="upload-form">';
                echo '<input type="hidden" name="term_id" value="' . esc_attr( $term->term_id ) . '">';
                echo '<input type="hidden" name="taxonomy" value="' . esc_attr( $taxonomy_slug ) . '">';
                echo '<input type="hidden" name="meta_key" value="' . esc_attr( $meta_key ) . '">';
                echo '<input type="url" name="image_url" placeholder="' . esc_attr__( 'Paste image URL here...', 'cob_theme' ) . '" required>';
                echo '<button type="submit" class="button button-primary">' . esc_html__( 'Upload & Set Image', 'cob_theme' ) . '</button>';
                echo '<span class="status-message"></span>';
                echo '</form>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '<p>' . sprintf( esc_html( _n( '%d item found.', '%d items found.', count( $terms_missing_image ), 'cob_theme' ) ), count( $terms_missing_image ) ) . '</p>';
    }
    echo '</div>';
}
?>