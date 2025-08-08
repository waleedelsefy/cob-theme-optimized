<?php
/**
 * WordPress Permalink and Rewrite Rule Customizations for Cob Theme.
 *
 * WARNING: This version removes the URL bases, which can lead to significant rewrite rule conflicts
 * and is generally not recommended. This version includes a custom resolver to handle ambiguity between cities and developers.
 *
 * New URL Structure:
 * - Cities:      /lang/city-slug/
 * - Developers:  /lang/developer-slug/  <-- NEW
 * - Compounds:   /lang/city-slug/compound-slug/
 * - Properties:  /lang/city-slug/compound-slug/post-id/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Add custom query variables to WordPress.
 */
if ( ! function_exists( 'cob_add_query_vars' ) ) {
    function cob_add_query_vars( $vars ) {
        $vars[] = 'lang';
        $vars[] = 'city';
        $vars[] = 'compound';
        $vars[] = 'developer';     // NEW: Add developer query var
        $vars[] = 'resolver_slug'; // NEW: Add a slug for our custom resolver
        return $vars;
    }
    add_filter( 'query_vars', 'cob_add_query_vars' );
}

/**
 * Add all custom rewrite rules.
 * The city and developer rules are replaced by a single resolver rule.
 */
if ( ! function_exists( 'cob_add_all_custom_rewrite_rules_conflicting' ) ) {
    function cob_add_all_custom_rewrite_rules_conflicting() {

        // --- Single Property Post Rule (Most Specific) ---
        add_rewrite_rule(
            '^([^/]{2})/([^/]+)/([^/]+)/([0-9]+)/?$',
            'index.php?post_type=properties&p=$matches[4]&lang=$matches[1]&city=$matches[2]&compound=$matches[3]',
            'top'
        );
        add_rewrite_rule(
            '^([^/]+)/([^/]+)/([0-9]+)/?$',
            'index.php?post_type=properties&p=$matches[3]&city=$matches[1]&compound=$matches[2]',
            'top'
        );

        // --- Compound Taxonomy Archive Rule (Less Specific) ---
        add_rewrite_rule(
            '^([^/]{2})/([^/]+)/([^/]+)/?$',
            'index.php?taxonomy=compound&term=$matches[3]&lang=$matches[1]&city=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^([^/]+)/([^/]+)/?$',
            'index.php?taxonomy=compound&term=$matches[2]&city=$matches[1]',
            'top'
        );

        // MODIFIED: Custom Resolver Rule for City and Developer Taxonomies
        // This single rule captures any /lang/slug/ or /slug/ structure and sends it to our resolver logic.
        add_rewrite_rule(
            '^([^/]{2})/([^/]+)/?$',
            'index.php?resolver_slug=$matches[2]&lang=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^([^/]+)/?$',
            'index.php?resolver_slug=$matches[1]',
            'top'
        );
    }
    add_action( 'init', 'cob_add_all_custom_rewrite_rules_conflicting' );
}

/**
 * NEW: Custom resolver function to determine if a slug is a city or a developer.
 * This hooks into 'pre_get_posts' to modify the main query before it runs.
 * Note: This gives priority to developers. If a slug exists for both a developer and a city, it will be treated as a developer.
 */
if ( ! function_exists( 'cob_resolve_city_or_developer' ) ) {
    function cob_resolve_city_or_developer( $query ) {
        // Only run on the main query on the frontend and if our resolver_slug is present.
        if ( is_admin() || ! $query->is_main_query() || ! isset( $query->query_vars['resolver_slug'] ) ) {
            return;
        }

        $resolver_slug = $query->query_vars['resolver_slug'];

        // Check if a term with this slug exists in the 'developer' taxonomy first.
        if ( term_exists( $resolver_slug, 'developer' ) ) {
            $query->set( 'taxonomy', 'developer' );
            $query->set( 'term', $resolver_slug );
        }
        // If not a developer, we check if it's a city.
        elseif ( term_exists( $resolver_slug, 'city' ) ) {
            $query->set( 'taxonomy', 'city' );
            $query->set( 'term', $resolver_slug );
        }

        // Clean up the query var so it doesn't interfere with anything else.
        unset( $query->query_vars['resolver_slug'] );
    }
    add_action( 'pre_get_posts', 'cob_resolve_city_or_developer' );
}


/**
 * Customize the permalink for 'properties' post type.
 * (No changes needed in this function)
 */
