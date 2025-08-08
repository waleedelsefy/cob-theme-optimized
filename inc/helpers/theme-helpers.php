<?php
/**
 * Theme Helper Functions
 *
 * This file contains reusable functions that can be called from anywhere
 * in the theme, ensuring they are defined before being used.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'cob_get_asset_version' ) ) {
    /**
     * Retrieves an asset's version based on its last modification time.
     * This is used for cache-busting CSS and JS files.
     *
     * @param string $file_path The absolute path to the asset file.
     * @return string|false The file modification time as a string, or false if the file doesn't exist.
     */
    function cob_get_asset_version( $file_path ) {
        return file_exists( $file_path ) ? (string) filemtime( $file_path ) : false;
    }
}

if ( ! function_exists( 'cob_get_top_compounds_images' ) ) {
    /**
     * Renders the HTML for the top two compound images, used on taxonomy pages.
     *
     * @param array $posts An array of WP_Post objects to display images from.
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
                        // Fallback to a default placeholder image.
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
                            // Fallback to a default placeholder image.
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
/**
 * Trims the term description to a specified number of words.
 *
 * This function retrieves the description for a given term, removes any
 * HTML tags, and then truncates it to the desired word count.
 *
 * @param WP_Term|null $term The term object. If null, it will be ignored.
 * @param int          $limit The maximum number of words to return.
 * @return string The truncated term description.
 */
if ( ! function_exists( 'cob_get_limited_term_description' ) ) {
    function cob_get_limited_term_description( $term, $limit = 100 ) {
        if ( ! $term || is_wp_error( $term ) ) {
            return '';
        }

        // Get the description from the term object.
        $description = term_description( $term->term_id, $term->taxonomy );

        if ( empty( $description ) ) {
            return '';
        }

        // Use WordPress's built-in function to safely trim the text.
        // It strips tags and adds an ellipsis automatically.
        return wp_trim_words( $description, $limit );
    }
}
