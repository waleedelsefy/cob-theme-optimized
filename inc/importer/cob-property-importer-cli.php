<?php
/**
 * WP-CLI command for High-Speed Property Importing.
 *
 * @version 1.3 - Added --fast-images to disable thumbnail generation during import.
 * @package cob_theme
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

$importer_functions_file = __DIR__ . '/cob-property-importer.php';
if ( file_exists( $importer_functions_file ) ) {
    require_once $importer_functions_file;
} else {
    WP_CLI::error( "The main importer function file is missing at: " . $importer_functions_file );
    return;
}

class COB_Property_CLI_Importer {

    /**
     * Imports properties from a given CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : The full path to the CSV file on the server.
     *
     * --language=<language>
     * : The language of the import. Accepted values: 'ar' or 'en'.
     *
     * [--skip-images]
     * : Use this flag to skip image processing entirely.
     *
     * [--fast-images]
     * : Use this flag to import original images only, skipping thumbnail generation for maximum speed.
     *
     * [--offset=<offset>]
     * : The number of rows to skip from the beginning.
     *
     * [--limit=<limit>]
     * : The maximum number of rows to process.
     *
     * @when after_wp_load
     */
    public function import( $args, $assoc_args ) {
        list( $file_path ) = $args;
        $language    = WP_CLI\Utils\get_flag_value( $assoc_args, 'language', '' );
        $skip_images = WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-images', false );
        $fast_images = WP_CLI\Utils\get_flag_value( $assoc_args, 'fast-images', false );
        $offset      = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );
        $limit       = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            WP_CLI::error( "File does not exist or is not readable: {$file_path}" );
        }
        if ( empty( $language ) || ! in_array( $language, ['ar', 'en'] ) ) {
            WP_CLI::error( "The --language parameter is required. Use 'ar' or 'en'." );
        }

        $config = cob_get_property_importer_config();
        $config['target_language'] = $language;
        $config['skip_images'] = $skip_images;

        // --- Performance Optimizations ---
        wp_defer_term_counting( true );
        wp_suspend_cache_invalidation( true );

        // ** NEW: Temporarily disable thumbnail generation for speed **
        if ( $fast_images ) {
            add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
            WP_CLI::log( "Fast Images mode enabled: Thumbnail generation is temporarily disabled." );
        }

        WP_CLI::log( "Opening file: {$file_path}" );
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) { WP_CLI::error( "Failed to open the file." ); }

        $headers = array_map( 'trim', fgetcsv( $handle, 0, $config['csv_delimiter'] ) );
        WP_CLI::log( "Process " . getmypid() . " handling with offset: {$offset}, limit: {$limit}" );
        
        for ( $i = 0; $i < $offset; $i++ ) { if ( fgetcsv( $handle ) === false ) { break; } }

        $progress = WP_CLI\Utils\make_progress_bar( "Importing slice", $limit > 0 ? $limit : 1000 );
        $summary = [ 'processed' => 0, 'imported' => 0, 'updated' => 0, 'failed' => 0 ];
        $rows_to_process = $limit > 0 ? $limit : PHP_INT_MAX;

        while ( $summary['processed'] < $rows_to_process && ( $raw_row_data = fgetcsv( $handle, 0, $config['csv_delimiter'] ) ) !== false ) {
            $summary['processed']++;
            $current_row_num = $offset + $summary['processed'];
            if ( count( $headers ) !== count( $raw_row_data ) ) {
                WP_CLI::warning( sprintf( "Row #%d: Skipping due to column count mismatch.", $current_row_num ) );
                $summary['failed']++;
                $progress->tick();
                continue;
            }
            $row_data = array_combine( $headers, $raw_row_data );
            $result = cob_import_single_property( $row_data, $config, $current_row_num );
            if ( $result['status'] === 'imported' ) $summary['imported']++;
            elseif ( $result['status'] === 'updated' ) $summary['updated']++;
            else {
                $summary['failed']++;
                foreach( $result['log'] as $log_message ) WP_CLI::warning( strip_tags( $log_message ) );
            }
            $progress->tick();
        }

        $progress->finish();
        fclose( $handle );

        // --- Restore Normal Operation ---
        wp_suspend_cache_invalidation( false );
        wp_defer_term_counting( false );
        wp_cache_flush();

        // ** NEW: Re-enable thumbnail generation **
        if ( $fast_images ) {
            remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
        }

        WP_CLI::success( "Process " . getmypid() . " finished its slice!" );
        WP_CLI::log( sprintf( "Slice Summary: Imported: %d, Updated: %d, Failed: %d", $summary['imported'], $summary['updated'], $summary['failed'] ) );
    }
}

WP_CLI::add_command( 'cob property', 'COB_Property_CLI_Importer' );
