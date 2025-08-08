<?php
/**
 * The template for displaying the developer archive.
 * This version implements a "Load More" button instead of pagination.
 */

// Initial query setup
$number = 10; // Number of items per page
$paged = 1; // Always start with page 1 for the initial load
$offset = 0; // Initial offset is 0

// Get all term IDs to calculate total pages, which is needed for the "Load More" logic.
$all_developer_ids = get_terms( array(
    'taxonomy'   => 'developer',
    'hide_empty' => false,
    'fields'     => 'ids',
) );
$total_terms = is_array( $all_developer_ids ) ? count( $all_developer_ids ) : 0;
$total_pages = ceil( $total_terms / $number );

// Arguments for the initial query to load the first page of developers.
$args = array(
    'taxonomy'   => 'developer',
    'hide_empty' => false,
    'number'     => $number,
    'offset'     => $offset,
);

$term_query = new WP_Term_Query( $args );
$developers = $term_query->terms;
?>

<section class="pagination-section">
    <div class="container">
        <!-- Add an ID to the cards container so JavaScript can easily find it and append new items. -->
        <div class="cards" id="developer-cards-container">
            <?php if ( ! empty( $developers ) && ! is_wp_error( $developers ) ) : ?>
                <?php foreach ( $developers as $developer ) : ?>
                    <?php
                    // This block generates a single developer card.
                    // It's repeated in the AJAX handler, so consider moving it to a template part for easier maintenance.
                    // Example: get_template_part('template-parts/developer-card', null, ['developer' => $developer]);
                    if ( ! empty( $developer ) && is_object( $developer ) ) {
                        $developer_link = get_term_link( $developer );
                        if ( is_wp_error( $developer_link ) ) {
                            $developer_link = '#';
                        }
                        $thumbnail_id = absint( get_term_meta( $developer->term_id, '_developer_logo_id', true ) );
                        $image_url    = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : get_template_directory_uri() . '/assets/imgs/developer-default.png';
                    } else {
                        $developer_link = '#'; // Fallback URL
                        $image_url    = get_template_directory_uri() . '/assets/imgs/developer-default.png';
                    }
                    ?>
                    <div class="motaoron-card">
                        <a href="<?php echo esc_url( $developer_link ); ?>" class="motaoron-img">
                            <img data-src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $developer->name ); ?>" class="lazyload">
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php esc_html_e( 'There are currently no Developers.', 'cob_theme' ); ?></p>
            <?php endif; ?>
        </div>

        <?php // --- LOAD MORE BUTTON --- ?>
        <?php // Show the "Load More" button only if there is more than one page. ?>
        <?php if ( $total_pages > 1 ) : ?>
            <div class="load-more-container" style="text-align: center; margin-top: 30px;">
                <button id="load-more-developers" class="page"
                        data-page="1"
                        data-total-pages="<?php echo esc_attr( $total_pages ); ?>"
                        data-per-page="<?php echo esc_attr( $number ); ?>">
                    <?php esc_html_e( 'More', 'cob_theme' ); // "More" ?>
                </button>
                <div id="loading-spinner" style="display:none;">
                    <?php esc_html_e( 'Loading...', 'cob_theme' ); // "Loading..." ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
