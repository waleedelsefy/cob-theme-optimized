<?php
/**
 * Adds a "Featured" toggle to all public taxonomies.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Featured_Taxonomy' ) ) {
    /**
     * Class COB_Featured_Taxonomy
     * Handles the "Featured" toggle functionality for taxonomies.
     */
    class COB_Featured_Taxonomy {

        /**
         * Initialize the class and set up the hooks.
         */
        public function __construct() {
            // Run on init with a late priority to ensure all taxonomies are registered.
            add_action( 'init', array( $this, 'add_taxonomy_columns_on_init' ), 99 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'wp_ajax_cob_toggle_featured_term', array( $this, 'toggle_featured_term' ) );
        }

        /**
         * Loop through all public taxonomies and add the featured column.
         * This is hooked to 'init' to ensure all taxonomies are registered.
         */
        public function add_taxonomy_columns_on_init() {
            $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

            foreach ( $taxonomies as $taxonomy ) {
                add_filter( "manage_edit-{$taxonomy}_columns", array( $this, 'add_featured_column' ) );
                add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'render_featured_column' ), 10, 3 );
            }
        }

        /**
         * Add the "Featured" column to the taxonomy table header.
         *
         * @param array $columns Existing columns.
         * @return array Modified columns.
         */
        public function add_featured_column( $columns ) {
            $columns['featured'] = __( 'Featured', 'cob_theme' );
            return $columns;
        }

        /**
         * Render the content of the "Featured" column, which is the toggle switch.
         *
         * @param string $content      The default column content (empty).
         * @param string $column_name  The name of the column being rendered.
         * @param int    $term_id      The ID of the current term.
         * @return string The HTML for the toggle switch.
         */
        public function render_featured_column( $content, $column_name, $term_id ) {
            if ( 'featured' === $column_name ) {
                // Get the current featured status from term meta.
                $is_featured = get_term_meta( $term_id, 'is_featured', true );
                $checked     = $is_featured ? 'checked' : '';
                // Create a nonce for security.
                $nonce       = wp_create_nonce( 'cob_toggle_featured_nonce_' . $term_id );

                // Build the HTML for the toggle switch.
                $content  = '<label class="cob-switch">';
                $content .= '<input type="checkbox" class="cob-featured-toggle" data-term-id="' . esc_attr( $term_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" ' . $checked . '>';
                $content .= '<span class="cob-slider cob-round"></span>';
                $content .= '</label>';
            }
            return $content;
        }

        /**
         * Enqueue admin scripts and styles needed for the toggle functionality.
         *
         * @param string $hook The current admin page hook.
         */
        public function enqueue_scripts() {
            // Get the current screen information.
            $screen = get_current_screen();

            // Only load on taxonomy editing screens.
            if ( ! $screen || 'edit-tags' !== $screen->base ) {
                return;
            }

            // Inline CSS for the toggle switch for simplicity.
            wp_add_inline_style( 'wp-admin', '
                .cob-switch { position: relative; display: inline-block; width: 50px; height: 28px; }
                .cob-switch input { opacity: 0; width: 0; height: 0; }
                .cob-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
                .cob-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
                input:checked + .cob-slider { background-color: #2196F3; }
                input:focus + .cob-slider { box-shadow: 0 0 1px #2196F3; }
                input:checked + .cob-slider:before { transform: translateX(22px); }
                .cob-slider.cob-round { border-radius: 28px; }
                .cob-slider.cob-round:before { border-radius: 50%; }
            ' );

            // Inline JavaScript for handling the AJAX toggle.
            wp_add_inline_script( 'jquery-core', '
                jQuery(document).ready(function($) {
                    // Use event delegation for dynamically loaded terms (e.g., after adding a new one).
                    $("#the-list").on("change", ".cob-featured-toggle", function() {
                        var checkbox = $(this);
                        var term_id = checkbox.data("term-id");
                        var nonce = checkbox.data("nonce");
                        var is_featured = checkbox.is(":checked");

                        // AJAX request to update the term meta.
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "cob_toggle_featured_term",
                                term_id: term_id,
                                is_featured: is_featured,
                                _ajax_nonce: nonce
                            },
                            success: function(response) {
                                if (!response.success) {
                                    // Revert the checkbox on failure and show an alert.
                                    checkbox.prop("checked", !is_featured);
                                    alert("Failed to update status: " + response.data.message);
                                }
                            },
                            error: function() {
                                // Revert the checkbox on error and show an alert.
                                checkbox.prop("checked", !is_featured);
                                alert("An error occurred while updating the status.");
                            }
                        });
                    });
                });
            ' );
        }

        /**
         * Handle the AJAX request to toggle the featured status of a term.
         */
        public function toggle_featured_term() {
            $term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;

            // Verify the nonce for security.
            check_ajax_referer( 'cob_toggle_featured_nonce_' . $term_id );

            // Check if the current user has the required permissions.
            if ( ! current_user_can( 'manage_categories' ) ) {
                wp_send_json_error( array( 'message' => 'Permission denied.' ) );
            }

            $is_featured = isset( $_POST['is_featured'] ) && $_POST['is_featured'] === 'true';

            if ( $term_id > 0 ) {
                // Update or delete the term meta based on the toggle state.
                if ( $is_featured ) {
                    update_term_meta( $term_id, 'is_featured', true );
                } else {
                    delete_term_meta( $term_id, 'is_featured' );
                }
                wp_send_json_success( array( 'message' => 'Status updated.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Invalid term ID.' ) );
            }
        }
    }

    // Instantiate the class.
    new COB_Featured_Taxonomy();
}

