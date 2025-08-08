<?php
/**
 * Theme Helper Functions
 *
 * This file contains reusable functions used across the theme.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'cob_get_asset_version' ) ) {
    /**
     * Retrieve asset version using the file's modification time for cache busting.
     *
     * @param string $file_path The absolute path to the asset file.
     * @return string|false The file modification time or false if file does not exist.
     */
    function cob_get_asset_version( $file_path ) {
        return file_exists( $file_path ) ? filemtime( $file_path ) : false;
    }
}

if ( ! function_exists( 'cob_get_top_compounds_images' ) ) {
    /**
     * Renders the top two compound images for the taxonomy developer page.
     *
     * @param array $posts Array of WP_Post objects.
     */
    function cob_get_top_compounds_images( $posts = [] ) {
        ?>
        <div class="images-inner-container">
            <?php if ( ! empty( $posts ) ) : ?>
                <div class="project-image image1">
                    <?php
                    $first_post = $posts[0];
                    if ( has_post_thumbnail( $first_post->ID ) ) {
                        echo get_the_post_thumbnail( $first_post->ID, 'large' );
                    } else {
                        // Use a placeholder if no thumbnail is available.
                        echo '<img src="' . esc_url( get_template_directory_uri() . '/assets/imgs/articles-det.jpg' ) . '" alt="' . esc_attr( get_the_title( $first_post->ID ) ) . '">';
                    }
                    ?>
                </div>
                <?php if ( count( $posts ) > 1 ) : ?>
                    <div class="project-image inner-img image2">
                        <?php
                        $second_post = $posts[1];
                        if ( has_post_thumbnail( $second_post->ID ) ) {
                            echo get_the_post_thumbnail( $second_post->ID, 'large' );
                        } else {
                            // Use a placeholder if no thumbnail is available.
                            echo '<img src="' . esc_url( get_template_directory_uri() . '/assets/imgs/articles-det2.jpg' ) . '" alt="' . esc_attr( get_the_title( $second_post->ID ) ) . '">';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="project-image image1"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/imgs/articles-det.jpg' ); ?>" alt="<?php esc_attr_e( 'Default Image 1', 'cob_theme' ); ?>"></div>
                <div class="project-image inner-img image2"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/imgs/articles-det2.jpg' ); ?>" alt="<?php esc_attr_e( 'Default Image 2', 'cob_theme' ); ?>"></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
