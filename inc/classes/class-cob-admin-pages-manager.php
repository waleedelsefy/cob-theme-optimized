<?php
/**
 * Admin Pages Manager Class
 *
 * Handles the creation and rendering of custom admin pages for the theme.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Admin_Pages_Manager' ) ) {

    class COB_Admin_Pages_Manager {

        /**
         * Hooks the necessary actions for creating admin pages.
         */
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
        }

        /**
         * Registers all custom admin pages for the theme.
         */
        public function register_admin_pages() {
            add_menu_page(
                __( 'Job Applicants', 'cob_theme' ),
                __( 'Job Applicants', 'cob_theme' ),
                'manage_options',
                'job-applicants',
                array( $this, 'render_job_applicants_page' ),
                'dashicons-id-alt2',
                26
            );
        }

        /**
         * Callback function to render the content of the Job Applicants page.
         */
        public function render_job_applicants_page() {
            // Instantiate our custom list table.
            $applicants_list_table = new COB_Job_Applicants_List_Table();
            // Prepare the items for display.
            $applicants_list_table->prepare_items();
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php esc_html_e( 'Job Applicants', 'cob_theme' ); ?></h1>

                <?php $applicants_list_table->views(); ?>

                <form method="post">
                    <?php
                    // Required for bulk actions and other table functionality.
                    $applicants_list_table->search_box( __( 'Search Applicants', 'cob_theme' ), 'applicant_search' );
                    $applicants_list_table->display();
                    ?>
                </form>
            </div>
            <?php
        }
    }
}
