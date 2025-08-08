<?php
/**
 * UNIFIED Custom Fields & Columns for Taxonomies (Compound, Developer, City)
 *
 * VERSION 1.0 - MERGED & REFACTORED
 * - Merges all separate files for taxonomy custom fields into one organized file.
 * - Manages image/logo fields for Compound, Developer, and City taxonomies using Attachment IDs.
 * - Manages relationship fields (Developer, City) for the Compound taxonomy.
 * - Adds custom columns to the admin list tables for each taxonomy.
 * - Unifies JavaScript for the media uploader into a single, efficient script.
 * - Ensures all meta keys are consistent with the new importers.
 *
 * @package Capital_of_Business_Customizations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// ============== 1. DEFINE ALL META KEYS ==============
// By defining them here, we ensure consistency with the importers.

define( 'COB_COMPOUND_COVER_IMAGE_META_KEY', '_compound_cover_image_id' );
define( 'COB_COMPOUND_DEVELOPER_META_KEY', 'compound_developer' );
define( 'COB_COMPOUND_CITY_META_KEY', 'compound_city' );
define( 'COB_DEVELOPER_LOGO_META_KEY', '_developer_logo_id' );
define( 'COB_CITY_COVER_IMAGE_META_KEY', '_city_cover_image_id' ); // Standardized key


// ============== 2. COMPOUND TAXONOMY FIELDS & COLUMNS ==============

if ( ! function_exists( 'cob_add_compound_custom_fields' ) ) {
    /**
     * Adds all custom fields to the 'compound' add screen.
     */
    function cob_add_compound_custom_fields() {
        ?>
        <div class="form-field term-group">
            <label for="cob-compound-cover-image-id"><?php _e( 'Compound Cover Image', 'cob_theme' ); ?></label>
            <input type="hidden" name="<?php echo esc_attr( COB_COMPOUND_COVER_IMAGE_META_KEY ); ?>" id="cob-compound-cover-image-id" value="">
            <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 200px;"></div>
            <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Image', 'cob_theme' ); ?></button>
            <button type="button" class="button cob-remove-image-button" style="display:none;"><?php _e( 'Remove Image', 'cob_theme' ); ?></button>
        </div>

        <div class="form-field term-group">
            <label for="<?php echo esc_attr( COB_COMPOUND_DEVELOPER_META_KEY ); ?>"><?php _e( 'Developer', 'cob_theme' ); ?></label>
            <?php
            wp_dropdown_categories( [
                'taxonomy'        => 'developer',
                'name'            => COB_COMPOUND_DEVELOPER_META_KEY,
                'orderby'         => 'name',
                'show_option_none' => __( 'Select a Developer', 'cob_theme' ),
                'hide_empty'      => false,
            ] );
            ?>
        </div>

        <div class="form-field term-group">
            <label for="<?php echo esc_attr( COB_COMPOUND_CITY_META_KEY ); ?>"><?php _e( 'City', 'cob_theme' ); ?></label>
            <?php
            wp_dropdown_categories( [
                'taxonomy'        => 'city',
                'name'            => COB_COMPOUND_CITY_META_KEY,
                'orderby'         => 'name',
                'show_option_none' => __( 'Select a City', 'cob_theme' ),
                'hide_empty'      => false,
            ] );
            ?>
        </div>
        <?php
    }
}
add_action( 'compound_add_form_fields', 'cob_add_compound_custom_fields' );

if ( ! function_exists( 'cob_edit_compound_custom_fields' ) ) {
    /**
     * Adds all custom fields to the 'compound' edit screen.
     */
    function cob_edit_compound_custom_fields( $term ) {
        $attachment_id = get_term_meta( $term->term_id, COB_COMPOUND_COVER_IMAGE_META_KEY, true );
        $image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
        $developer_id  = get_term_meta( $term->term_id, COB_COMPOUND_DEVELOPER_META_KEY, true );
        $city_id       = get_term_meta( $term->term_id, COB_COMPOUND_CITY_META_KEY, true );
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label><?php _e( 'Compound Cover Image', 'cob_theme' ); ?></label></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( COB_COMPOUND_COVER_IMAGE_META_KEY ); ?>" id="cob-compound-cover-image-id" value="<?php echo esc_attr( $attachment_id ); ?>">
                <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 200px;">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 200px; height: auto;">
                    <?php endif; ?>
                </div>
                <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Image', 'cob_theme' ); ?></button>
                <button type="button" class="button cob-remove-image-button" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php _e( 'Remove Image', 'cob_theme' ); ?></button>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="<?php echo esc_attr( COB_COMPOUND_DEVELOPER_META_KEY ); ?>"><?php _e( 'Developer', 'cob_theme' ); ?></label></th>
            <td>
                <?php
                wp_dropdown_categories( [
                    'taxonomy'        => 'developer',
                    'name'            => COB_COMPOUND_DEVELOPER_META_KEY,
                    'orderby'         => 'name',
                    'show_option_none' => __( 'Select a Developer', 'cob_theme' ),
                    'hide_empty'      => false,
                    'selected'        => $developer_id,
                ] );
                ?>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="<?php echo esc_attr( COB_COMPOUND_CITY_META_KEY ); ?>"><?php _e( 'City', 'cob_theme' ); ?></label></th>
            <td>
                <?php
                wp_dropdown_categories( [
                    'taxonomy'        => 'city',
                    'name'            => COB_COMPOUND_CITY_META_KEY,
                    'orderby'         => 'name',
                    'show_option_none' => __( 'Select a City', 'cob_theme' ),
                    'hide_empty'      => false,
                    'selected'        => $city_id,
                ] );
                ?>
            </td>
        </tr>
        <?php
    }
}
add_action( 'compound_edit_form_fields', 'cob_edit_compound_custom_fields' );

