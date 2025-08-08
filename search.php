<?php
/**
 * The template for displaying search results pages.
 *
 * This template is used by WordPress whenever a search is performed.
 * It's designed to work with the integrated COB Search functionality.
 *
 * @package Capital_of_Business_Theme
 */

get_header();

// Get the search query from the URL.
$search_query_string = get_search_query();

// Get custom filters from the URL if they exist.
$raw_filters = isset( $_GET['filters'] ) && is_array( $_GET['filters'] ) ? $_GET['filters'] : [];
$filters = [];
if ( ! empty( $raw_filters ) ) {
    foreach( $raw_filters as $filter_string ) {
        // Basic validation for the filter format "key:value".
        if ( is_string( $filter_string ) && strpos( $filter_string, ':' ) !== false ) {
            list($key, $val) = explode(':', $filter_string, 2);
            $filters[ sanitize_key($key) ] = sanitize_text_field( $val );
        }
    }
}

// Prepare the arguments for WP_Query.
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$args = [
    'post_type'      => ['properties', 'post', 'page', 'jobs', 'factory'], // The post types to search within.
    'posts_per_page' => 12, // How many results to show per page.
    'paged'          => $paged,
    's'              => $search_query_string, // The main search keyword.
    'post_status'    => 'publish',
];

// Add taxonomy and meta queries based on the filters.
$tax_query = [];
$meta_query = [];

foreach ( $filters as $key => $value ) {
    if ( strpos( $key, 'taxonomies.' ) === 0 ) {
        $taxonomy = str_replace( 'taxonomies.', '', $key );
        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'name',
            'terms'    => $value,
        ];
    }
    if ( strpos( $key, 'custom_fields.' ) === 0 ) {
        $meta_key = str_replace( 'custom_fields.', '', $key );
        // Special handling for price range
        if ( $meta_key === 'price_range' ) {
            $price_parts = explode( ' - ', $value );
            if ( count( $price_parts ) === 2 ) {
                $meta_query[] = [
                    'key'     => 'min_price',
                    'value'   => [ (int)str_replace(',', '', $price_parts[0]), (int)str_replace(',', '', $price_parts[1]) ],
                    'type'    => 'NUMERIC',
                    'compare' => 'BETWEEN',
                ];
            } elseif ( strpos( $value, '+' ) !== false ) {
                $meta_query[] = [
                    'key'     => 'min_price',
                    'value'   => (int)str_replace(['+', ','], '', $value),
                    'type'    => 'NUMERIC',
                    'compare' => '>=',
                ];
            }
        } else {
            $meta_query[] = [
                'key'     => $meta_key,
                'value'   => $value,
                'compare' => '=',
            ];
        }
    }
}

if ( ! empty( $tax_query ) ) {
    $args['tax_query'] = count( $tax_query ) > 1 ? array_merge( ['relation' => 'AND'], $tax_query ) : $tax_query;
}
if ( ! empty( $meta_query ) ) {
    $args['meta_query'] = count( $meta_query ) > 1 ? array_merge( ['relation' => 'AND'], $meta_query ) : $meta_query;
}


// Execute the final query.
$search_query = new WP_Query( $args );

?>

<div class="search-results-page">
    <div class="container">

        <header class="search-results-header">
            <h1 class="page-title">
                <?php
                if ( $search_query->have_posts() ) {
                    /* translators: %s: search query. */
                    printf( esc_html__( 'Search Results for: %s', 'cob_theme' ), '<span>' . get_search_query() . '</span>' );
                } else {
                    esc_html_e( 'Nothing Found', 'cob_theme' );
                }
                ?>
            </h1>
            <div class="search-results-form-container">
                <?php
                // Display the search form again so users can refine their search.
                echo do_shortcode('[cob_search_form]');
                ?>
            </div>
        </header>

        <div class="search-results-content">
            <?php if ( $search_query->have_posts() ) : ?>
                <div class="results-grid">
                    <?php
                    // Start the Loop.
                    while ( $search_query->have_posts() ) :
                        $search_query->the_post();
                        /**
                         * Use a template part to display the content.
                         * This assumes you have a properties-card.php or similar.
                         * Adjust the path if necessary.
                         */
                        get_template_part( 'template-parts/single/properties-card' );
                    endwhile;
                    ?>
                </div>

                <div class="pagination-container">
                    <?php
                    // Display pagination.
                    echo paginate_links( [
                        'base'    => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                        'format'  => '?paged=%#%',
                        'current' => max( 1, get_query_var( 'paged' ) ),
                        'total'   => $search_query->max_num_pages,
                    ] );
                    ?>
                </div>

            <?php else : ?>
                <div class="no-results-found">
                    <p><?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'cob_theme' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php wp_reset_postdata(); ?>

    </div><!-- .container -->
</div><!-- .search-results-page -->

<?php get_footer(); ?>
