<?php
/**
 * Similar Units Section with Fallback Logic (City -> Developer).
 *
 * @package cob_theme
 */

$post_id = get_the_ID();

// Determine the search criteria: city first, then developer.
$search_taxonomy = '';
$search_term_slug = '';
$search_term_name = '';

// Try to get city terms first.
$city_terms = get_the_terms( $post_id, 'city' );
if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) {
    $search_taxonomy = 'city';
    $search_term_slug = $city_terms[0]->slug;
    $search_term_name = $city_terms[0]->name;
} else {
    // Fallback to developer if no city is found.
    $developer_terms = get_the_terms( $post_id, 'developer' );
    if ( ! is_wp_error( $developer_terms ) && ! empty( $developer_terms ) ) {
        $search_taxonomy = 'developer';
        $search_term_slug = $developer_terms[0]->slug;
        $search_term_name = $developer_terms[0]->name;
    }
}

// Proceed only if we have a valid search criterion.
if ( ! empty( $search_taxonomy ) ) :

    // Initial query to load the first 3 properties.
    $initial_query = new WP_Query( [
        'post_type'      => 'properties',
        'posts_per_page' => 3,
        'paged'          => 1,
        'post__not_in'   => [ $post_id ],
        'tax_query'      => [
            [
                'taxonomy' => $search_taxonomy,
                'field'    => 'slug',
                'terms'    => $search_term_slug,
            ],
        ],
    ] );
    ?>

    <section class="pagination-section pagination-city">
        <div class="container">
            <div class="top-compounds">
                <div class="right-compounds">
                    <h3 class="head">
                        <?php
                        // Display a relevant heading based on the search criteria.
                        echo '<p>' . sprintf(
                                esc_html__( 'Similar units in %s', 'cob_theme' ),
                                esc_html( $search_term_name )
                            ) . '</p>';
                        ?>
                    </h3>
                </div>
            </div>

            <!-- The container that will be populated by AJAX -->
            <div id="similar-units-container"
                 class="properties-cards"
                 data-page="1"
                 data-post-id="<?php echo esc_attr( $post_id ); ?>"
                 data-search-by="<?php echo esc_attr( $search_taxonomy ); ?>"
                 data-search-term="<?php echo esc_attr( $search_term_slug ); ?>"
                 data-max-pages="<?php echo esc_attr( $initial_query->max_num_pages ); ?>">

                <?php
                // Display initial posts.
                if ( $initial_query->have_posts() ) {
                    while ( $initial_query->have_posts() ) {
                        $initial_query->the_post();
                        get_template_part( 'template-parts/single/properties-card' );
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p>' . esc_html__( 'No similar units found.', 'cob_theme' ) . '</p>';
                }
                ?>
            </div>

            <!-- Loading indicator -->
            <?php if ( $initial_query->max_num_pages > 1 ) : ?>
                <div id="similar-units-loader" style="display: none;">
                    <div class="loader" id="loader-2">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

<?php endif; // End check for search criteria. ?>
