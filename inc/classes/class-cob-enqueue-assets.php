<?php
/**
 * Asset Enqueueing Class
 *
 * Handles all theme style and script enqueuing in an organized,
 * object-oriented way.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Enqueue_Assets' ) ) {

    /**
     * Manages the registration and loading of the theme's assets.
     */
    class COB_Enqueue_Assets {

        /**
         * Adds the necessary WordPress hooks for enqueuing assets.
         */
        public function __construct() {
            add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'remove_unwanted_styles' ), 100 );
        }

        /**
         * Main function to register all frontend assets.
         */
        public function register_frontend_assets() {
            $this->enqueue_styles();
            $this->enqueue_scripts();
            $this->enqueue_conditional_frontend_assets();
        }

        /**
         * Registers assets for the admin area.
         */
        public function register_admin_assets( $hook ) {
            global $post;

            if ( ( $hook == 'post-new.php' || $hook == 'post.php' ) && isset($post->post_type) && 'jobs' === $post->post_type ) {
                $js_path = '/admin/js/job-metabox-admin.js';
                wp_enqueue_script(
                    'cob-job-metabox-admin',
                    get_template_directory_uri() . $js_path,
                    ['jquery'],
                    cob_get_asset_version( get_template_directory() . $js_path ),
                    true
                );
            }
        }

        /**
         * Enqueues all the theme's global stylesheets.
         */
        private function enqueue_styles() {
            $theme_uri = get_stylesheet_directory_uri();
            $theme_dir = get_stylesheet_directory();

            wp_enqueue_style( 'cob-normalize', $theme_uri . '/assets/css/normalize.css', array(), '8.0.1' );
            wp_enqueue_style( 'cob-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11.0.0' );
            wp_enqueue_style( 'cob-fontawesome', $theme_uri . '/assets/css/fonts/fontawesome/css/all.css', array(), '6.4.2' );
            wp_enqueue_style( 'cob-google-fonts', 'https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Cairo:wght@200..1000&display=swap', array(), null );
            wp_enqueue_style( 'cob-header', $theme_uri . '/assets/css/header.css', array(), cob_get_asset_version( $theme_dir . '/assets/css/header.css' ) );
            wp_enqueue_style( 'cob-footer', $theme_uri . '/assets/css/footer.css', array(), cob_get_asset_version( $theme_dir . '/assets/css/footer.css' ) );
            if ( is_rtl() ) {
                wp_enqueue_style( 'cob-rtl', $theme_uri . '/assets/css/rtl.css', array(), cob_get_asset_version( $theme_dir . '/assets/css/rtl.css' ) );
            }
            wp_enqueue_style( 'cob-style', get_stylesheet_uri(), array(), cob_get_asset_version( $theme_dir . '/style.css' ) );

            if ( ! is_robots() ) {
                $css_files = [
                    'animate' => 'assets/css/animate.css', 'contact' => 'assets/css/contact.css', 'areas' => 'assets/css/areas.css',
                    'developers' => 'assets/css/motawren-det.css', 'article-name' => 'assets/css/article-name.css', 'articles' => 'assets/css/articels.css',
                    'factorys' => 'assets/css/factorys.css', 'developer' => 'assets/css/motawren.css', 'projects' => 'assets/css/projects.css',
                    'services' => 'assets/css/services.css', 'factory-det' => 'assets/css/factory-det.css', 'flat-det' => 'assets/css/flat-det.css',
                    'hiring' => 'assets/css/hiring.css', 'we' => 'assets/css/we.css', 'city' => 'assets/css/city.css', 'home' => 'assets/css/home.css',
                ];
                foreach ( $css_files as $handle => $path ) {
                    wp_enqueue_style( "cob-{$handle}", $theme_uri . '/' . $path, array(), cob_get_asset_version( $theme_dir . '/' . $path ) );
                }
            }
        }

        /**
         * Enqueues all the theme's global JavaScript files.
         */
        private function enqueue_scripts() {
            $theme_uri = get_stylesheet_directory_uri();

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'cob-swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11.0.0', true );
            wp_enqueue_script( 'cob-lazyload', 'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js', array(), '5.3.2', true );
            wp_enqueue_script( 'cob-fontawesome', $theme_uri . '/assets/css/fonts/fontawesome/js/all.js', array(), null, true );
        }

        /**
         * Enqueues assets only on specific frontend pages.
         */
        private function enqueue_conditional_frontend_assets() {
            $theme_uri = get_template_directory_uri();
            $theme_dir = get_template_directory();

            // --- Force load COB Search plugin assets ONLY on the homepage. ---
            // This ensures the AJAX search form works correctly when the shortcode is embedded in the homepage template.
            if ( is_front_page() ) {
                // The COB Search plugin already registers these handles. We just need to enqueue them here.
                wp_enqueue_style('cob-frontend-style');
                wp_enqueue_script('cob-instantsearch-script');
            }


            if ( is_singular( 'properties' ) ) {
                wp_enqueue_script( 'cob-similar-units-loader', $theme_uri . '/assets/js/similar-units-loader.js', array( 'jquery' ), '1.0.2', true );
                $ajax_data = [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cob_similar_units_nonce' ),
                ];
                if ( function_exists( 'pll_current_language' ) ) {
                    $ajax_data['lang'] = pll_current_language();
                }
                wp_localize_script( 'cob-similar-units-loader', 'cob_ajax_obj', $ajax_data );
            }

            if ( is_page_template( 'page-templates/hiring-page.php' ) || is_singular('jobs') ) {
                $js_path = '/assets/js/job-application.js';
                wp_enqueue_script(
                    'cob-job-application',
                    $theme_uri . $js_path,
                    ['jquery'],
                    cob_get_asset_version( $theme_dir . $js_path ),
                    true
                );

                wp_localize_script( 'cob-job-application', 'cobJobAjax', [
                    'ajax_url'        => admin_url( 'admin-ajax.php' ),
                    'nonce'           => wp_create_nonce( 'submit_job_application' ),
                    'text_sending'    => __( 'Sending...', 'cob_theme' ),
                    'text_error'      => __( 'An unknown error occurred. Please try again.', 'cob_theme' ),
                    'text_choose'     => __( 'Choose file', 'cob_theme' ),
                    'text_drag'       => __( 'or drag it here', 'cob_theme' ),
                ]);
            }

            if ( is_singular('properties') || is_tax(array('compound', 'city', 'developer')) ) {
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
            }

            if ( is_page_template('page-templates/factories-page.php') || is_post_type_archive('factory') ) {
                wp_enqueue_script( 'cob-factory-ajax', $theme_uri . '/assets/js/factory-ajax.js', [ 'jquery' ], '1.0', true );
                $city = ( get_queried_object() && property_exists(get_queried_object(), 'slug') ) ? get_queried_object()->slug : '';
                wp_localize_script( 'cob-factory-ajax', 'cobFactory', [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cob_factory_nonce' ),
                    'city'     => $city,
                ]);
            }

            if ( is_tax('developer') || is_page('developers') ) {
                wp_enqueue_script( 'cob-developer-load-more', $theme_uri . '/assets/js/developer-load-more.js', array(), '1.0', true );
                wp_localize_script( 'cob-developer-load-more', 'cob_ajax_object', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cob_developer_nonce' )
                ));
            }
        }

        /**
         * Removes unwanted styles from other plugins.
         */
        public function remove_unwanted_styles() {
            // Example: wp_dequeue_style( 'unwanted-plugin-style' );
        }
    }
}
