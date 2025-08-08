<?php
/**
 * Zoom Integration Class
 *
 * Handles all theme functionality related to Zoom meetings, including
 * scheduling, API communication, and admin management.
 *
 * @package Capital_of_Business_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Zoom_Integration' ) ) {

    /**
     * Manages all Zoom integration logic.
     */
    final class COB_Zoom_Integration {

        private static $instance = null;

        private function __construct() {
            $this->setup_hooks();
        }

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function setup_hooks() {
            add_action( 'wp_ajax_schedule_zoom_meeting_ajax', array( $this, 'handle_scheduled_meeting_ajax' ) );
            add_action( 'wp_ajax_nopriv_schedule_zoom_meeting_ajax', array( $this, 'handle_scheduled_meeting_ajax' ) );
            add_action( 'wp_ajax_create_zoom_live_meeting_ajax', array( $this, 'handle_live_meeting_ajax' ) );
            add_action( 'wp_ajax_nopriv_create_zoom_live_meeting_ajax', array( $this, 'handle_live_meeting_ajax' ) );
            add_action( 'wp_ajax_cob_update_meeting_notes', array( $this, 'handle_update_notes_ajax' ) ); // AJAX for notes
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        }

        public static function activate() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zoom_meetings';
            $charset_collate = $wpdb->get_charset_collate();

            // UPDATED: Added status column
            $sql = "CREATE TABLE {$table_name} (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              meeting_id VARCHAR(64) NOT NULL,
              topic TEXT NOT NULL,
              start_time DATETIME NOT NULL,
              duration INT NOT NULL,
              join_url TEXT NOT NULL,
              password VARCHAR(32) NULL,
              participant_email VARCHAR(100) NOT NULL,
              participant_phone VARCHAR(50) NULL,
              notes TEXT NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'active',
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY meeting_id (meeting_id)
            ) {$charset_collate};";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        public function handle_scheduled_meeting_ajax() {
            $name  = sanitize_text_field( $_POST['zm_name'] ?? '' );
            $date  = sanitize_text_field( $_POST['zm_date'] ?? '' );
            $time  = sanitize_text_field( $_POST['zm_time'] ?? '' );
            $email = sanitize_email( $_POST['zm_participant_email'] ?? '' );
            $phone = sanitize_text_field( $_POST['zm_phone'] ?? '' );
            $notes = sanitize_textarea_field( $_POST['zm_notes'] ?? '' );

            if ( ! $name || ! $date || ! $time || ! is_email( $email ) ) {
                wp_send_json_error( [ 'message' => __( 'Invalid input', 'cob_theme' ) ] );
            }

            // IMPORTANT: Convert local time from form to UTC for Zoom API
            $local_time_str = "$date $time";
            $local_timezone = new DateTimeZone( wp_timezone_string() );
            $local_dt = new DateTime( $local_time_str, $local_timezone );
            $utc_dt = (clone $local_dt)->setTimezone(new DateTimeZone('UTC'));
            $start_time_for_api = $utc_dt->format( "Y-m-d\TH:i:00\Z" );

            $meeting_data = $this->api_request( [
                'topic'      => "Meeting with {$name}",
                'type'       => 2,
                'start_time' => $start_time_for_api,
                'duration'   => 60,
                'settings'   => [ 'join_before_host' => true, 'mute_upon_entry' => true ],
            ] );

            if ( empty( $meeting_data['id'] ) ) {
                wp_send_json_error( [ 'message' => __( 'Zoom API error', 'cob_theme' ) . (isset($meeting_data['message']) ? ': ' . $meeting_data['message'] : '') ] );
            }

            // Save the original local time to the DB
            $this->save_meeting_to_db( $meeting_data, $email, $phone, $notes, $local_dt->format('Y-m-d H:i:s') );
            $this->send_emails( $meeting_data, $email, $local_dt->format('Y-m-d H:i:s') );

            wp_send_json_success( $meeting_data );
        }

        public function handle_live_meeting_ajax() {
            $name  = sanitize_text_field( $_POST['zm_name'] ?? '' );
            $phone = sanitize_text_field( $_POST['zm_phone'] ?? '' );
            $notes = sanitize_textarea_field( $_POST['zm_notes'] ?? '' );

            if ( ! $name || ! $phone ) {
                wp_send_json_error( [ 'message' => __( 'Invalid input', 'cob_theme' ) ] );
            }

            $meeting_data = $this->api_request( [ 'topic' => "Live Call: {$name}", 'type' => 1, 'settings' => [ 'join_before_host' => true, 'mute_upon_entry' => true ] ] );

            if ( empty( $meeting_data['id'] ) ) {
                wp_send_json_error( [ 'message' => __( 'Zoom API error', 'cob_theme' ) . (isset($meeting_data['message']) ? ': ' . $meeting_data['message'] : '') ] );
            }

            $user_email = is_user_logged_in() ? wp_get_current_user()->user_email : 'guest@example.com';
            $meeting_start_time = current_time('mysql'); // Use site's current time for instant meetings

            $this->save_meeting_to_db( $meeting_data, $user_email, $phone, $notes, $meeting_start_time );
            $this->send_emails( $meeting_data, $user_email, $meeting_start_time );

            wp_send_json_success( $meeting_data );
        }

        public function handle_update_notes_ajax() {
            check_ajax_referer('cob_update_notes_nonce', 'nonce');

            $meeting_id = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;
            $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

            if ( ! $meeting_id || ! current_user_can('manage_options') ) {
                wp_send_json_error(['message' => 'Permission denied.']);
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'zoom_meetings';
            $result = $wpdb->update(
                $table_name,
                ['notes' => $notes],
                ['id' => $meeting_id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to save notes.']);
            }

            wp_send_json_success(['message' => 'Notes saved!']);
        }

        public function add_admin_menu() {
            add_menu_page( 'Zoom Meetings', 'Zoom Meetings', 'manage_options', 'cob_zoom_meetings', array( $this, 'render_meetings_page' ), 'dashicons-video-alt3', 26 );
        }

        public function render_meetings_page() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zoom_meetings';
            $rows = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC" );
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Zoom Meetings', 'cob_theme' ); ?></h1>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Topic', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Start Time (Site Time)', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Participant', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'cob_theme' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'cob_theme' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'No meetings found.', 'cob_theme' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) :
                            $is_canceled = ($row->status === 'canceled');
                            $join_button_html = '';

                            try {
                                $start_time_local = new DateTime( $row->start_time, new DateTimeZone( wp_timezone_string() ) );
                                $current_time_local = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );
                                $join_window_start = (clone $start_time_local)->modify('-15 minutes');
                                $join_window_end = (clone $start_time_local)->modify('+60 minutes');

                                if ( $is_canceled ) {
                                    $join_button_html = sprintf('<button class="button" disabled="disabled" title="%s">%s</button>', esc_attr__('Meeting is canceled', 'cob_theme'), esc_html__( 'Join', 'cob_theme' ));
                                } elseif ( $current_time_local >= $join_window_start && $current_time_local <= $join_window_end ) {
                                    $join_button_html = sprintf('<a href="%s" class="button button-primary" target="_blank">%s</a>', esc_url( $row->join_url ), esc_html__( 'Join', 'cob_theme' ));
                                } else {
                                    $join_button_html = sprintf('<button class="button" disabled="disabled" title="%s">%s</button>', esc_attr__('Meeting is not active', 'cob_theme'), esc_html__( 'Join', 'cob_theme' ));
                                }
                            } catch (Exception $e) {
                                $join_button_html = sprintf('<button class="button" disabled="disabled">%s</button>', esc_html__( 'Invalid Time', 'cob_theme' ));
                            }

                            $nonce_url = wp_nonce_url( add_query_arg( [ 'action' => 'cob_cancel_meeting', 'meeting_id' => $row->id ] ), 'cob_cancel_meeting_nonce_' . $row->id );
                            ?>
                            <tr class="<?php echo $is_canceled ? 'status-canceled' : ''; ?>">
                                <td><?php echo esc_html( $row->id ); ?></td>
                                <td><?php echo esc_html( $row->topic ); ?></td>
                                <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start_time_local->getTimestamp() ) ); ?></td>
                                <td><?php echo esc_html( $row->participant_email ); ?></td>
                                <td><?php echo esc_html( $row->participant_phone ); ?></td>
                                <td>
                                    <textarea class="zoom-notes" data-id="<?php echo esc_attr($row->id); ?>" rows="2" style="width:100%;" <?php if($is_canceled) echo 'disabled'; ?>><?php echo esc_textarea( $row->notes ); ?></textarea>
                                    <button class="button save-notes-btn" data-id="<?php echo esc_attr($row->id); ?>" <?php if($is_canceled) echo 'disabled'; ?>><?php _e('Save', 'cob_theme'); ?></button>
                                    <span class="notes-feedback" data-id="<?php echo esc_attr($row->id); ?>"></span>
                                </td>
                                <td><?php echo esc_html( ucfirst($row->status) ); ?></td>
                                <td>
                                    <?php echo $join_button_html; ?>
                                    <?php if ( !$is_canceled ) : ?>
                                        <a href="<?php echo esc_url( $nonce_url ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'cob_theme' ); ?>');"><?php esc_html_e( 'Cancel', 'cob_theme' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <style>.status-canceled { opacity: 0.6; background: #fef2f2 !important; } .notes-feedback { font-style: italic; display: block; margin-top: 4px; }</style>
            <script>
                jQuery(document).ready(function($) {
                    $('.save-notes-btn').on('click', function() {
                        var btn = $(this);
                        var meetingId = btn.data('id');
                        var notes = $('textarea[data-id="' + meetingId + '"]').val();
                        var feedback = $('.notes-feedback[data-id="' + meetingId + '"]');

                        btn.prop('disabled', true);
                        feedback.text('Saving...').css('color', 'black');

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'cob_update_meeting_notes',
                                nonce: '<?php echo wp_create_nonce('cob_update_notes_nonce'); ?>',
                                meeting_id: meetingId,
                                notes: notes
                            },
                            success: function(response) {
                                if(response.success) {
                                    feedback.text('Saved!').css('color', 'green');
                                } else {
                                    feedback.text('Error.').css('color', 'red');
                                }
                                setTimeout(function() { feedback.text(''); }, 2000);
                            },
                            error: function() {
                                feedback.text('Request failed.').css('color', 'red');
                                setTimeout(function() { feedback.text(''); }, 2000);
                            },
                            complete: function() {
                                btn.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <?php
        }

        public function handle_admin_actions() {
            if ( isset( $_GET['action'] ) && $_GET['action'] === 'cob_cancel_meeting' && isset( $_GET['meeting_id'] ) ) {
                $meeting_id = intval( $_GET['meeting_id'] );
                check_admin_referer( 'cob_cancel_meeting_nonce_' . $meeting_id );
                $this->cancel_zoom_meeting_by_id( $meeting_id );
                add_action('admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Meeting canceled successfully.', 'cob_theme') . '</p></div>'; });
            }
        }

        private function api_request( $body ) { /* ... same as before ... */
            $token = $this->get_oauth_token(); if ( ! $token ) return [];
            $response = wp_remote_post( "https://api.zoom.us/v2/users/me/meetings", [ 'headers' => [ 'Authorization' => "Bearer {$token}", 'Content-Type'  => 'application/json' ], 'body' => wp_json_encode( $body ), 'timeout' => 15, ] );
            if ( is_wp_error( $response ) ) { error_log( 'Zoom API Request Error: ' . $response->get_error_message() ); return []; }
            return json_decode( wp_remote_retrieve_body( $response ), true );
        }

        private function get_oauth_token() { /* ... same as before ... */
            $transient_key = 'cob_zoom_oauth_token'; if ( $token = get_transient( $transient_key ) ) return $token;
            $client_id = 'CxESA4jKSxiXkp_m7Yp_zg'; $client_secret = 'krg6rhGRSDTV2FeljUZHHByBp26Gib6a'; $account_id = '8JY4L6HSRciV1ezn9Vtxow';
            $url = "https://zoom.us/oauth/token?grant_type=account_credentials&account_id={$account_id}";
            $response = wp_remote_post( $url, [ 'headers' => [ 'Authorization' => 'Basic ' . base64_encode( "{$client_id}:{$client_secret}" ) ] ] );
            if ( is_wp_error( $response ) ) { error_log( 'Zoom Token Request Error: ' . $response->get_error_message() ); return false; }
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( empty( $body['access_token'] ) ) { error_log( 'Zoom Token Error: Failed to obtain token. Response: ' . print_r( $body, true ) ); return false; }
            set_transient( $transient_key, $body['access_token'], 50 * MINUTE_IN_SECONDS );
            return $body['access_token'];
        }

        private function send_emails( $meeting_data, $participant_email, $start_time_str ) {
            $join_url = esc_url( $meeting_data['join_url'] );
            $password = esc_html( $meeting_data['password'] );
            $topic    = esc_html( $meeting_data['topic'] );
            $subject  = __( 'Your Zoom Meeting Details', 'cob_theme' );
            $headers  = [ 'Content-Type: text/html; charset=UTF-8' ];
            $host_email = get_option( 'admin_email' );

            // Format time according to site settings
            $start_time_obj = new DateTime( $start_time_str, new DateTimeZone( wp_timezone_string() ) );
            $formatted_time = wp_date( get_option('date_format') . ' ' . get_option('time_format'), $start_time_obj->getTimestamp() );

            $message = "<div style='font-family: sans-serif; direction: ltr; text-align: left;'><h2>{$topic}</h2><p>" . sprintf(esc_html__('Time: %s (%s)', 'cob_theme'), $formatted_time, wp_timezone_string() ) . "</p><p>" . __( 'Join URL:', 'cob_theme' ) . " <a href='{$join_url}'>{$join_url}</a></p><p>" . __( 'Password:', 'cob_theme' ) . " <strong>{$password}</strong></p></div>";
            wp_mail( $participant_email, $subject, $message, $headers );
            if( $participant_email !== $host_email ) {
                wp_mail( $host_email, $subject, $message, $headers );
            }
        }

        private function save_meeting_to_db( $meeting_data, $participant_email, $participant_phone = '', $notes = '', $start_time ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zoom_meetings';
            $wpdb->insert( $table_name, [
                'meeting_id'        => $meeting_data['id'],
                'topic'             => $meeting_data['topic'],
                'start_time'        => $start_time,
                'duration'          => $meeting_data['duration'] ?? 60,
                'join_url'          => $meeting_data['join_url'],
                'password'          => $meeting_data['password'],
                'participant_email' => $participant_email,
                'participant_phone' => $participant_phone,
                'notes'             => $notes,
                'status'            => 'active', // Set initial status
            ],
                [ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
        }

        private function cancel_zoom_meeting_by_id( $meeting_id_db ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zoom_meetings';

            // Get the Zoom meeting ID from our database ID
            $zoom_meeting_id = $wpdb->get_var($wpdb->prepare("SELECT meeting_id FROM {$table_name} WHERE id = %d", $meeting_id_db));

            if ($zoom_meeting_id) {
                $token = $this->get_oauth_token();
                if ($token) {
                    wp_remote_request( "https://api.zoom.us/v2/meetings/{$zoom_meeting_id}", [
                        'method'  => 'DELETE',
                        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
                    ] );
                }
                // UPDATED: Change status to 'canceled' instead of deleting
                $wpdb->update( $table_name, ['status' => 'canceled'], ['id' => $meeting_id_db], ['%s'], ['%d'] );
            }
        }
    }
}
