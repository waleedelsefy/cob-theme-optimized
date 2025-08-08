<?php
/**
 * Metabox Manager Class
 *
 * Centralized handling for all theme custom meta boxes.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Metabox_Manager' ) ) {

    /**
     * Manages registration and data handling for custom meta boxes.
     */
    class COB_Metabox_Manager {

        /**
         * Hooks the necessary actions for meta box functionality.
         */
        public function __construct() {
            // Hooks for "Jobs" CPT
            add_action( 'add_meta_boxes_jobs', array( $this, 'register_job_qualifications_meta_box' ) );
            add_action( 'save_post_jobs', array( $this, 'save_job_qualifications_meta_box' ) );




            add_action( 'bulk_edit_custom_box', array( $this, 'add_bulk_edit_fields' ), 10, 2 );
            add_action( 'quick_edit_custom_box', array( $this, 'add_quick_edit_fields' ), 10, 2 );
        }

        /*
        |--------------------------------------------------------------------------
        | Job Qualifications Metabox
        |--------------------------------------------------------------------------
        */

        public function register_job_qualifications_meta_box() {
            add_meta_box(
                'job_qualifications_meta_box',          // ID
                __( 'Job Qualifications', 'cob_theme' ),// Title
                array( $this, 'render_job_qualifications_meta_box' ), // Callback
                'jobs',                                 // Post Type
                'normal',                               // Context
                'high'                                  // Priority
            );
        }

        public function render_job_qualifications_meta_box( $post ) {
            wp_nonce_field( 'save_job_qualifications', 'job_qualifications_nonce' );
            $qualifications = get_post_meta( $post->ID, 'job_qualifications', true );
            $qualifications = ( ! is_array( $qualifications ) || empty( $qualifications ) ) ? [''] : $qualifications;
            ?>
            <style>
                .job-qualification { display: flex; margin-bottom: 10px; align-items: center; }
                .job-qualification input[type="text"] { flex-grow: 1; margin-right: 10px; }
            </style>

            <div id="job_qualifications_container">
                <?php foreach ( $qualifications as $qualification ) : ?>
                    <div class="job-qualification">
                        <input type="text" name="job_qualifications[]" value="<?php echo esc_attr( $qualification ); ?>" class="widefat" />
                        <button type="button" class="button remove_qualification"><?php esc_html_e( 'Remove', 'cob_theme' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add_qualification" class="button button-primary"><?php esc_html_e( 'Add Qualification', 'cob_theme' ); ?></button>

            <?php // -- JAVASCRIPT ADDED HERE FOR RELIABILITY -- ?>
            <script>
                jQuery(document).ready(function($) {
                    // Cache container for performance
                    var container = $('#job_qualifications_container');

                    // Template for a new qualification row
                    var newRow = '<div class="job-qualification">' +
                        '<input type="text" name="job_qualifications[]" value="" class="widefat" />' +
                        '<button type="button" class="button remove_qualification"><?php echo esc_js( __( "Remove", "cob_theme" ) ); ?></button>' +
                        '</div>';

                    // Add a new qualification field
                    $('#add_qualification').on('click', function() {
                        container.append(newRow);
                    });

                    // Remove a qualification field using event delegation
                    container.on('click', '.remove_qualification', function() {
                        // Prevents removing the very last field
                        if ( container.find('.job-qualification').length > 1 ) {
                            $(this).closest('.job-qualification').remove();
                        } else {
                            // Clear the last field instead of removing it
                            $(this).closest('.job-qualification').find('input[type="text"]').val('');
                        }
                    });
                });
            </script>
            <?php
        }

        public function save_job_qualifications_meta_box( $post_id ) {
            if ( ! isset( $_POST['job_qualifications_nonce'] ) || ! wp_verify_nonce( $_POST['job_qualifications_nonce'], 'save_job_qualifications' ) ) return;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            if ( isset( $_POST['job_qualifications'] ) ) {
                $qualifications = array_map( 'sanitize_text_field', (array) $_POST['job_qualifications'] );
                $qualifications = array_filter( $qualifications, 'strlen' );
                if ( ! empty( $qualifications ) ) {
                    update_post_meta( $post_id, 'job_qualifications', $qualifications );
                } else {
                    delete_post_meta( $post_id, 'job_qualifications' );
                }
            } else {
                delete_post_meta( $post_id, 'job_qualifications' );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Parent Project (Compound) Metabox for Properties
        |--------------------------------------------------------------------------
        */

        public function register_parent_project_meta_box() {
            add_meta_box('propertie_project_metabox', __('Parent Project', 'cob_theme'), array($this, 'render_parent_project_metabox'), 'properties', 'side', 'high');
        }

        public function render_parent_project_metabox($post) {
            wp_nonce_field('cob_save_propertie_project', 'cob_propertie_project_nonce');
            $parent_project_id = $post->post_parent;
            $projects = get_posts(['post_type' => 'projects', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
            ?>
            <label for="cob_parent_project"><?php esc_html_e( 'Select Parent Project:', 'cob_theme' ); ?></label>
            <select name="cob_parent_project" id="cob_parent_project" style="width:100%;">
                <option value="0"><?php esc_html_e( '-- None --', 'cob_theme' ); ?></option>
                <?php foreach ( $projects as $project ) : ?>
                    <option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $parent_project_id, $project->ID ); ?>>
                        <?php echo esc_html( $project->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        public function render_parent_project_column($column, $post_id) {
            if ('parent_project' === $column) {
                $parent_id = wp_get_post_parent_id($post_id);
                echo $parent_id ? esc_html(get_the_title($parent_id)) : '—';
            }
        }

        public function add_bulk_edit_fields($column_name, $post_type) {
            if ('parent_project' !== $column_name || 'properties' !== $post_type) return;
            $this->render_project_dropdown('bulk_parent_project', __( '-- No Change --', 'cob_theme' ));
        }

        public function add_quick_edit_fields($column_name, $post_type) {
            if ('parent_project' !== $column_name || 'properties' !== $post_type) return;
            $this->render_project_dropdown('quick_parent_project', __( '-- No Change --', 'cob_theme' ));
        }

        private function render_project_dropdown($name, $no_change_text) {
            $projects = get_posts(['post_type' => 'projects', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
            ?>
            <fieldset class="inline-edit-col-right inline-edit-properties">
                <div class="inline-edit-col">
                    <label>
                        <span class="title"><?php esc_html_e( 'Parent Project', 'cob_theme' ); ?></span>
                        <select name="<?php echo esc_attr( $name ); ?>">
                            <option value=""><?php echo esc_html( $no_change_text ); ?></option>
                            <option value="0"><?php esc_html_e( '-- None --', 'cob_theme' ); ?></option>
                            <?php foreach ( $projects as $project ) : ?>
                                <option value="<?php echo esc_attr( $project->ID ); ?>"><?php echo esc_html( $project->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </fieldset>
            <?php
        }

        public function save_property_parent_meta($post_id) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            if (!current_user_can('edit_post', $post_id)) return;

            $parent_id = null;

            if (isset($_REQUEST['bulk_edit']) || (isset($_REQUEST['action']) && $_REQUEST['action'] === 'inline-save')) {
                if (isset($_REQUEST['bulk_parent_project']) && !empty($_REQUEST['bulk_parent_project'])) {
                    $parent_id = intval($_REQUEST['bulk_parent_project']);
                } elseif (isset($_REQUEST['quick_parent_project']) && !empty($_REQUEST['quick_parent_project'])) {
                    $parent_id = intval($_REQUEST['quick_parent_project']);
                }
            } elseif (isset($_POST['cob_propertie_project_nonce']) && wp_verify_nonce($_POST['cob_propertie_project_nonce'], 'cob_save_propertie_project')) {
                if (isset($_POST['cob_parent_project'])) {
                    $parent_id = intval($_POST['cob_parent_project']);
                }
            }

            if (isset($parent_id) && wp_get_post_parent_id($post_id) != $parent_id) {
                remove_action('save_post_properties', array($this, 'save_property_parent_meta'), 10);
                wp_update_post(['ID' => $post_id, 'post_parent' => $parent_id]);
                add_action('save_post_properties', array($this, 'save_property_parent_meta'), 10, 1);
            }
        }
    }
}