if ( ! function_exists( 'cob_properties_permalink_custom' ) ) {
    function cob_properties_permalink_custom( $post_link, $post, $leavename, $sample ) {
        if ( 'properties' !== $post->post_type || $sample ) {
            return $post_link;
        }

        $lang_slug = 'en';
        if ( function_exists( 'pll_get_post_language' ) ) {
            $current_lang = pll_get_post_language( $post->ID, 'slug' );
            if ( ! empty( $current_lang ) ) {
                $lang_slug = $current_lang;
            } elseif ( function_exists('pll_default_language') ) {
                $lang_slug = pll_default_language('slug');
            }
        }

        $city_slug_val = 'unknown-city';
        $city_terms = get_the_terms( $post->ID, 'city' );
        if ( ! empty( $city_terms ) && ! is_wp_error( $city_terms ) ) {
            $city_slug_val = current( $city_terms )->slug;
        }

        $compound_slug_val = 'unknown-compound';
        $compound_terms = get_the_terms( $post->ID, 'compound' );
        if ( ! empty( $compound_terms ) && ! is_wp_error( $compound_terms ) ) {
            $compound_slug_val = current( $compound_terms )->slug;
        }

        $hide_default_lang_slug = function_exists('pll_is_language_hidden') && pll_is_language_hidden($lang_slug);

        if ($hide_default_lang_slug) {
            $post_link = home_url( user_trailingslashit( "{$city_slug_val}/{$compound_slug_val}/" . $post->ID ) );
        } else {
            $post_link = home_url( user_trailingslashit( "{$lang_slug}/{$city_slug_val}/{$compound_slug_val}/" . $post->ID ) );
        }

        return $post_link;
    }
    add_filter( 'post_type_link', 'cob_properties_permalink_custom', 10, 4 );
}

/**
 * MODIFIED: Customize term links for 'compound', 'city', and now 'developer' taxonomies.
 */
if ( ! function_exists( 'cob_custom_term_link_custom' ) ) {
    function cob_custom_term_link_custom( $termlink, $term, $taxonomy ) {

        $lang_slug = 'en';
        if ( function_exists( 'pll_get_term_language' ) ) {
            $current_lang = pll_get_term_language( $term->term_id, 'slug' );
            if ( ! empty( $current_lang ) ) {
                $lang_slug = $current_lang;
            } elseif ( function_exists('pll_default_language') ) {
                $lang_slug = pll_default_language('slug');
            }
        }
        $hide_default_lang_slug = function_exists('pll_is_language_hidden') && pll_is_language_hidden($lang_slug);

        // NEW: Handle 'developer' taxonomy links: /lang/developer-slug/
        if ( 'developer' === $taxonomy ) {
            $developer_slug = $term->slug;
            if ($hide_default_lang_slug) {
                return home_url( user_trailingslashit( "{$developer_slug}" ) );
            } else {
                return home_url( user_trailingslashit( "{$lang_slug}/{$developer_slug}" ) );
            }
        }

        // Handle 'compound' taxonomy links: /lang/city-slug/compound-slug/
        if ( 'compound' === $taxonomy ) {
            $city_term_id = get_term_meta( $term->term_id, 'compound_city', true );
            $city_slug_val = 'unknown-city';
            if ( $city_term_id && is_numeric($city_term_id) ) {
                $city_term_obj = get_term( (int) $city_term_id, 'city' );
                if ( $city_term_obj && ! is_wp_error( $city_term_obj ) ) {
                    $city_slug_val = $city_term_obj->slug;
                }
            }
            $compound_slug_val = $term->slug;

            if ($hide_default_lang_slug) {
                return home_url( user_trailingslashit( "{$city_slug_val}/{$compound_slug_val}" ) );
            } else {
                return home_url( user_trailingslashit( "{$lang_slug}/{$city_slug_val}/{$compound_slug_val}" ) );
            }
        }

        // Handle 'city' taxonomy links: /lang/city-slug/
        if ( 'city' === $taxonomy ) {
            $city_slug_val = $term->slug;
            if ($hide_default_lang_slug) {
                return home_url( user_trailingslashit( "{$city_slug_val}" ) );
            } else {
                return home_url( user_trailingslashit( "{$lang_slug}/{$city_slug_val}" ) );
            }
        }

        return $termlink; // Return original link for any other taxonomy
    }
    add_filter( 'term_link', 'cob_custom_term_link_custom', 10, 3 );
}


/**
 * IMPORTANT: After adding or modifying rewrite rules, you MUST flush WordPress's
 * rewrite rules for the changes to take effect.
 * You can do this by visiting the "Settings" > "Permalinks" page in the
 * WordPress admin area and simply clicking the "Save Changes" button.
 * Do this EVERY TIME you change these rules.
 */
?>