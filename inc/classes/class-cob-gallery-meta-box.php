<?php
/**
 * Class COB_Gallery_Meta_Box
 *
 * Handles the creation, display, and saving of a custom meta box
 * for managing gallery images on various post types.
 * NOTE: This version removes the namespace to avoid conflicts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class COB_Gallery_Meta_Box {

    private $post_types = array( 'post', 'properties', 'lands', 'factory', 'page' );
    private $meta_key = '_cob_gallery_images';
    private $urls_meta_key = '_cob_gallery_image_urls';
    private $nonce_action = 'cob_gallery_images_nonce_action';
    private $nonce_name = 'cob_gallery_images_nonce';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_gallery_images_meta' ), 10, 1 );
        add_action( 'save_post', array( $this, 'import_gallery_from_urls' ), 20, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
    }

    public function enqueue_scripts_and_styles() {
        global $pagenow;
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && in_array( get_post_type(), $this->post_types ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'jquery-ui-sortable' );
            // Inline styles and scripts are kept for simplicity as in the original file.
            // It's recommended to move these to separate files in a production environment.
            $this->print_inline_styles();
            $this->print_inline_scripts();
        }
    }

    private function print_inline_styles() {
        ?>
        <style>
            #attached-images { list-style: none; margin: 0; padding: 0; overflow: auto; display: flex; flex-wrap: wrap; }
            #attached-images li { float: left; margin: 5px; position: relative; cursor: grab; border: 1px solid #eee; background: #fff; }
            #attached-images li img { max-width: 100px; height: auto; display: block; }
            .detach-image-button { position: absolute; top: -5px; right: -5px; background: #dc3232; color: #fff; border: none; border-radius: 50%; cursor: pointer; padding: 0 6px; height: 20px; width: 20px; line-height: 1; text-align: center; font-size: 16px; font-weight: bold; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: background-color 0.2s ease; }
            .detach-image-button:hover { background-color: #c00; }
        </style>
        <?php
    }

    private function print_inline_scripts() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                var frame;
                function updateGalleryField() {
                    var ids = $("#attached-images li").map(function() { return $(this).data("attachment-id"); }).get().join(",");
                    $("#cob_gallery_images").val(ids);
                }
                $("#attached-images").sortable({ placeholder: "ui-state-highlight", forcePlaceholderSize: true, update: updateGalleryField });
                $("#add-attached-images").on("click", function(e) {
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({
                        title: "<?php echo esc_js( __( 'Select Images', 'cob_theme' ) ); ?>",
                        button: { text: "<?php echo esc_js( __( 'Add to Gallery', 'cob_theme' ) ); ?>" },
                        multiple: true
                    });
                    frame.on("select", function() {
                        var selection = frame.state().get("selection").map(function(attachment) { return attachment.toJSON(); });
                        selection.forEach(function(attachment) {
                            if ($("#attached-images li[data-attachment-id='" + attachment.id + "']").length === 0) {
                                var thumb = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                                $("#attached-images").append('<li data-attachment-id="' + attachment.id + '"><img src="' + thumb + '" alt=""><button type="button" class="detach-image-button" data-attachment-id="' + attachment.id + '">×</button></li>');
                            }
                        });
                        updateGalleryField();
                    });
                    frame.open();
                });
                $("#attached-images").on("click", ".detach-image-button", function(e) {
                    e.preventDefault();
                    $(this).closest("li").remove();
                    updateGalleryField();
                });
            });
        </script>
        <?php
    }

    public function add_meta_box() {
        add_meta_box(
            'cob_gallery_images_meta_box',
            __( 'Gallery Images', 'cob_theme' ),
            array( $this, 'meta_box_callback' ),
            $this->post_types, 'normal', 'high'
        );
    }

    public function meta_box_callback( $post ) {
        wp_nonce_field( $this->nonce_action, $this->nonce_name );
        $image_ids = get_post_meta( $post->ID, $this->meta_key, true );
        $image_ids = is_array( $image_ids ) ? $image_ids : array_filter( explode( ',', $image_ids ) );
        ?>
        <div id="attached-images-wrapper">
            <ul id="attached-images">
                <?php
                if ( ! empty( $image_ids ) ) {
                    foreach ( $image_ids as $id ) {
                        $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
                        if ( ! $thumb ) continue;
                        echo '<li data-attachment-id="' . esc_attr( $id ) . '"><img src="' . esc_url( $thumb ) . '" alt=""><button type="button" class="detach-image-button" data-attachment-id="' . esc_attr( $id ) . '">×</button></li>';
                    }
                }
                ?>
            </ul>
            <button id="add-attached-images" class="button button-primary"><?php esc_html_e( 'Add Images', 'cob_theme' ); ?></button>
            <input type="hidden" name="cob_gallery_images" id="cob_gallery_images" value="<?php echo esc_attr( implode( ',', $image_ids ) ); ?>">
        </div>
        <?php
    }

    public function save_gallery_images_meta( $post_id ) {
        if ( ! isset( $_POST[ $this->nonce_name ] ) || ! wp_verify_nonce( $_POST[ $this->nonce_name ], $this->nonce_action ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['cob_gallery_images'] ) ) {
            $ids = array_filter( array_map( 'intval', explode( ',', $_POST['cob_gallery_images'] ) ) );
            update_post_meta( $post_id, $this->meta_key, $ids );
        } else {
            delete_post_meta( $post_id, $this->meta_key );
        }
    }

    public function import_gallery_from_urls( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $image_urls = get_post_meta( $post_id, $this->urls_meta_key, true );
        if ( empty( $image_urls ) ) return;

        $image_urls = is_string( $image_urls ) ? array_map( 'trim', explode( ',', $image_urls ) ) : $image_urls;
        $current_gallery_ids = is_array(get_post_meta( $post_id, $this->meta_key, true )) ? get_post_meta( $post_id, $this->meta_key, true ) : [];
        $new_attachment_ids = [];

        foreach ( $image_urls as $image_url ) {
            if ( ! empty( $image_url ) ) {
                $attachment_id = $this->import_external_image( $image_url, $post_id, $post->post_title );
                if ( $attachment_id && ! in_array( $attachment_id, $current_gallery_ids ) ) {
                    $new_attachment_ids[] = $attachment_id;
                }
            }
        }

        if ( ! empty( $new_attachment_ids ) ) {
            $updated_gallery_ids = array_unique( array_merge( $current_gallery_ids, $new_attachment_ids ) );
            update_post_meta( $post_id, $this->meta_key, $updated_gallery_ids );
            delete_post_meta( $post_id, $this->urls_meta_key );
        }
    }

    private function import_external_image( $url, $post_id = 0, $desc = '' ) {
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            error_log( 'COB Gallery Meta Box: Failed to download image from ' . $url . ': ' . $tmp->get_error_message() );
            return false;
        }

        $file_array = [
            'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
            'type'     => mime_content_type( $tmp ),
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => filesize( $tmp ),
        ];

        $sideload = media_handle_sideload( $file_array, $post_id, $desc );
        @unlink( $tmp );

        if ( is_wp_error( $sideload ) ) {
            error_log( 'COB Gallery Meta Box: Failed to sideload image from ' . $url . ': ' . $sideload->get_error_message() );
            return false;
        }

        return $sideload;
    }
}
