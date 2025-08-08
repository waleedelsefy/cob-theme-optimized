<?php
/**
 * Post Type and Taxonomy Manager Class
 *
 * Centralized handling for all Custom Post Type and Taxonomy registrations.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Post_Type_Manager' ) ) {

    /**
     * Manages all CPT and Taxonomy registrations for the theme.
     */
    class COB_Post_Type_Manager {

        /**
         * Hooks the registration methods and other actions.
         */
        public function __construct() {
            add_action( 'init', array( $this, 'register_all' ) );
            add_action( 'template_redirect', array( $this, 'update_property_views' ) );
            add_action( 'template_redirect', array( $this, 'block_cpt_redirects' ) );
        }

        /**
         * A central method to run all registrations.
         */
        public function register_all() {
            $this->register_post_types();
            $this->register_taxonomies();
        }

        /**
         * Registers all custom post types for the theme.
         */
        public function register_post_types() {
            // Properties CPT
            register_post_type( 'properties', [
                'labels' => $this->create_labels('Property', 'Properties'),
                'public' => true,
                'has_archive' => true,
                'menu_icon' => 'dashicons-building',
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes'],
                'rewrite' => false,
                'show_in_rest' => true,
                'hierarchical' => true,
            ]);

            // Factory CPT
            register_post_type( 'factory', [
                'labels' => $this->create_labels('Factory', 'Factories'),
                'public' => true,
                'has_archive' => true,
                'menu_icon' => 'dashicons-store',
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
                'rewrite' => ['slug' => 'factories'],
                'show_in_rest' => true,
            ]);

            // Lands CPT
            register_post_type( 'lands', [
                'labels' => $this->create_labels('Land', 'Lands'),
                'public' => true,
                'has_archive' => true,
                'menu_icon' => 'dashicons-palmtree',
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
                'rewrite' => ['slug' => 'lands'],
                'show_in_rest' => true,
            ]);

            // Sliders CPT (private)
            register_post_type('sliders', [
                'labels' => $this->create_labels('Slider', 'Sliders'),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_icon' => 'dashicons-slides',
                'supports' => ['title', 'editor', 'thumbnail'],
                'exclude_from_search' => true,
                'publicly_queryable' => false,
            ]);

            // Services CPT
            register_post_type('services', [
                'labels' => $this->create_labels('Service', 'Services'),
                'public' => true,
                'hierarchical' => true,
                'show_ui' => true,
                'rewrite' => ['slug' => 'services'],
                'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
                'show_in_rest' => true,
            ]);

            // Jobs CPT
            register_post_type('jobs', [
                'labels' => $this->create_labels('Job', 'Jobs', ['archives' => __( 'Job Archives', 'cob_theme' )]),
                'description' => __('Job listings for the site', 'cob_theme'),
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_position' => 25,
                'menu_icon' => 'dashicons-businessman',
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
                'rewrite' => ['slug' => 'jobs'],
            ]);
        }

        /**
         * Registers all custom taxonomies for the theme.
         */
        public function register_taxonomies() {
            $default_args = [
                'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true,
                'query_var' => true, 'rewrite' => true, 'show_in_rest' => true,
            ];

            register_taxonomy( 'compound', ['properties'], array_merge($default_args, [
                'labels' => $this->create_labels('Compound', 'Compounds'),
                'rewrite' => false
            ]));

            register_taxonomy( 'city', ['lands', 'properties', 'factory', 'post'], array_merge($default_args, [
                'labels' => $this->create_labels('City', 'Cities'),
                'rewrite' => ['slug' => 'city']
            ]));

            register_taxonomy( 'developer', ['lands', 'properties', 'factory', 'post'], array_merge($default_args, [
                'labels' => $this->create_labels('Developer', 'Developers'),
                'rewrite' => ['slug' => 'developer']
            ]));

            register_taxonomy( 'finishing', ['properties'], array_merge($default_args, [
                'labels' => $this->create_labels('Finishing Type', 'Finishing Types'),
                'rewrite' => ['slug' => 'finishing']
            ]));

            register_taxonomy( 'type', ['lands', 'properties', 'post'], array_merge($default_args, [
                'labels' => $this->create_labels('Type', 'Types'),
                'rewrite' => ['slug' => 'type']
            ]));

            register_taxonomy('job_tag', ['jobs'], [
                'labels' => $this->create_labels('Job Tag', 'Job Tags', ['popular_items' => __( 'Popular Job Tags', 'cob_theme' )]),
                'hierarchical' => false,
                'show_ui' => true,
                'show_admin_column' => true,
                'rewrite' => ['slug' => 'job-tag'],
            ]);
        }

        /**
         * Updates the view count for properties.
         */
        public function update_property_views() {
            if ( ! is_admin() && is_singular( 'properties' ) ) {
                $post_id = get_queried_object_id();
                if ( ! $post_id ) return;
                $cookie_name = 'properties_viewed_' . $post_id;
                if ( isset( $_COOKIE[ $cookie_name ] ) ) return;
                $views = (int) get_post_meta( $post_id, 'properties_views', true );
                update_post_meta( $post_id, 'properties_views', $views + 1 );
                setcookie( $cookie_name, 1, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
            }
        }

        /**
         * Redirects single posts of specific private CPTs to the homepage.
         */
        public function block_cpt_redirects() {
            if ( is_singular(['sliders', 'services']) ) {
                wp_redirect( home_url(), 301 );
                exit;
            }
        }

        /**
         * Helper function to generate standard CPT/Taxonomy labels.
         */
        private function create_labels($singular, $plural, $overrides = []) {
            $labels = [
                'name' => _x($plural, 'post type general name', 'cob_theme'),
                'singular_name' => _x($singular, 'post type singular name', 'cob_theme'),
                'menu_name' => __($plural, 'cob_theme'),
                'name_admin_bar' => __($singular, 'cob_theme'),
                'add_new' => __('Add New', 'cob_theme'),
                'add_new_item' => __('Add New ' . $singular, 'cob_theme'),
                'new_item' => __('New ' . $singular, 'cob_theme'),
                'edit_item' => __('Edit ' . $singular, 'cob_theme'),
                'view_item' => __('View ' . $singular, 'cob_theme'),
                'all_items' => __('All ' . $plural, 'cob_theme'),
                'search_items' => __('Search ' . $plural, 'cob_theme'),
                'parent_item_colon' => __('Parent ' . $singular . ':', 'cob_theme'),
                'not_found' => __('No ' . strtolower($plural) . ' found.', 'cob_theme'),
                'not_found_in_trash' => __('No ' . strtolower($plural) . ' found in Trash.', 'cob_theme'),
            ];
            return array_merge($labels, $overrides);
        }
    }
}
