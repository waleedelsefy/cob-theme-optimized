<?php
/**
 * Template for displaying a section of Legal Services.
 * This version is adapted for Polylang to ensure the "View All" link
 * points to the correct translated page.
 */

// Check if Polylang's function exists to get the current language slug.
$current_lang = '';
if (function_exists('pll_current_language')) {
    $current_lang = pll_current_language();
}

// Arguments for fetching the latest 3 services in the current language.
$args = [
    'post_type'      => 'services',
    'posts_per_page' => 3,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'lang'           => $current_lang, // Polylang parameter to filter by language.
];

$services_query = new WP_Query($args);
?>

<div class="services">
    <div class="container">
        <div class="services-content">
            <div class="head-services">
                <h2 class="head"><?php esc_html_e( 'Legal services', 'cob_theme' ); ?></h2>
                <p><?php esc_html_e( "We provide a variety of legal services to meet our clients' needs", "cob_theme" ); ?></p>
            </div>
            <div class="services-cards">
                <?php if ($services_query->have_posts()) : ?>
                    <?php while ($services_query->have_posts()) : $services_query->the_post(); ?>
                        <?php
                        // Get custom field for the SVG icon.
                        $svg_icon = get_post_meta(get_the_ID(), '_cob_service_svg', true);
                        ?>
                        <div class="services-card">
                            <div class="svg-hold">
                                <?php if (!empty($svg_icon)) : ?>
                                    <?php echo $svg_icon; // It's important to ensure this SVG content is sanitized. ?>
                                <?php else : ?>

                                    <svg width="29" height="28" fill="white"><rect width="29" height="28" fill="gray"/></svg>
                                <?php endif; ?>
                            </div>
                            <h5><?php the_title(); ?></h5>
                            <p><?php echo get_the_excerpt(); ?></p>
                        </div>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <p><?php esc_html_e( 'There are currently no services in this language.', 'cob_theme' ); ?></p>
                <?php endif; ?>
            </div>

            <?php
            // --- ROBUST MULTILINGUAL LINK LOGIC FOR POLYLANG ---
            // This logic finds the correct link for the 'legal-services' page
            // based on the current language.
            $services_page_link = '#'; // Default fallback link.
            // Get the page object by its slug.
            $services_page = get_page_by_path( 'legal-services' );

            if ( $services_page ) {
                $page_id = $services_page->ID;

                // Check if Polylang's function `pll_get_post` exists.
                if ( function_exists( 'pll_get_post' ) ) {
                    // Get the ID of the translated page for the current language.
                    $translated_page_id = pll_get_post( $page_id, pll_current_language() );

                    // If a translation exists, use its ID. Otherwise, fallback to the original ID.
                    $link_id = $translated_page_id ? $translated_page_id : $page_id;
                    $services_page_link = get_permalink( $link_id );
                } else {
                    // If Polylang is not active, fall back to the standard WordPress function.
                    $services_page_link = get_permalink( $page_id );
                }
            }
            ?>
            <a href="<?php echo esc_url( $services_page_link ); ?>" class="services-button">
                <?php esc_html_e('View all', 'cob_theme'); ?>
                <svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                            d="M2.32561 7.00227L7.4307 12.1033C7.80826 12.4809 7.80826 13.0914 7.4307 13.465C7.05314 13.8385 6.44262 13.8385 6.06506 13.465L0.281171 7.68509C-0.0843415 7.31958 -0.0923715 6.73316 0.253053 6.3556L6.06104 0.535563C6.24982 0.346785 6.49885 0.254402 6.74386 0.254402C6.98887 0.254402 7.2379 0.346785 7.42668 0.535563C7.80424 0.913122 7.80424 1.52364 7.42668 1.89719L2.32561 7.00227Z"
                            fill="#ec3c43" />
                </svg>
            </a>
        </div>
    </div>
</div>
