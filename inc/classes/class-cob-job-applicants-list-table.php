<?php
/**
 * Job Applicants List Table Class
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class COB_Job_Applicants_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Job Applicant', 'cob_theme' ),
            'plural'   => __( 'Job Applicants', 'cob_theme' ),
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'cb'                 => '<input type="checkbox" />',
            'full_name'          => __( 'Full Name', 'cob_theme' ),
            'job_title'          => __( 'Applied For', 'cob_theme' ),
            'email'              => __( 'Email', 'cob_theme' ),
            'phone'              => __( 'Phone', 'cob_theme' ),
            'resume'             => __( 'Resume', 'cob_theme' ),
            'status'             => __( 'Status', 'cob_theme' ),
            'submission_date'    => __( 'Submission Date', 'cob_theme' ),
        ];
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] );
    }

    public function column_full_name( $item ) {
        $actions = [
            'edit_status' => sprintf( '<a href="#" class="edit-status" data-id="%d">%s</a>', $item['id'], __( 'Update Status', 'cob_theme' ) ),
            'delete'      => sprintf( '<a href="?page=%s&action=delete&applicant_id=%d&_wpnonce=%s">%s</a>', $_REQUEST['page'], $item['id'], wp_create_nonce('cob_delete_applicant'), __( 'Delete', 'cob_theme' ) ),
        ];
        return sprintf( '<strong>%s</strong>%s', $item['full_name'], $this->row_actions( $actions ) );
    }

    public function column_job_title( $item ) {
        $job_title = get_the_title( $item['job_id'] );
        return $job_title ? esc_html($job_title) : __('Unknown Job', 'cob_theme');
    }

    public function column_resume( $item ) {
        if ( ! empty( $item['resume'] ) ) {
            return sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $item['resume'] ), __( 'Download', 'cob_theme' ) );
        }
        return __( 'N/A', 'cob_theme' );
    }

    public function column_status($item) {
        return $this->get_status_dropdown($item['id'], $item['status']);
    }

    private function get_status_dropdown($applicant_id, $current_status) {
        ob_start();
        ?>
        <form method="post" class="status-form">
            <input type="hidden" name="applicant_id" value="<?php echo intval($applicant_id); ?>">
            <?php wp_nonce_field('update_job_applicant_status_' . $applicant_id); ?>
            <select name="status">
                <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'cob_theme'); ?></option>
                <option value="approved" <?php selected($current_status, 'approved'); ?>><?php esc_html_e('Approved', 'cob_theme'); ?></option>
                <option value="rejected" <?php selected($current_status, 'rejected'); ?>><?php esc_html_e('Rejected', 'cob_theme'); ?></option>
            </select>
            <button type="submit" name="update_status" class="button button-secondary button-small"><?php esc_html_e('Update', 'cob_theme'); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id'] );
    }

    public function get_bulk_actions() {
        return [
            'bulk-delete' => __( 'Delete', 'cob_theme' ),
        ];
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );
            if ( ! wp_verify_nonce( $nonce, 'cob_delete_applicant' ) ) {
                die( 'Go get a life script kiddies' );
            }
            $this->delete_applicant( absint( $_GET['applicant_id'] ) );
        }

        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' ) ) {
            $delete_ids = esc_sql( $_POST['bulk-delete'] );
            foreach ( $delete_ids as $id ) {
                $this->delete_applicant( $id );
            }
        }
    }

    private function delete_applicant($id) {
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}job_applications", [ 'ID' => $id ], [ '%d' ] );
    }

    private function handle_status_update() {
        if (isset($_POST['update_status'], $_POST['applicant_id'])) {
            $applicant_id = absint($_POST['applicant_id']);
            check_admin_referer('update_job_applicant_status_' . $applicant_id);
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}job_applications",
                ['status' => sanitize_text_field($_POST['status'])],
                ['id' => $applicant_id],
                ['%s'],
                ['%d']
            );
        }
    }

    public function prepare_items() {
        global $wpdb;

        $this->handle_status_update();
        $this->process_bulk_action();

        $table_name = $wpdb->prefix . 'job_applications';
        $per_page = 15;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ] );

        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table_name ORDER BY submission_date DESC LIMIT %d OFFSET %d", $per_page, $offset ),
            ARRAY_A
        );
    }
}
