<?php
/**
 * Capital of Business Theme Functions
 *
 * This file acts as a loader for the theme's components, ensuring everything
 * is included in the correct and logical order to prevent dependency errors.
 *
 * @package Capital_of_Business
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// =========================================================================
//  1. Define Theme Constants
// =========================================================================
define( 'COB_THEME_DIR', get_template_directory() );
define( 'COB_THEME_URI', get_template_directory_uri() );

/**
 * Register all theme navigation menus.
 */
function cob_register_menus() {
    register_nav_menus( array(
        'primary_menu'           => __( 'Primary Menu', 'cob_theme' ),
        'top-articles'           => __( 'Top Articles', 'cob_theme' ),
        'footer_menu'            => __( 'Footer Menu', 'cob_theme' ),
        'footer_cities_menu'     => __( 'Footer Cities Menu', 'cob_theme' ),
        'footer_projects_menu'   => __( 'Footer Projects Menu', 'cob_theme' ),
        'footer_developers_menu' => __( 'Footer Developers Menu', 'cob_theme' ),
    ) );
}
// We hook this function to the 'init' action hook.
add_action( 'init', 'cob_register_menus' );


// =========================================================================
//  2. Core Theme File Includes (Order is Critical)
// =========================================================================
// This array defines all the necessary files and their correct loading order.
$cob_theme_files = [
    // Helpers (must be loaded first as other classes might depend on them)
    '/inc/helpers/theme-helpers.php',
    '/inc/helpers/template-helpers.php',

    // Core Classes
    // '/inc/classes/class-cob-activator.php', // IMPORTANT: This line is commented out to prevent fatal error. The plugin should handle its own activation.
    '/inc/theme-setup/class-cob-theme-setup.php',
    '/inc/theme-setup/class-cob-walker-nav-menu.php',
    '/inc/classes/class-cob-post-type-manager.php',
    '/inc/classes/class-cob-metabox-manager.php',
    '/inc/classes/class-cob-ajax-manager.php',
    '/inc/classes/class-cob-featured-taxonomies.php',
    '/inc/classes/class-cob-rewrite-manager.php',
    '/inc/classes/class-cob-enqueue-assets.php',
    '/inc/classes/class-cob-admin-pages-manager.php',
    '/inc/classes/class-cob-job-applicants-list-table.php',
    '/inc/classes/class-cob-search.php',
    '/inc/classes/class-cob-html-minifier.php',
    '/inc/classes/class-cob-gallery-meta-box.php',
    '/inc/importer/pll_save_post_translations.php',

    // Integrations
    '/inc/integrations/class-cob-zoom-integration.php',
    '/inc/integrations/cob_register_diagnostic_api_routes.php',

    // taxonomy
    '/inc/post-types/cob-taxonomy-fields.php',
    '/inc/taxonomy/compound/duplicate_compounds.php',

    // Importer Files
    '/inc/classes/importer/class-cob-master-importer.php',
    '/inc/importer/importer-taxonomies-unified.php',
    '/inc/importer/cob-missing-images-tool.php',
    '/inc/importer/duplicate_tax.php',
    '/inc/importer/cob-property-importer.php',
    '/inc/importer/importer-taxonomies-unified-cli.php',

    // Real Estate Expert Roles
    '/inc/real-estate-expert/add_real_estate_expert_role.php',
    '/inc/real-estate-expert/real_estate_expert_extra_profile_fields.php',
];

foreach ( $cob_theme_files as $file ) {
    $filepath = COB_THEME_DIR . $file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
}

// =========================================================================
//  3. Instantiate Core Classes & Run Setup Actions
// =========================================================================
/**
 * Initializes all the core classes of the theme.
 */
function cob_instantiate_theme_classes() {
    if ( class_exists( 'COB_Theme_Setup' ) ) { COB_Theme_Setup::instance(); }
    if ( class_exists( 'COB_HTML_Minifier' ) ) { COB_HTML_Minifier::instance(); }
    if ( class_exists( 'COB_Post_Type_Manager' ) ) { new COB_Post_Type_Manager(); }
    if ( class_exists( 'COB_Metabox_Manager' ) ) { new COB_Metabox_Manager(); }
    if ( class_exists( 'COB_Enqueue_Assets' ) ) { new COB_Enqueue_Assets(); }
    if ( class_exists( 'COB_Ajax_Manager' ) ) { new COB_Ajax_Manager(); }
    if ( class_exists( 'COB_Rewrite_Manager' ) ) { new COB_Rewrite_Manager(); }
    if ( class_exists( 'COB_Admin_Pages_Manager' ) ) { new COB_Admin_Pages_Manager(); }
    if ( class_exists( 'COB_Theme_Search_Integration' ) ) { COB_Theme_Search_Integration::instance(); }
    if ( class_exists( 'COB_Zoom_Integration' ) ) { COB_Zoom_Integration::instance(); }
    if ( class_exists( 'COB_Master_Importer' ) ) { COB_Master_Importer::instance(); }
    if ( class_exists( 'COB_Featured_Taxonomy' ) ) { new COB_Featured_Taxonomy(); }
    if ( class_exists( 'COB_Gallery_Meta_Box' ) ) { new COB_Gallery_Meta_Box(); }
}
add_action( 'after_setup_theme', 'cob_instantiate_theme_classes' );

/**
 * Handles theme activation tasks.
 * It's generally better for a plugin to handle its own activation.
 * However, if the theme needs to ensure certain actions on activation, this is the place.
 */
add_action('after_switch_theme', function() {
    // If you have specific theme activation tasks, they can go here.
    // For example, flushing rewrite rules if you've added new post types in the theme.
    flush_rewrite_rules();
});

// Load Redux Framework settings.
add_action( 'init', function() {
    $redux_config_path = COB_THEME_DIR . '/inc/redux/settings/config.php';
    if(file_exists($redux_config_path)) {
        require_once $redux_config_path;
    }
});

// =========================================================================
//  4. Third-party & Environment-specific Includes
// =========================================================================
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    $cli_importer_path = __DIR__ . '/inc/importer/cob-property-importer-cli.php';
    if ( file_exists( $cli_importer_path ) ) {
        require_once $cli_importer_path;
    }
}