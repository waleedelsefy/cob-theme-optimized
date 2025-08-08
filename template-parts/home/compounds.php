<?php
/**
 * Most Searched Compounds Template
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme_dir             = get_template_directory_uri();
$cover_image_meta_key  = '_compound_cover_image_id';

$all_terms = get_terms( [
    'taxonomy'   => 'compound',
    'hide_empty' => false,
] );

$compounds         = [];
$compound_modified = [];

if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {

    foreach ( $all_terms as $term ) {
        $attachment_id = get_term_meta( $term->term_id, $cover_image_meta_key, true );
        if ( ! $attachment_id ) {
            continue;
        }
        $query = new WP_Query( [
            'post_type'      => 'properties',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'tax_query'      => [
                [
                    'taxonomy' => 'compound',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
            'fields'         => 'ids',
        ] );

        if ( $query->have_posts() ) {
            $last_modified = get_post_modified_time( 'U', false, $query->posts[0] );
        } else {
            $last_modified = 0;
        }

        $compound_modified[ $term->term_id ] = $last_modified;
        $compounds[]                        = $term;
    }

    usort( $compounds, function( $a, $b ) use ( $compound_modified ) {
        $a_time = $compound_modified[ $a->term_id ] ?? 0;
        $b_time = $compound_modified[ $b->term_id ] ?? 0;
        return $b_time - $a_time;
    } );

    $compounds = array_slice( $compounds, 0, 9 );
}
?>

<div class="compounds">
    <div class="container">
        <div class="top-compounds">
            <div class="right-compounds">
                <h3 class="head"><?php esc_html_e( 'Most Searched Compounds', 'cob_theme' ); ?></h3>
            </div>
        </div>

        <?php if ( ! empty( $compounds ) ) : ?>
            <div class="swiper swiper1 hover14">
                <div class="swiper-wrapper">
                    <?php foreach ( $compounds as $compound ) : ?>
                        <?php
                        $attachment_id = get_term_meta( $compound->term_id, $cover_image_meta_key, true );
                        $image_data    = wp_get_attachment_image_src( $attachment_id, 'medium' );
                        $image_url     = $image_data[0] ?? ( $theme_dir . '/assets/imgs/default.jpg' );
                        ?>
                        <div class="swiper-slide">
                            <a href="<?php echo esc_url( get_term_link( $compound ) ); ?>" class="compounds-card">
                                <div class="top-card-comp">
                                    <h6><?php echo esc_html( $compound->name ); ?></h6>
                                    <span>
                                        <?php
                                        $prop_count = get_term_meta( $compound->term_id, 'properties_count', true );
                                        if ( ! is_numeric( $prop_count ) ) {
                                            $prop_count = $compound->count;
                                        }
                                        echo esc_html( number_format_i18n( (int) $prop_count ) ) . ' ' . esc_html__( 'Properties', 'cob_theme' );
                                        ?>
                                    </span>
                                </div>
                                <img src="<?php echo esc_url( $image_url ); ?>"
                                     alt="<?php echo esc_attr( $compound->name ); ?>"
                                     class="lazyload">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="swiper-button-prev">
                    <svg width="20" height="12" viewBox="0 0 20 12" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M1.66602 6.00033H18.3327M1.66602 6.00033C1.66602 4.54158 5.82081 1.81601 6.87435 0.791992M1.66602 6.00033C1.66602 7.45908 5.82081 10.1847 6.87435 11.2087" stroke="white" stroke-width="1.5625" stroke-linecap="round" stroke-linejoin="round"></path> </svg>
                </div>
                <div class="swiper-button-next">
                    <svg width="20" height="12" viewBox="0 0 20 12" fill="#fff" xmlns="http://www.w3.org/2000/svg"> <path d="M18.334 5.99967L1.66732 5.99967M18.334 5.99967C18.334 7.45842 14.1792 10.184 13.1257 11.208M18.334 5.99967C18.334 4.54092 14.1792 1.8153 13.1257 0.791341" stroke="#fff" stroke-width="1.5625" stroke-linecap="round" stroke-linejoin="round"></path> </svg>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No compounds with images found.', 'cob_theme' ); ?></p>
        <?php endif; ?>
    </div>
</div>
