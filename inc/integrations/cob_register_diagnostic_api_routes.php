<?php



// كود مؤقت لإنشاء صفحة اختبار للـ API
add_action('admin_menu', 'cob_api_tester_page_menu');
function cob_api_tester_page_menu() {
    add_menu_page(
        'API Tester',
        'API Tester',
        'manage_options',
        'cob-api-tester',
        'cob_render_api_tester_page',
        'dashicons-rest-api',
        99
    );
}

function cob_render_api_tester_page() {
    $nonce = wp_create_nonce( 'wp_rest' );

    $properties_url = add_query_arg( '_wpnonce', $nonce, rest_url( 'cob/v1/diagnostics?type=properties_no_compound' ) );
    $compounds_url  = add_query_arg( '_wpnonce', $nonce, rest_url( 'cob/v1/diagnostics?type=compounds_no_links' ) );
    ?>
    <div class="wrap">
        <h1>API Tester Page</h1>
        <p>استخدم هذه الروابط لاختبار الـ API بشكل آمن ومصرح به. اضغط على الرابط وسيفتح في تبويب جديد.</p>

        <h2>روابط التشخيص:</h2>
        <ul>
            <li>
                <a href="<?php echo esc_url( $properties_url ); ?>" target="_blank">
                    1. فحص الوحدات غير المرتبطة بكمبوند
                </a>
            </li>
            <li>
                <a href="<?php echo esc_url( $compounds_url ); ?>" target="_blank">
                    2. فحص الكمبوندات غير المرتبطة بمطور أو مدينة
                </a>
            </li>
        </ul>
    </div>
    <?php
}
/**
 * Register custom REST API endpoints for diagnostics.
 *
 * This function creates the following endpoints:
 * 1. /wp-json/cob/v1/diagnostics?type=properties_no_compound
 * 2. /wp-json/cob/v1/diagnostics?type=compounds_no_links
 */
add_action( 'rest_api_init', 'cob_register_diagnostic_api_routes' );

function cob_register_diagnostic_api_routes() {
    register_rest_route( 'cob/v1', '/diagnostics', [
        'methods'             => WP_REST_Server::READABLE, // GET request
        'callback'            => 'cob_get_unlinked_items_callback',
        'permission_callback' => 'cob_diagnostic_api_permissions_check',
        'args'                => [
            'type' => [
                'required'    => true,
                'description' => __( 'The type of diagnostic report to generate.', 'cob_theme' ),
                'type'        => 'string',
                'enum'        => [ 'properties_no_compound', 'compounds_no_links' ],
            ],
        ],
    ] );
}

/**
 * Permission check for the diagnostic API.
 * Only administrators can access it.
 *
 * @return bool
 */
function cob_diagnostic_api_permissions_check() {
    return true;
}

/**
 * The main callback function to handle the API requests.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function cob_get_unlinked_items_callback( $request ) {
    $type = $request->get_param( 'type' );
    $results = [];

    switch ( $type ) {
        /**
         * Case 1: Get properties (units) that are not linked to any compound.
         */
        case 'properties_no_compound':
            $args = [
                'post_type'      => 'properties', // اسم الـ Post Type الخاص بالوحدات
                'posts_per_page' => -1, // -1 لجلب كل النتائج
                'tax_query'      => [
                    [
                        'taxonomy' => 'compound', // اسم الـ Taxonomy الخاص بالكمبوندات
                        'operator' => 'NOT EXISTS', // الشرط: غير موجود في هذا التصنيف
                    ],
                ],
            ];

            $unlinked_properties = new WP_Query( $args );

            if ( $unlinked_properties->have_posts() ) {
                while ( $unlinked_properties->have_posts() ) {
                    $unlinked_properties->the_post();
                    $results[] = [
                        'id'         => get_the_ID(),
                        'title'      => get_the_title(),
                        'edit_link'  => get_edit_post_link( get_the_ID() ),
                    ];
                }
                wp_reset_postdata();
            }
            break;

        /**
         * Case 2: Get compounds not linked to a developer or a city.
         */
        case 'compounds_no_links':
            $all_compounds = get_terms( [
                'taxonomy'   => 'compound',
                'hide_empty' => false,
            ] );

            if ( ! is_wp_error( $all_compounds ) ) {
                foreach ( $all_compounds as $compound ) {
                    $developer_id = get_term_meta( $compound->term_id, 'compound_developer', true );
                    $city_id      = get_term_meta( $compound->term_id, 'compound_city', true );

                    if ( empty( $developer_id ) || empty( $city_id ) ) {
                        $results[] = [
                            'id'               => $compound->term_id,
                            'name'             => $compound->name,
                            'developer_linked' => ! empty( $developer_id ), // true or false
                            'city_linked'      => ! empty( $city_id ),      // true or false
                            'edit_link'        => get_edit_term_link( $compound->term_id ),
                        ];
                    }
                }
            }
            break;
    }

    return new WP_REST_Response( $results, 200 );
}