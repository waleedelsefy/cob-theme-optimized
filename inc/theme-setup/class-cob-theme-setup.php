<?php
/**
 * Theme Setup Class
 *
 * Handles core theme setup, features, menus, and basic asset registration
 * like the preloader.
 *
 * @package Capital_of_Business_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Theme_Setup' ) ) {

    /**
     * Manages the core setup functionalities of the theme.
     */
    final class COB_Theme_Setup {

        /**
         * The single instance of the class.
         * @var COB_Theme_Setup
         */
        private static $instance = null;

        /**
         * Ensures only one instance of the class is loaded.
         * @return COB_Theme_Setup - Main instance.
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor to set up all hooks.
         */
        private function __construct() {
            // Include necessary files before setting up hooks that depend on them.
            $this->includes();

            // Register all actions and filters.
            $this->setup_hooks();
        }

        /**
         * Include required files for theme setup.
         */
        private function includes() {
            // Include the custom navigation walker.
            require_once get_template_directory() . '/inc/theme-setup/class-cob-walker-nav-menu.php';
        }

        /**
         * Setup all theme hooks.
         */
        private function setup_hooks() {
            add_action( 'after_setup_theme', array( $this, 'theme_setup' ) );
            add_action( 'after_setup_theme', array( $this, 'register_menus' ) );
            add_action( 'wp_body_open', array( $this, 'print_preloader' ), 5 );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preloader_script' ) );
        }

        /**
         * Core theme setup functionalities.
         */
        public function theme_setup() {
            // Enable support for various WordPress features.
            add_theme_support('title-tag');
            add_theme_support('post-thumbnails');
            add_theme_support('automatic-feed-links');
            add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
            add_theme_support('custom-logo');
            add_theme_support('customize-selective-refresh-widgets');
            add_theme_support('widgets');

            // Load theme textdomain for translation.
            load_theme_textdomain('cob_theme', get_template_directory() . '/languages');
        }


        public function print_preloader() {
            // You can replace this with a more advanced preloader if needed.
            ?>
            <div id="cob-preloader">
                <img src="<?php echo get_template_directory_uri(); ?>/assets/images/preloader.gif" alt="Loading...">
            </div>
            <?php
        }

        /**
         * Enqueues the script that will hide the preloader.
         */
        public function enqueue_preloader_script() {
            // This script should be very lightweight.
            wp_enqueue_script(
                'cob-preloader',
                get_template_directory_uri() . '/assets/js/preloader.js',
                array(),
                '1.0',
                true
            );
        }
    }
}
