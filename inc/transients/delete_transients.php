<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Sadan_Transient_Manager' ) ) {

    class Sadan_Transient_Manager {

        /**
         * Constructor to hook all actions.
         */
        public function __construct() {
            add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
            add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 999 );
            add_action( 'init', [ $this, 'handle_quick_delete_action' ] );
        }

        /**
         * Adds the management page under the "Tools" menu.
         */
        public function add_admin_menu_page() {
            add_management_page(
                'إدارة الترنسينت',          // Page Title
                'إدارة الترنسينت',          // Menu Title
                'manage_options',          // Capability
                'sadan-transient-manager', // Menu Slug
                [ $this, 'render_management_page' ] // Callback function
            );
        }

        /**
         * Renders the HTML content for the transient management page.
         */
        public function render_management_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            // Handle form submissions for deleting transients
            if ( isset( $_POST['sadan_transient_nonce'] ) && wp_verify_nonce( $_POST['sadan_transient_nonce'], 'sadan_delete_transients' ) ) {
                if ( isset( $_POST['delete_all_transients'] ) ) {
                    $deleted_count = $this->delete_all_transients();
                    echo '<div class="notice notice-success is-dismissible"><p>تم حذف جميع الترنسينت بنجاح (عدد: ' . $deleted_count . ').</p></div>';
                } elseif ( ! empty( $_POST['transients_to_delete'] ) ) {
                    $deleted_count = 0;
                    foreach ( $_POST['transients_to_delete'] as $transient_name ) {
                        if ( delete_transient( sanitize_text_field( $transient_name ) ) ) {
                            $deleted_count++;
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>تم حذف الترنسينت المحددة بنجاح (عدد: ' . $deleted_count . ').</p></div>';
                }
            }

            $all_transients = $this->get_all_transients();
            ?>
            <div class="wrap">
                <h1>إدارة جميع الترنسينت</h1>
                <p>هذه الصفحة تعرض جميع الترنسينت المخزنة في قاعدة البيانات. يمكنك حذف ترنسينت محددة أو حذفها جميعاً.</p>
                <form method="post">
                    <?php wp_nonce_field( 'sadan_delete_transients', 'sadan_transient_nonce' ); ?>
                    <p>
                        <input type="submit" name="delete_selected" value="حذف المحدد" class="button">
                        <input type="submit" name="delete_all_transients" value="حذف الكل" class="button button-primary" onclick="return confirm('هل أنت متأكد أنك تريد حذف جميع الترنسينت؟');">
                    </p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>
                            <th scope="col" class="manage-column">اسم الترنسينت (Key)</th>
                            <th scope="col" class="manage-column">القيمة (Value)</th>
                            <th scope="col" class="manage-column">تاريخ الانتهاء</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ( empty( $all_transients ) ) : ?>
                            <tr>
                                <td colspan="4">لا يوجد أي ترنسينت حالياً.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $all_transients as $transient ) : ?>
                                <tr>
                                    <th scope="row" class="check-column"><input type="checkbox" name="transients_to_delete[]" value="<?php echo esc_attr( $transient['name'] ); ?>"></th>
                                    <td><strong><?php echo esc_html( $transient['name'] ); ?></strong></td>
                                    <td><div style="max-height: 100px; overflow-y: auto;"><?php echo esc_html( print_r( $transient['value'], true ) ); ?></div></td>
                                    <td><?php echo esc_html( $transient['expires'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php
        }

        /**
         * Fetches all transients from the database.
         */
        private function get_all_transients() {
            global $wpdb;
            $transients = [];
            $sql = "SELECT option_name FROM `{$wpdb->options}` WHERE `option_name` LIKE '\_transient\_%' AND `option_name` NOT LIKE '\_transient\_timeout\_%' ORDER BY `option_name`";
            $results = $wpdb->get_results( $sql );

            foreach ( $results as $result ) {
                $transient_name = str_replace( '_transient_', '', $result->option_name );
                $timeout_key = '_transient_timeout_' . $transient_name;
                $timeout = get_option( $timeout_key );

                $expires_in = $timeout ? human_time_diff( time(), $timeout ) : 'لا ينتهي';

                $transients[] = [
                    'name'    => $transient_name,
                    'value'   => get_transient( $transient_name ),
                    'expires' => $expires_in,
                ];
            }
            return $transients;
        }

        /**
         * Deletes all transients from the database.
         */
        private function delete_all_transients() {
            global $wpdb;
            $sql = "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '\_transient\_%' OR `option_name` LIKE '\_transient\_timeout\_%'";
            return $wpdb->query( $sql );
        }

        /**
         * Adds the "Delete Transients" menu to the admin bar.
         */
        public function add_admin_bar_menu( $wp_admin_bar ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $wp_admin_bar->add_node( [
                'id'    => 'sadan_transients_menu',
                'title' => '<span class="ab-icon dashicons-trash"></span>حذف الكاش (Transients)',
                'href'  => admin_url( 'tools.php?page=sadan-transient-manager' ),
            ] );

            $quick_delete_nonce = wp_create_nonce( 'sadan_quick_delete_nonce' );
            $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $quick_delete_url = add_query_arg( [
                'sadan_action' => 'quick_delete_transients',
                '_wpnonce' => $quick_delete_nonce,
            ], $current_url );

            $wp_admin_bar->add_node( [
                'id'     => 'sadan_quick_delete',
                'parent' => 'sadan_transients_menu',
                'title'  => 'حذف الكل وتحديث الصفحة',
                'href'   => $quick_delete_url,
                'meta'   => [ 'onclick' => 'return confirm("هل أنت متأكد؟ سيتم حذف جميع الترنسينت فوراً.");' ],
            ] );
        }

        /**
         * Handles the quick delete action from the admin bar link.
         */
        public function handle_quick_delete_action() {
            if ( isset( $_GET['sadan_action'] ) && $_GET['sadan_action'] === 'quick_delete_transients' && isset( $_GET['_wpnonce'] ) ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sadan_quick_delete_nonce' ) ) {
                    wp_die( 'Security check failed.' );
                }
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_die( 'Permission denied.' );
                }

                $this->delete_all_transients();

                // Redirect back to the same page without the action parameters
                wp_safe_redirect( remove_query_arg( [ 'sadan_action', '_wpnonce' ] ) );
                exit;
            }
        }
    }

    // Initialize the class
    new Sadan_Transient_Manager();
}