if ( ! function_exists( 'cob_save_compound_custom_fields' ) ) {
    /**
     * Saves all custom fields for the 'compound' taxonomy.
     */
    function cob_save_compound_custom_fields( $term_id ) {
        // Save Cover Image
        if ( isset( $_POST[COB_COMPOUND_COVER_IMAGE_META_KEY] ) ) {
            $attachment_id = sanitize_text_field( $_POST[COB_COMPOUND_COVER_IMAGE_META_KEY] );
            if ( ! empty( $attachment_id ) && is_numeric( $attachment_id ) ) {
                update_term_meta( $term_id, COB_COMPOUND_COVER_IMAGE_META_KEY, $attachment_id );
            } else {
                delete_term_meta( $term_id, COB_COMPOUND_COVER_IMAGE_META_KEY );
            }
        }
        // Save Developer
        if ( isset( $_POST[COB_COMPOUND_DEVELOPER_META_KEY] ) ) {
            $developer_id = sanitize_text_field( $_POST[COB_COMPOUND_DEVELOPER_META_KEY] );
            if ( ! empty( $developer_id ) && is_numeric( $developer_id ) ) {
                update_term_meta( $term_id, COB_COMPOUND_DEVELOPER_META_KEY, $developer_id );
            } else {
                delete_term_meta( $term_id, COB_COMPOUND_DEVELOPER_META_KEY );
            }
        }
        // Save City
        if ( isset( $_POST[COB_COMPOUND_CITY_META_KEY] ) ) {
            $city_id = sanitize_text_field( $_POST[COB_COMPOUND_CITY_META_KEY] );
            if ( ! empty( $city_id ) && is_numeric( $city_id ) ) {
                update_term_meta( $term_id, COB_COMPOUND_CITY_META_KEY, $city_id );
            } else {
                delete_term_meta( $term_id, COB_COMPOUND_CITY_META_KEY );
            }
        }
    }
}
add_action( 'created_compound', 'cob_save_compound_custom_fields' );
add_action( 'edited_compound', 'cob_save_compound_custom_fields' );

