<?php
/**
 * AJAX Manager Class
 *
 * Centralized handling for all theme AJAX operations.
 * Allows for a single point of control to disable all AJAX if needed.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define a master switch for all AJAX functionality.
// To disable all AJAX, add define('COB_AJAX_ENABLED', false); to your wp-config.php file.
if ( ! defined( 'COB_AJAX_ENABLED' ) ) {
    define( 'COB_AJAX_ENABLED', true );
}

if ( ! class_exists( 'COB_Ajax_Manager' ) ) {

    /**
     * Manages all AJAX hooks and handlers for the theme.
     */
    class COB_Ajax_Manager {

        /**
         * Hooks the main registration function if AJAX is enabled.
         */
        public function __construct() {
            if ( COB_AJAX_ENABLED === true ) {
                // Use a lower priority to ensure all post types/taxonomies are registered.
                add_action( 'init', array( $this, 'register_ajax_hooks' ), 20 );
            }
        }

        /**
         * Registers all AJAX hooks for the theme.
         * This is the single place to add new AJAX actions.
         */
        public function register_ajax_hooks() {
            $ajax_events = [
                'load_developer_properties',
                'load_developer_compounds',
                'load_more_similar_units',
                'cob_load_properties',
                'load_more_developers',
                'load_more_projects',
                'cob_load_similar_units',
                'load_compound_properties',
                'cob_load_projects',
                'submit_job_application',
            ];

            foreach ( $ajax_events as $event ) {
                add_action( "wp_ajax_{$event}", array( $this, "handle_{$event}" ) );
                add_action( "wp_ajax_nopriv_{$event}", array( $this, "handle_{$event}" ) );
            }
        }

        /**
         * Handler for the general taxonomy page sorting/pagination (e.g., sort by price/date).
         */
        public function handle_load_developer_properties() {
            check_ajax_referer('load_developer_properties_nonce', 'nonce');

            $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $sort     = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
            $term_id  = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';

            if ( ! $term_id || ! taxonomy_exists($taxonomy) ) {
                wp_send_json_error(['message' => 'Invalid taxonomy or term.']);
            }

            switch ($sort) {
                case 'price_asc':  $orderby = 'meta_value_num'; $order = 'ASC'; $meta_key = 'price'; break;
                case 'price_desc': $orderby = 'meta_value_num'; $order = 'DESC'; $meta_key = 'price'; break;
                case 'date_asc':   $orderby = 'date'; $order = 'ASC'; $meta_key = ''; break;
                default:           $orderby = 'date'; $order = 'DESC'; $meta_key = ''; break;
            }

            $args = [
                'post_type' => 'properties', 'posts_per_page' => 6, 'paged' => $page,
                'orderby' => $orderby, 'order' => $order, 'post_status' => 'publish',
                'tax_query' => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id, ] ],
            ];
            if (!empty($meta_key)) { $args['meta_key'] = $meta_key; }
            if ( function_exists( 'pll_current_language' ) ) { $args['lang'] = pll_current_language(); }

            $query = new WP_Query($args);

            ob_start();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    get_template_part('template-parts/single/properties-card');
                }
            } else {
                echo '<p class="no-results">' . esc_html__('There are no posts currently available', 'cob_theme') . '</p>';
            }
            $html = ob_get_clean();
            wp_reset_postdata();

            wp_send_json_success([ 'html' => $html, 'max_pages' => $query->max_num_pages, ]);
        }

        /**
         * Handler for the "Top Compounds" section sorting/pagination (sort by views/date).
         */
        public function handle_load_developer_compounds() {
            check_ajax_referer( 'cob_developer_compounds_nonce', 'nonce' );

            $paged    = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
            $sort     = isset( $_POST['sort'] ) ? sanitize_key( $_POST['sort'] ) : 'views';
            $term_id  = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
            $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

            if ( ! $term_id || ! $taxonomy || ! term_exists( $term_id, $taxonomy ) ) {
                wp_send_json_error( [ 'message' => 'Invalid term information.' ] );
            }

            $args = [
                'post_type'      => 'properties',
                'posts_per_page' => 6,
                'paged'          => $paged,
                'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id ] ],
            ];

            if ( $sort === 'views' ) {
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = 'post_views_count';
            } else {
                $args['orderby'] = 'date';
            }

            $query = new WP_Query( $args );
            $list_html = '';
            $images_html = '';

            if ( $query->have_posts() ) {
                ob_start();
                while ( $query->have_posts() ) {
                    $query->the_post();
                    get_template_part('template-parts/developer/list-item-compound');
                }
                $list_html = ob_get_clean();

                if ( $paged === 1 ) {
                    ob_start();
                    if ( function_exists('cob_get_top_compounds_images') ) {
                        cob_get_top_compounds_images( array_slice( $query->posts, 0, 2 ) );
                    }
                    $images_html = ob_get_clean();
                }
            }
            wp_reset_postdata();

            wp_send_json_success( [
                'list_html'   => $list_html,
                'images_html' => $images_html,
                'max_pages'   => $query->max_num_pages,
                'page'        => $paged,
            ] );
        }

        /**
         * Handler for loading more similar units (infinite scroll).
         */
        public function handle_load_more_similar_units() {
            check_ajax_referer( 'cob_similar_units_nonce', 'nonce' );

            $paged       = isset( $_POST['page'] ) ? intval( $_POST['page'] ) + 1 : 2;
            $post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
            $search_by   = isset( $_POST['search_by'] ) ? sanitize_key( $_POST['search_by'] ) : '';
            $search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( urldecode( $_POST['search_term'] ) ) : '';
            $lang        = isset( $_POST['lang'] ) ? sanitize_text_field( $_POST['lang'] ) : '';

            if ( ! $post_id || ! in_array( $search_by, [ 'city', 'developer' ] ) || empty( $search_term ) || $search_term === '-' ) {
                wp_send_json_error( ['message' => 'Invalid or missing search parameters.'] );
            }

            $exclude_posts = [ $post_id ];
            if ( function_exists( 'pll_get_post_translations' ) ) {
                $translations = pll_get_post_translations( $post_id );
                $exclude_posts = !empty($translations) ? array_merge($exclude_posts, array_values($translations)) : $exclude_posts;
            }
            $exclude_posts = array_map( 'intval', array_unique( $exclude_posts ) );

            $args = [
                'post_type' => 'properties', 'posts_per_page' => 3, 'paged' => $paged,
                'post__not_in' => $exclude_posts,
                'tax_query' => [ [ 'taxonomy' => $search_by, 'field' => 'slug', 'terms' => $search_term ] ],
                'ignore_sticky_posts' => 1,
            ];

            if ( ! empty( $lang ) ) { $args['lang'] = $lang; }

            $query = new WP_Query( $args );
            if ( $query->have_posts() ) {
                ob_start();
                while ( $query->have_posts() ) {
                    $query->the_post();
                    get_template_part( 'template-parts/single/properties-card' );
                }
                $html = ob_get_clean();
                wp_reset_postdata();
                wp_send_json_success( [ 'html' => $html ] );
            } else {
                wp_send_json_error( ['message' => 'No more units found.'] );
            }
        }

        /**
         * Handler for loading properties on compound taxonomy pages.
         */
        public function handle_load_compound_properties() {
            check_ajax_referer('load_compound_properties_nonce', 'nonce');

            $page        = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $sort_option = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
            $term_id     = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
            $taxonomy    = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : 'compound';

            if (empty($term_id) || empty($taxonomy)) {
                wp_send_json_error(['message' => 'Required term information is missing.']);
            }

            switch ($sort_option) {
                case 'price_asc':  $orderby = 'meta_value_num'; $order = 'ASC';  $meta_key = 'min_price'; break;
                case 'price_desc': $orderby = 'meta_value_num'; $order = 'DESC'; $meta_key = 'min_price'; break;
                case 'date_asc':   $orderby = 'date';           $order = 'ASC';  $meta_key = ''; break;
                default:           $orderby = 'date';           $order = 'DESC'; $meta_key = ''; break;
            }

            $args = [
                'post_type'      => 'properties',
                'posts_per_page' => 6,
                'paged'          => $page,
                'orderby'        => $orderby,
                'order'          => $order,
                'post_status'    => 'publish',
                'tax_query'      => [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id, ] ],
            ];

            if (!empty($meta_key)) { $args['meta_key'] = $meta_key; }

            $properties_query = new WP_Query($args);

            ob_start();
            if ($properties_query->have_posts()) {
                while ($properties_query->have_posts()) {
                    $properties_query->the_post();
                    get_template_part('template-parts/single/properties-card');
                }
            } else {
                echo '<p class="no-results">' . esc_html__('No properties match your criteria.', 'cob_theme') . '</p>';
            }
            wp_reset_postdata();
            $html = ob_get_clean();

            wp_send_json_success([
                'html'          => $html,
                'max_pages'     => $properties_query->max_num_pages,
                'total_results' => $properties_query->found_posts
            ]);
        }

        /**
         * Handler for loading more developers (taxonomy terms).
         */
        public function handle_load_more_developers() {
            check_ajax_referer( 'cob_developer_nonce', 'nonce' );

            $paged  = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
            $number = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
            $offset = ( $paged - 1 ) * $number;

            $term_query = new WP_Term_Query([
                'taxonomy'   => 'developer',
                'hide_empty' => false,
                'number'     => $number,
                'offset'     => $offset,
            ]);

            if ( ! empty( $term_query->terms ) ) {
                ob_start();
                foreach ( $term_query->terms as $developer ) {
                    get_template_part('template-parts/developer-card', null, ['developer' => $developer]);
                }
                wp_send_json_success( [ 'html' => ob_get_clean() ] );
            } else {
                wp_send_json_error( [ 'message' => 'No more developers found.' ] );
            }
        }

        /**
         * Handler for loading more projects (city terms).
         */
        public function handle_load_more_projects() {
            check_ajax_referer( 'cob_projects_nonce', 'nonce' );

            $paged  = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
            $number = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 9;
            $offset = ( $paged - 1 ) * $number;

            $cities = get_terms( [
                'taxonomy' => 'city', 'hide_empty' => false, 'number' => $number, 'offset' => $offset, 'parent' => 0,
            ] );

            if ( ! empty( $cities ) && ! is_wp_error( $cities ) ) {
                ob_start();
                foreach ( $cities as $city ) {
                    get_template_part('template-parts/city-card', null, ['city' => $city]);
                }
                wp_send_json_success( [ 'html' => ob_get_clean() ] );
            } else {
                wp_send_json_error( [ 'message' => 'No more cities found.' ] );
            }
        }

        /**
         * Handler for the legacy "cob_load_similar_units" action.
         */
        public function handle_cob_load_similar_units() {
            check_ajax_referer( 'cob_similar_nonce', 'nonce' );
            $paged   = absint( $_POST['paged']   ?? 1 );
            $unit_id = absint( $_POST['unit_id'] ?? 0 );
            $city    = sanitize_text_field( $_POST['city'] ?? '' );

            $query = new WP_Query([
                'post_type'      => 'properties',
                'posts_per_page' => 6,
                'paged'          => $paged,
                'post__not_in'   => [ $unit_id ],
                'tax_query'      => [[ 'taxonomy' => 'city', 'field' => 'slug', 'terms' => $city, ]],
            ]);

            ob_start();
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    get_template_part( 'template-parts/single/properties-card' );
                }
            } else {
                echo '<p>' . esc_html__( 'There are no posts currently available.', 'cob_theme' ) . '</p>';
            }
            $cards = ob_get_clean();
            wp_reset_postdata();

            $pages = paginate_links([
                'base' => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format' => '?paged=%#%', 'current' => $paged, 'total' => $query->max_num_pages,
                'type' => 'list',
            ]);

            wp_send_json_success([ 'cards' => $cards, 'pagination' => $pages ]);
        }

        /**
         * Generic handler for loading properties by city (e.g., for factory page).
         */
        public function handle_cob_load_properties() {
            check_ajax_referer( 'cob_factory_nonce', 'nonce' );

            $paged = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
            $city  = isset( $_POST['city'] ) ? sanitize_text_field( $_POST['city'] ) : '';

            $query = new WP_Query([
                'post_type' => 'properties', 'posts_per_page' => 6, 'paged' => $paged,
                'tax_query' => [ [ 'taxonomy' => 'city', 'field' => 'slug', 'terms' => $city ] ],
            ]);

            ob_start();
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    get_template_part( 'template-parts/single/properties-card' );
                }
            } else {
                echo '<p>' . esc_html__( 'There are no posts currently available', 'cob_theme' ) . '</p>';
            }
            $cards = ob_get_clean();
            wp_reset_postdata();

            $pages = paginate_links([
                'base' => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format' => '?paged=%#%', 'current' => $paged, 'total' => $query->max_num_pages,
                'type' => 'list',
            ]);

            wp_send_json_success([ 'cards' => $cards, 'pagination' => $pages, ]);
        }

        /**
         * Handler for the "Latest Projects" page AJAX pagination.
         */
        public function handle_cob_load_projects() {
            check_ajax_referer( 'cob_projects_nonce', 'nonce' );
            $paged = absint( $_POST['paged'] ?? 1 );

            $query = new WP_Query([
                'post_type'      => 'factory',
                'posts_per_page' => 3,
                'paged'          => $paged,
            ]);

            ob_start();
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    get_template_part( 'template-parts/single/factorys-card' );
                }
                wp_reset_postdata();
            } else {
                echo '<p>' . esc_html__( 'No projects found.', 'cob_theme' ) . '</p>';
            }
            $cards = ob_get_clean();

            $pages = paginate_links([
                'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format'    => '?paged=%#%',
                'current'   => $paged,
                'total'     => $query->max_num_pages,
                'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'cob_theme' ),
                'next_text' => esc_html__( 'Next', 'cob_theme' ) . ' &raquo;',
                'type'      => 'list',
            ]);

            wp_send_json_success([
                'cards'      => $cards,
                'pagination' => $pages,
            ]);
        }

        /**
         * CORRECTED & UNIFIED HANDLER FOR SUBMITTING A JOB APPLICATION
         */
        public function handle_submit_job_application() {
            if ( ! isset( $_POST['job_application_nonce'] ) || ! wp_verify_nonce( $_POST['job_application_nonce'], 'submit_job_application' ) ) {
                wp_send_json_error( ['message' => __( 'Security check failed. Please refresh and try again.', 'cob_theme' )] );
            }

            $job_id             = isset( $_POST['job_id'] ) ? intval( $_POST['job_id'] ) : 0;
            $full_name          = sanitize_text_field( $_POST['full_name'] ?? '' );
            $phone              = sanitize_text_field( $_POST['phone'] ?? '' );
            $experience_years   = sanitize_text_field( $_POST['experience_years'] ?? '' );
            $email              = sanitize_email( $_POST['email'] ?? '' );
            $address            = sanitize_text_field( $_POST['address'] ?? '' );
            $additional_details = sanitize_textarea_field( $_POST['additional_details'] ?? '' );

            if ( empty($full_name) || empty($phone) || !is_email($email) ) {
                wp_send_json_error( ['message' => __('Please fill in all required fields correctly.', 'cob_theme')] );
            }

            $resume_file_url = '';
            if ( isset( $_FILES['resume'] ) && ! empty( $_FILES['resume']['name'] ) ) {
                if ( ! function_exists( 'wp_handle_upload' ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/file.php' );
                }
                $uploaded_file = $_FILES['resume'];
                $upload_overrides = [
                    'test_form' => false,
                    'mimes'     => [
                        'pdf'  => 'application/pdf',
                        'doc'  => 'application/msword',
                        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ]
                ];
                $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

                if ( $movefile && empty( $movefile['error'] ) ) {
                    $resume_file_url = $movefile['url'];
                } else {
                    wp_send_json_error( ['message' => $movefile['error'] ?? __('File upload failed.', 'cob_theme')] );
                }
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'job_applications';

            $result = $wpdb->insert(
                $table_name,
                [
                    'job_id'             => $job_id,
                    'full_name'          => $full_name,
                    'phone'              => $phone,
                    'experience_years'   => $experience_years,
                    'email'              => $email,
                    'address'            => $address,
                    'resume'             => $resume_file_url,
                    'additional_details' => $additional_details,
                    'status'             => 'pending',
                    'submission_date'    => current_time('mysql'),
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );

            if ( $result ) {
                wp_send_json_success( [ 'message' => __( 'Thank you for applying! Your application has been received.', 'cob_theme' ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'An error occurred while saving your application. Please try again.', 'cob_theme' ) . ' DB_ERROR: ' . $wpdb->last_error ] );
            }
        }
    }
}
