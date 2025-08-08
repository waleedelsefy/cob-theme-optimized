<?php
/**
 * Template Name: Landing & Search Template
 *
 * @package cob_theme
 */


$theme_dir = get_template_directory_uri();
?>

<div class="landing">
    <div class="container">
        <div class="img-holder">
            <div class="swiper landing-swiper">
                <div class="swiper-wrapper">
                    <?php
                    $args         = [
                        'post_type'      => 'slider',
                        'posts_per_page' => -1,
                        'orderby'        => 'menu_order',
                        'order'          => 'ASC',
                    ];
                    $slider_query = new WP_Query( $args );
                    ?>
                    <?php if ( $slider_query->have_posts() ) : ?>
                        <?php while ( $slider_query->have_posts() ) : $slider_query->the_post(); ?>
                            <?php $slider_img = get_the_post_thumbnail_url( get_the_ID(), 'full' ); ?>
                            <div class="swiper-slide">
                                <img
                                        data-src="<?php echo esc_url( $slider_img ? $slider_img : $theme_dir . '/assets/imgs/landing.jpg' ); ?>"
                                        alt="<?php the_title_attribute(); ?>"
                                        class="lazyload"
                                >
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php else : ?>
                        <div class="swiper-slide">
                            <img
                                    data-src="<?php echo esc_url( $theme_dir . '/assets/imgs/landing.jpg' ); ?>"
                                    alt="<?php esc_attr_e( 'Default Slide', 'cob_theme' ); ?>"
                                    class="lazyload"
                            >
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-button-prev">
                    <svg width="20" height="12" viewBox="0 0 20 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1.66602 6.00033H18.3327M1.66602 6.00033C1.66602 4.54158 5.82081 1.81601 6.87435 0.791992M1.66602 6.00033C1.66602 7.45908 5.82081 10.1847 6.87435 11.2087" stroke="white" stroke-width="1.5625" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="swiper-button-next">
                    <svg width="20" height="12" viewBox="0 0 20 12" fill="#fff" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18.334 5.99967L1.66732 5.99967M18.334 5.99967C18.334 7.45842 14.1792 10.184 13.1257 11.208M18.334 5.99967C18.334 4.54092 14.1792 1.8153 13.1257 0.791341" stroke="#fff" stroke-width="1.5625" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <?php
            echo  do_shortcode('[cob_search_form]')

            ?>

        </div>
    </div>
</div>