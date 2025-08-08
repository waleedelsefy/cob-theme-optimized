<?php
/**
 * Template Part for displaying a single city card.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) || ! isset( $args['city'] ) ) {
    return;
}

$city = $args['city'];
$theme_dir = get_template_directory_uri();

$city_link = get_term_link( $city );
if ( is_wp_error( $city_link ) ) {
    $city_link = '#';
}
$city_name = $city->name;

// Get city image.
$thumbnail_id   = absint( get_term_meta( $city->term_id, 'thumbnail_id', true ) );
$city_image_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : $theme_dir . '/assets/imgs/default-city.png';

// Count compounds linked to this city.
$compound_query = get_terms([
    'taxonomy'   => 'compound',
    'hide_empty' => false,
    'fields'     => 'ids',
    'meta_query' => [
        [
            'key'     => 'compound_city',
            'value'   => $city->term_id,
            'compare' => '=',
            'type'    => 'NUMERIC'
        ]
    ]
]);
$compound_count = ! is_wp_error( $compound_query ) && is_array( $compound_query ) ? count( $compound_query ) : 0;

// Count properties in this city, including its children.
$property_query = new WP_Query( array(
    'post_type'      => 'properties',
    'tax_query'      => array(
        array(
            'taxonomy'         => 'city',
            'field'            => 'term_id',
            'terms'            => $city->term_id,
            'include_children' => true,
        ),
    ),
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );
$property_count = $property_query->found_posts;
?>

<a href="<?php echo esc_url( $city_link ); ?>" class="card">
    <svg width="27" height="27" viewBox="0 0 27 27" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16.0555 18.1727V10.9445M16.0555 10.9445H8.82735M16.0555 10.9445L5.36827 21.6318M11.2552 24.78C14.8933 25.5005 18.8123 24.4511 21.6318 21.6318C26.1228 17.1407 26.1228 9.85932 21.6318 5.36828C17.1406 0.87724 9.85929 0.87724 5.36827 5.36828C2.54889 8.18766 1.49947 12.1067 2.21998 15.7448" stroke="#EC3C43" stroke-width="2.42105" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <p><?php echo esc_html( number_format_i18n($compound_count) ) . ' ' . __( 'Compounds', 'cob_theme' ); ?></p>
    <span><?php echo esc_html( number_format_i18n($property_count) ) . ' ' . __( 'Properties', 'cob_theme' ); ?></span>
    <button><?php echo esc_html( $city_name ); ?></button>
</a>
