<?php
/**
 * =================================================================================
 * COB Search - Handle Custom Search Filters on Results Page
 * =================================================================================
 * This code intercepts the main WordPress query on the search results page,
 * reads the custom filters from the URL, and applies them to the query.
 *
 * @param WP_Query $query The main WP_Query object.
 */
function cob_custom_search_query_logic( $query ) {
    //
    // 1. We only want to run this on the frontend, for the main query, on our specific search results page.
    //
    // is_main_query() ensures we don't interfere with custom queries in sidebars, footers, etc.
    // is_page('search-results') targets only our dedicated search results page.
    //
    if ( ! is_admin() && $query->is_main_query() && $query->is_page('search-results') ) {

        //
        // 2. Get the search parameters from the URL (e.g., ?s=...&filters[]=...)
        //
        $search_term = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $filters     = isset( $_GET['filters'] ) && is_array( $_GET['filters'] ) ? $_GET['filters'] : [];
        $lang        = isset( $_GET['lang'] ) ? sanitize_text_field( $_GET['lang'] ) : '';

        //
        // 3. Set the main search keyword for the query.
        //
        if ( ! empty( $search_term ) ) {
            $query->set( 's', $search_term );
        }

        // Set the language for the query if Polylang is active and a lang parameter is present
        if ( ! empty( $lang ) && function_exists( 'pll_the_languages' ) ) {
            $query->set( 'lang', $lang );
        }

        //
        // 4. Build the meta_query (for custom fields) and tax_query (for taxonomies) arrays.
        //
        $meta_query = array('relation' => 'AND');
        $tax_query  = array('relation' => 'AND');

        if ( ! empty( $filters ) ) {
            foreach ( $filters as $filter_string ) {
                // Each filter comes in the format "key:value", e.g., "taxonomies.type:Villa"
                list( $key, $value ) = explode( ':', $filter_string, 2 );

                // Handle Taxonomies (like property type)
                if ( strpos( $key, 'taxonomies.' ) === 0 ) {
                    $taxonomy = str_replace( 'taxonomies.', '', $key );
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug', // Assuming you are filtering by term slug
                        'terms'    => $value,
                    );
                }
                // Handle Custom Fields
                elseif ( strpos( $key, 'custom_fields.' ) === 0 ) {
                    $field_name = str_replace( 'custom_fields.', '', $key );

                    // Special handling for price ranges
                    if ( $field_name === 'price_range' ) {
                        // This assumes you have a numeric custom field named 'min_price' to compare against.
                        // You MUST adjust 'min_price' to your actual custom field key for the price.
                        $price_key = 'min_price';

                        if ( strpos( $value, '+' ) !== false ) {
                            // Handles ranges like "15000000+"
                            $min_price = intval( str_replace( '+', '', $value ) );
                            $meta_query[] = array(
                                'key'     => $price_key,
                                'value'   => $min_price,
                                'compare' => '>=',
                                'type'    => 'NUMERIC',
                            );
                        } else {
                            // Handles ranges like "5000000-9999999"
                            list( $min_price, $max_price ) = explode( '-', $value );
                            $meta_query[] = array(
                                'key'     => $price_key,
                                'value'   => array( intval( $min_price ), intval( $max_price ) ),
                                'compare' => 'BETWEEN',
                                'type'    => 'NUMERIC',
                            );
                        }
                    }
                    // Handling for other simple custom fields like 'bedrooms'
                    else {
                        $meta_query[] = array(
                            'key'     => $field_name,
                            'value'   => $value,
                            'compare' => '=',
                        );
                    }
                }
            }
        }

        //
        // 5. Apply the generated queries to the main WP_Query object.
        //
        if ( count( $tax_query ) > 1 ) {
            $query->set( 'tax_query', $tax_query );
        }
        if ( count( $meta_query ) > 1 ) {
            $query->set( 'meta_query', $meta_query );
        }

        //
        // 6. Ensure the query fetches posts from the post types you have indexed.
        //    This overrides the default search which might only search 'post' and 'page'.
        //
        $options = get_option('cob_search_options');
        $post_types_to_index = isset($options['indexed_post_types']) && is_array($options['indexed_post_types']) ? $options['indexed_post_types'] : ['post', 'page'];
        if (!empty($post_types_to_index)) {
            $query->set( 'post_type', $post_types_to_index );
        }
    }
}

// Add our function to the 'pre_get_posts' action hook.
add_action( 'pre_get_posts', 'cob_custom_search_query_logic' );