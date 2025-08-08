<?php
/**
 * Permalink and Rewrite Rule Manager Class
 *
 * Handles all custom permalink structures and rewrite rules for the theme.
 * This version uses a fixed prefix for hierarchical URLs to ensure stability.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'COB_Rewrite_Manager' ) ) {

    class COB_Rewrite_Manager {

        /**
         * The base prefix for all hierarchical URLs.
         * Change this value to change the base of your real estate URLs.
         * @var string
         */
        private $base_prefix = 'city'; // e.g., 'city', 'listings', etc.

        public function __construct() {
            add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
            add_action( 'init', array( $this, 'add_rewrite_rules' ) );
            add_action( 'parse_request', array( $this, 'parse_hierarchical_request' ) );
            add_filter( 'post_type_link', array( $this, 'filter_property_permalink' ), 10, 2 );
            add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 3 );
            add_action( 'after_switch_theme', array( $this, 'flush_rules' ) );
        }

        public function flush_rules() {
            if(class_exists('COB_Post_Type_Manager')) {
                $cpt_manager = new COB_Post_Type_Manager();
                $cpt_manager->register_all();
            }
            $this->add_rewrite_rules();
            flush_rewrite_rules();
        }

        public function add_query_vars( $vars ) {
            $vars[] = 'cob_part1'; // City
            $vars[] = 'cob_part2'; // Compound
            $vars[] = 'cob_part3'; // Property
            return $vars;
        }

        public function add_rewrite_rules() {
            // Rule for prefixed taxonomies (e.g., Developer) that are not part of the main hierarchy.
            add_rewrite_rule( '^(?:([^/]{2})/)?developer/([^/]+)/?$', 'index.php?taxonomy=developer&term=$matches[2]&lang=$matches[1]', 'top' );

            // Generic rules for the prefixed hierarchical structure.
            add_rewrite_rule( '^(?:([^/]{2})/)?' . $this->base_prefix . '/([^/]+)/([^/]+)/([^/]+)/?$', 'index.php?lang=$matches[1]&cob_part1=$matches[2]&cob_part2=$matches[3]&cob_part3=$matches[4]', 'top' );
            add_rewrite_rule( '^(?:([^/]{2})/)?' . $this->base_prefix . '/([^/]+)/([^/]+)/?$', 'index.php?lang=$matches[1]&cob_part1=$matches[2]&cob_part2=$matches[3]', 'top' );
            add_rewrite_rule( '^(?:([^/]{2})/)?' . $this->base_prefix . '/([^/]+)/?$', 'index.php?lang=$matches[1]&cob_part1=$matches[2]', 'top' );
        }

        public function parse_hierarchical_request( &$wp ) {
            if ( ! isset( $wp->query_vars['cob_part1'] ) ) {
                return;
            }

            $part1 = $wp->query_vars['cob_part1'];
            $part2 = $wp->query_vars['cob_part2'] ?? null;
            $part3 = $wp->query_vars['cob_part3'] ?? null;
            $lang  = $wp->query_vars['lang'] ?? null;

            $is_custom_url = false;

            if ( $part1 && $part2 && $part3 ) { // Property: /properties/city/compound/property
                $property = get_page_by_path( $part3, OBJECT, 'properties' );
                if ( $property && has_term( $part1, 'city', $property ) && has_term( $part2, 'compound', $property ) ) {
                    $this->set_query_vars( $wp, ['post_type' => 'properties', 'name' => $part3, 'lang' => $lang]);
                    $is_custom_url = true;
                }
            } elseif ( $part1 && $part2 ) { // Compound: /properties/city/compound
                $city_term = get_term_by( 'slug', $part1, 'city' );
                $compound_term = get_term_by( 'slug', $part2, 'compound' );
                if ( $city_term && $compound_term && get_term_meta( $compound_term->term_id, 'compound_city', true ) == $city_term->term_id ) {
                    $this->set_query_vars( $wp, ['taxonomy' => 'compound', 'term' => $part2, 'lang' => $lang]);
                    $is_custom_url = true;
                }
            } elseif ( $part1 ) { // City: /properties/city
                $city_term = get_term_by( 'slug', $part1, 'city' );
                if ( $city_term ) {
                    $this->set_query_vars( $wp, ['taxonomy' => 'city', 'term' => $part1, 'lang' => $lang]);
                    $is_custom_url = true;
                }
            }

            if ( ! $is_custom_url ) {
                unset( $wp->query_vars['cob_part1'], $wp->query_vars['cob_part2'], $wp->query_vars['cob_part3'] );
            }
        }

        private function set_query_vars( &$wp, $vars_to_set ) {
            unset( $wp->query_vars['cob_part1'], $wp->query_vars['cob_part2'], $wp->query_vars['cob_part3'] );
            foreach ( $vars_to_set as $key => $value ) {
                if ( $value !== null ) {
                    $wp->query_vars[ $key ] = $value;
                }
            }
        }

        public function filter_property_permalink( $post_link, $post ) {
            if ( 'properties' !== $post->post_type || ! function_exists('pll_get_post_language') ) return $post_link;

            $lang_slug = pll_get_post_language( $post->ID, 'slug' ) ?: pll_default_language('slug');
            $city_terms = get_the_terms( $post->ID, 'city' );
            $compound_terms = get_the_terms( $post->ID, 'compound' );

            if ( empty($city_terms) || empty($compound_terms) ) return $post_link;

            $city_slug_val = current( $city_terms )->slug;
            $compound_slug_val = current( $compound_terms )->slug;

            $hide_default_lang = function_exists('pll_is_language_hidden') ? pll_is_language_hidden( $lang_slug ) : ( $lang_slug === pll_default_language('slug') );
            $lang_part = $hide_default_lang ? '' : "{$lang_slug}/";

            return home_url( user_trailingslashit( "{$lang_part}" . $this->base_prefix . "/{$city_slug_val}/{$compound_slug_val}/" . $post->post_name ) );
        }

        public function filter_term_link( $termlink, $term, $taxonomy ) {
            $hierarchical_taxonomies = ['city', 'compound'];
            if ( ! in_array( $taxonomy, $hierarchical_taxonomies ) || ! function_exists('pll_get_term_language') ) {
                return $termlink;
            }

            $lang_slug = pll_get_term_language( $term->term_id, 'slug' ) ?: pll_default_language('slug');
            $hide_default_lang = function_exists('pll_is_language_hidden') ? pll_is_language_hidden( $lang_slug ) : ( $lang_slug === pll_default_language('slug') );
            $lang_prefix = $hide_default_lang ? '' : "{$lang_slug}/";

            switch ($taxonomy) {
                case 'compound':
                    $city_slug_val = 'unknown-city';
                    $city_term_id = get_term_meta( $term->term_id, 'compound_city', true );
                    if ( $city_term_id && ($city_term_obj = get_term( (int) $city_term_id, 'city' )) && ! is_wp_error($city_term_obj) ) {
                        $city_slug_val = $city_term_obj->slug;
                    }
                    return home_url( user_trailingslashit( "{$lang_prefix}" . $this->base_prefix . "/{$city_slug_val}/{$term->slug}" ) );

                case 'city':
                    return home_url( user_trailingslashit( "{$lang_prefix}" . $this->base_prefix . "/{$term->slug}" ) );
            }

            return $termlink;
        }
    }
}
