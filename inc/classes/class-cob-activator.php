<?php
/**
 * Theme Activation Class
 *
 * Handles tasks that should run only once, upon theme activation.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Activator' ) ) {

    class COB_Activator {

        /**
         * Main activation hook.
         */
        public static function activate() {
            self::create_job_applications_table();
            flush_rewrite_rules();
        }

        /**
         * Creates the custom database table for job applications.
         */
        private static function create_job_applications_table() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'job_applications';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                job_id bigint(20) UNSIGNED NOT NULL,
                full_name tinytext NOT NULL,
                phone varchar(55) DEFAULT '' NOT NULL,
                email varchar(100) DEFAULT '' NOT NULL,
                experience_years varchar(55) DEFAULT '' NOT NULL,
                address text DEFAULT '' NOT NULL,
                resume varchar(255) DEFAULT '' NOT NULL,
                additional_details text DEFAULT '' NOT NULL,
                status varchar(20) DEFAULT 'pending' NOT NULL,
                submission_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }
}