if ( ! function_exists( 'cob_unified_compound_admin_columns' ) ) {
    /**
     * Adds ALL custom columns to the 'compound' taxonomy list table.
     */
    function cob_unified_compound_admin_columns( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            if ( $key === 'posts' ) {
                $new_columns['compound_cover_image'] = __( 'Cover Image', 'cob_theme' );
                $new_columns['compound_developer']   = __( 'Developer', 'cob_theme' );
                $new_columns['compound_city']        = __( 'City', 'cob_theme' );
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }
}
add_filter( 'manage_edit-compound_columns', 'cob_unified_compound_admin_columns' );

if ( ! function_exists( 'cob_unified_compound_column_content' ) ) {
    /**
     * Displays content for ALL custom columns for the 'compound' taxonomy.
     */
    function cob_unified_compound_column_content( $content, $column_name, $term_id ) {
        switch ( $column_name ) {
            case 'compound_cover_image':
                $attachment_id = get_term_meta( $term_id, COB_COMPOUND_COVER_IMAGE_META_KEY, true );
                if ( $attachment_id ) {
                    return wp_get_attachment_image( $attachment_id, [ 50, 50 ], true );
                }
                break;
            case 'compound_developer':
                $developer_id = get_term_meta( $term_id, COB_COMPOUND_DEVELOPER_META_KEY, true );
                if ( $developer_id ) {
                    $developer_term = get_term( $developer_id, 'developer' );
                    if ( $developer_term && ! is_wp_error( $developer_term ) ) {
                        return '<a href="' . esc_url( get_edit_term_link( $developer_id, 'developer' ) ) . '">' . esc_html( $developer_term->name ) . '</a>';
                    }
                }
                break;
            case 'compound_city':
                $city_id = get_term_meta( $term_id, COB_COMPOUND_CITY_META_KEY, true );
                if ( $city_id ) {
                    $city_term = get_term( $city_id, 'city' );
                    if ( $city_term && ! is_wp_error( $city_term ) ) {
                        return '<a href="' . esc_url( get_edit_term_link( $city_id, 'city' ) ) . '">' . esc_html( $city_term->name ) . '</a>';
                    }
                }
                break;
        }
        return $content;
    }
}
add_filter( 'manage_compound_custom_column', 'cob_unified_compound_column_content', 10, 3 );


// ============== 3. DEVELOPER TAXONOMY FIELDS & COLUMNS ==============

if ( ! function_exists( 'cob_add_developer_logo_field' ) ) {
    function cob_add_developer_logo_field() {
        ?>
        <div class="form-field term-group">
            <label><?php _e( 'Developer Logo', 'cob_theme' ); ?></label>
            <input type="hidden" name="<?php echo esc_attr( COB_DEVELOPER_LOGO_META_KEY ); ?>" value="">
            <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 150px;"></div>
            <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Logo', 'cob_theme' ); ?></button>
            <button type="button" class="button cob-remove-image-button" style="display:none;"><?php _e( 'Remove Logo', 'cob_theme' ); ?></button>
        </div>
        <?php
    }
}
add_action( 'developer_add_form_fields', 'cob_add_developer_logo_field' );

if ( ! function_exists( 'cob_edit_developer_logo_field' ) ) {
    function cob_edit_developer_logo_field( $term ) {
        $attachment_id = get_term_meta( $term->term_id, COB_DEVELOPER_LOGO_META_KEY, true );
        $image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label><?php _e( 'Developer Logo', 'cob_theme' ); ?></label></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( COB_DEVELOPER_LOGO_META_KEY ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>">
                <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 150px;">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" style="max-width:100%; height:auto;">
                    <?php endif; ?>
                </div>
                <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Logo', 'cob_theme' ); ?></button>
                <button type="button" class="button cob-remove-image-button" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php _e( 'Remove Logo', 'cob_theme' ); ?></button>
            </td>
        </tr>
        <?php
    }
}
add_action( 'developer_edit_form_fields', 'cob_edit_developer_logo_field' );

if ( ! function_exists( 'cob_save_developer_logo_field' ) ) {
    function cob_save_developer_logo_field( $term_id ) {
        if ( isset( $_POST[COB_DEVELOPER_LOGO_META_KEY] ) ) {
            $attachment_id = sanitize_text_field( $_POST[COB_DEVELOPER_LOGO_META_KEY] );
            if ( ! empty( $attachment_id ) && is_numeric( $attachment_id ) ) {
                update_term_meta( $term_id, COB_DEVELOPER_LOGO_META_KEY, $attachment_id );
            } else {
                delete_term_meta( $term_id, COB_DEVELOPER_LOGO_META_KEY );
            }
        }
    }
}
add_action( 'created_developer', 'cob_save_developer_logo_field' );
add_action( 'edited_developer', 'cob_save_developer_logo_field' );

if ( ! function_exists( 'cob_add_developer_logo_column' ) ) {
    function cob_add_developer_logo_column( $columns ) {
        $columns['developer_logo'] = __( 'Logo', 'cob_theme' );
        return $columns;
    }
}
add_filter( 'manage_edit-developer_columns', 'cob_add_developer_logo_column' );

if ( ! function_exists( 'cob_display_developer_logo_column' ) ) {
    function cob_display_developer_logo_column( $content, $column_name, $term_id ) {
        if ( 'developer_logo' === $column_name ) {
            $attachment_id = get_term_meta( $term_id, COB_DEVELOPER_LOGO_META_KEY, true );
            if ( $attachment_id ) {
                return wp_get_attachment_image( $attachment_id, [ 50, 50 ], true );
            }
        }
        return $content;
    }
}
add_filter( 'manage_developer_custom_column', 'cob_display_developer_logo_column', 10, 3 );


// ============== 4. CITY TAXONOMY FIELDS & COLUMNS (UPGRADED) ==============

if ( ! function_exists( 'cob_add_city_image_field' ) ) {
    function cob_add_city_image_field() {
        ?>
        <div class="form-field term-group">
            <label><?php _e( 'City Cover Image', 'cob_theme' ); ?></label>
            <input type="hidden" name="<?php echo esc_attr( COB_CITY_COVER_IMAGE_META_KEY ); ?>" value="">
            <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 200px;"></div>
            <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Image', 'cob_theme' ); ?></button>
            <button type="button" class="button cob-remove-image-button" style="display:none;"><?php _e( 'Remove Image', 'cob_theme' ); ?></button>
        </div>
        <?php
    }
}
add_action( 'city_add_form_fields', 'cob_add_city_image_field' );

if ( ! function_exists( 'cob_edit_city_image_field' ) ) {
    function cob_edit_city_image_field( $term ) {
        $attachment_id = get_term_meta( $term->term_id, COB_CITY_COVER_IMAGE_META_KEY, true );
        $image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label><?php _e( 'City Cover Image', 'cob_theme' ); ?></label></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( COB_CITY_COVER_IMAGE_META_KEY ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>">
                <div class="cob-image-preview-wrapper" style="margin-bottom: 10px; border: 1px dashed #ddd; padding: 10px; min-height: 100px; max-width: 200px;">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" style="max-width:200px; height:auto;">
                    <?php endif; ?>
                </div>
                <button type="button" class="button cob-upload-image-button"><?php _e( 'Upload/Select Image', 'cob_theme' ); ?></button>
                <button type="button" class="button cob-remove-image-button" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php _e( 'Remove Image', 'cob_theme' ); ?></button>
            </td>
        </tr>
        <?php
    }
}
add_action( 'city_edit_form_fields', 'cob_edit_city_image_field' );

if ( ! function_exists( 'cob_save_city_image_field' ) ) {
    function cob_save_city_image_field( $term_id ) {
        if ( isset( $_POST[COB_CITY_COVER_IMAGE_META_KEY] ) ) {
            $attachment_id = sanitize_text_field( $_POST[COB_CITY_COVER_IMAGE_META_KEY] );
            if ( ! empty( $attachment_id ) && is_numeric( $attachment_id ) ) {
                update_term_meta( $term_id, COB_CITY_COVER_IMAGE_META_KEY, $attachment_id );
            } else {
                delete_term_meta( $term_id, COB_CITY_COVER_IMAGE_META_KEY );
            }
        }
    }
}
add_action( 'created_city', 'cob_save_city_image_field' );
add_action( 'edited_city', 'cob_save_city_image_field' );

if ( ! function_exists( 'cob_add_city_image_column' ) ) {
    function cob_add_city_image_column( $columns ) {
        $columns['city_image'] = __( 'Image', 'cob_theme' );
        return $columns;
    }
}
add_filter( 'manage_edit-city_columns', 'cob_add_city_image_column' );

if ( ! function_exists( 'cob_display_city_image_column' ) ) {
    function cob_display_city_image_column( $content, $column_name, $term_id ) {
        if ( 'city_image' === $column_name ) {
            $attachment_id = get_term_meta( $term_id, COB_CITY_COVER_IMAGE_META_KEY, true );
            if ( $attachment_id ) {
                return wp_get_attachment_image( $attachment_id, [ 50, 50 ], true );
            }
        }
        return $content;
    }
}
add_filter( 'manage_city_custom_column', 'cob_display_city_image_column', 10, 3 );


// ============== 5. UNIFIED JAVASCRIPT FOR MEDIA UPLOADER ==============

if ( ! function_exists( 'cob_unified_media_uploader_script' ) ) {
    /**
     * Enqueues the media uploader script and localizes data.
     */
    function cob_enqueue_media_uploader_assets( $hook ) {
        // Only load on taxonomy pages
        if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
            return;
        }

        wp_enqueue_media();
        // It's better to put this in a separate JS file, but for a single file solution, this is fine.
        add_action( 'admin_footer', 'cob_unified_media_uploader_script', 99 );
    }

    /**
     * The unified JavaScript logic.
     */
    function cob_unified_media_uploader_script() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                var mediaUploader;

                $('body').on('click', '.cob-upload-image-button', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var $formField = $button.closest('.form-field, .term-group-wrap');
                    var $attachmentIdField = $formField.find('input[type="hidden"]');
                    var $imageWrapper = $formField.find('.cob-image-preview-wrapper');
                    var $removeButton = $formField.find('.cob-remove-image-button');

                    mediaUploader = wp.media.frames.file_frame = wp.media({
                        title: '<?php _e( "Select or Upload Image", "cob_theme" ); ?>',
                        button: { text: '<?php _e( "Use this image", "cob_theme" ); ?>' },
                        multiple: false
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $attachmentIdField.val(attachment.id);
                        var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                        $imageWrapper.html('<img src="' + imageUrl + '" style="max-width:100%; height:auto;" />');
                        $removeButton.show();
                    });

                    mediaUploader.open();
                });

                $('body').on('click', '.cob-remove-image-button', function(e) {
                    e.preventDefault();
                    var $button = $(this);
                    var $formField = $button.closest('.form-field, .term-group-wrap');
                    var $attachmentIdField = $formField.find('input[type="hidden"]');
                    var $imageWrapper = $formField.find('.cob-image-preview-wrapper');

                    $attachmentIdField.val('');
                    $imageWrapper.html('');
                    $button.hide();
                });
            });
        </script>
        <?php
    }
}
add_action( 'admin_enqueue_scripts', 'cob_enqueue_media_uploader_assets' );