<?php
/**
 * Standalone WP-CLI "Smart Link" Command for Compounds.
 *
 * VERSION 5.1 - THE FINAL, COMPLETE & FULLY IMPLEMENTED FILE
 * - This file is self-contained and includes all necessary functions, now fully written.
 * - Adds `--start-from-row` parameter to resume an interrupted process.
 * - Adds a `--force-images` flag to re-download all images.
 * - Uses a "fuzzy match" algorithm to link data by intelligently finding terms by name.
 * - Fixes terms by adding the correct source_id, links relationships, and logs the action.
 *
 * @package Capital_of_Business_CLI_Tools
 */

if ( ! defined( 'WP_CLI' ) ) {
    return;
}

if ( ! class_exists( 'COB_Smart_Link_Compounds_Command' ) ) {

    class COB_Smart_Link_Compounds_Command {

        private $config;
        private $all_compound_names = [];

        /**
         * Finds, fixes, and links existing compounds, with an option to force image updates and resume.
         *
         * ## PARAMETERS
         *
         * <file>
         * : The full path to the compound CSV file on the server.
         *
         * ## OPTIONS
         *
         * [--lang=<language>]
         * : The language of the CSV columns.
         * ---
         * default: en
         * options: [en, ar]
         * ---
         *
         * [--force-images]
         * : Optional. If present, the script will re-download and update images.
         *
         * [--start-from-row=<row_number>]
         * : Optional. The data row number (excluding the header) to start processing from.
         * ---
         * default: 1
         * ---
         *
         * ## EXAMPLES
         *
         * # 1. Run the full process from the beginning
         * $ wp cob smart-link-compounds /path/to/file.csv --lang=en
         *
         * # 2. Resume the process starting from the 500th data row
         * $ wp cob smart-link-compounds /path/to/file.csv --lang=en --start-from-row=500
         *
         * # 3. Resume from row 500 and force image updates for the remaining items
         * $ wp cob smart-link-compounds /path/to/file.csv --lang=en --start-from-row=500 --force-images
         */
        public function __invoke( $args, $assoc_args ) {
            list( $file_path ) = $args;
            $lang = $assoc_args['lang'];
            $force_images = isset( $assoc_args['force-images'] );
            $start_from_row = (int) ( $assoc_args['start-from-row'] ?? 1 );

            if ( ! $this->validate_prerequisites( $file_path ) ) return;
            $this->config = cob_get_unified_tax_importer_config()['taxonomies']['compound'];

            if( $force_images ) {
                WP_CLI::warning( "--- FORCE IMAGES mode is active. ---" );
            }
            if( $start_from_row > 1 ) {
                WP_CLI::warning( "--- RESUME mode is active. Starting from data row: $start_from_row ---" );
            }

            WP_CLI::log( "Caching all existing compound names for faster matching..." );
            $this->cache_all_compound_names();

            $csv_data = $this->read_csv( $file_path );
            if ( ! $csv_data ) return;

            $total_rows_to_process = count( $csv_data['rows'] );
            WP_CLI::log( sprintf( "Found %d total data rows. Starting Smart Link process...", $total_rows_to_process ) );
            $progress = \WP_CLI\Utils\make_progress_bar( 'Linking Compounds', $total_rows_to_process );

            $current_data_row = 1;
            foreach ( $csv_data['rows'] as $raw_row_data ) {

                if( $current_data_row < $start_from_row ) {
                    $current_data_row++;
                    $progress->tick();
                    continue;
                }

                $file_line_number = $current_data_row + 1;

                if ( count( $csv_data['headers'] ) !== count( $raw_row_data ) ) {
                    WP_CLI::warning( "Skipping line {$file_line_number}: Column count mismatch." );
                } else {
                    $csv_row = array_combine( $csv_data['headers'], $raw_row_data );
                    $this->process_single_row( $csv_row, $lang, $file_line_number, $force_images );
                }

                $current_data_row++;
                $progress->tick();
            }

            $progress->finish();
            WP_CLI::success( "Process completed!" );
        }

        private function process_single_row( $csv_row, $lang, $row_num, $force_images ) {
            $map = $this->config['csv_column_map_' . $lang];
            $source_id = trim( $csv_row[ $map['id'] ] ?? '' );
            $csv_name = trim( $csv_row[ $map['name'] ] ?? '' );

            if ( empty( $source_id ) || empty( $csv_name ) ) {
                WP_CLI::warning( "Skipping row {$row_num}: Missing Source ID or Name in CSV." );
                return;
            }

            $compound_term = $this->find_compound_term_fuzzy( $source_id, $csv_name, $lang );

            if ( ! $compound_term ) {
                WP_CLI::warning( "Skipping row {$row_num}: Could not find a unique match for '{$csv_name}'." );
                return;
            }

            $this->fix_link_and_log_term( $compound_term, $csv_row, $source_id, $lang, $row_num, $force_images );
        }

        private function find_compound_term_fuzzy( $source_id, $csv_name, $lang ) {
            // Attempt 1: Find by source_id
            $terms = get_terms(['taxonomy' => $this->config['taxonomy_slug'], 'meta_key' => $this->config['source_id_meta_key'], 'meta_value' => $source_id, 'hide_empty' => false, 'lang' => $lang]);
            if ( ! is_wp_error($terms) && ! empty($terms) ) return $terms[0];

            // Attempt 2: Find by exact name match
            $terms = get_terms(['taxonomy' => $this->config['taxonomy_slug'], 'name' => $csv_name, 'hide_empty' => false, 'lang' => $lang]);
            if ( ! is_wp_error($terms) && ! empty($terms) ) return $terms[0];

            // Attempt 3: Fuzzy match
            $found_terms = [];
            foreach ( $this->all_compound_names as $term_id => $real_name ) {
                if( empty($real_name) ) continue;
                if ( preg_match( '/\b' . preg_quote($real_name, '/') . '\b/i', $csv_name ) ) {
                    $found_terms[$term_id] = $real_name;
                }
            }

            if ( count($found_terms) === 1 ) {
                $found_id = key($found_terms);
                $found_name = current($found_terms);
                WP_CLI::log( "Fuzzy Match Found: Matched '{$csv_name}' to existing compound '{$found_name}' (ID: {$found_id})." );
                return get_term($found_id);
            }

            return null;
        }

        private function fix_link_and_log_term( $term, $csv_row, $source_id, $lang, $row_num, $force_images ) {
            $wp_term_id = $term->term_id;
            $map = $this->config['csv_column_map_' . $lang];

            update_term_meta( $wp_term_id, $this->config['source_id_meta_key'], $source_id );

            $developer_name = trim( $csv_row[ $map['developer_name_csv_col'] ] ?? '' );
            if ( $developer_name ) {
                $dev_term_id = cob_get_or_create_term_for_linking( $developer_name, $this->config['developer_taxonomy_slug'], $lang );
                if( $dev_term_id ) update_term_meta( $wp_term_id, $this->config['developer_meta_key'], $dev_term_id );
            }

            $city_name = trim( $csv_row[ $map['city_name_csv_col'] ] ?? '' );
            if ( $city_name ) {
                $city_term_id = cob_get_or_create_term_for_linking( $city_name, $this->config['city_taxonomy_slug'], $lang );
                if( $city_term_id ) update_term_meta( $wp_term_id, $this->config['city_meta_key'], $city_term_id );
            }

            $log_entry = cob_get_term_import_log_entry($source_id, $this->config['taxonomy_slug'], $lang);
            $should_download_images = $force_images || !$log_entry || !$log_entry->images_downloaded;
            $images_were_updated = false;

            if ( $should_download_images ) {
                WP_CLI::log(" -> Row {$row_num}: Downloading images for '{$term->name}'...");

                // Cover Image
                $cover_image_url = trim($csv_row[$map['cover_image_url_csv_col']] ?? '');
                if ($cover_image_url && filter_var($cover_image_url, FILTER_VALIDATE_URL)) {
                    $att_id = media_sideload_image($cover_image_url, 0, $term->name, 'id');
                    if (!is_wp_error($att_id)) {
                        update_term_meta($wp_term_id, $this->config['cover_image_meta_key'], $att_id);
                        if(function_exists('pll_set_post_language')) pll_set_post_language($att_id, $lang);
                        $images_were_updated = true;
                    }
                }

                // Gallery Images
                $gallery_ids = [];
                for ($i = 0; $i < ($map['gallery_img_count'] ?? 0); $i++) {
                    $img_url_key = $map['gallery_img_base_col'] . '[' . $i . ']';
                    $img_url = trim($csv_row[$img_url_key] ?? '');
                    if($img_url && filter_var($img_url, FILTER_VALIDATE_URL)) {
                        $att_id = media_sideload_image($img_url, 0, $term->name . ' Gallery', 'id');
                        if(!is_wp_error($att_id)) {
                            $gallery_ids[] = $att_id;
                            if(function_exists('pll_set_post_language')) pll_set_post_language($att_id, $lang);
                        }
                    }
                }
                if(!empty($gallery_ids)) {
                    update_term_meta($wp_term_id, $this->config['gallery_images_meta_key'], $gallery_ids);
                    $images_were_updated = true;
                }
            }

            $source_url = sanitize_url(trim($csv_row[$map['source_url_col']] ?? ''));
            $final_image_status = $images_were_updated || ($log_entry && $log_entry->images_downloaded);
            cob_log_term_import_status($source_id, $wp_term_id, $this->config['taxonomy_slug'], $lang, $source_url, $final_image_status, 'completed');

            $log_message = "Row {$row_num}: Fixed & linked '{$term->name}' (ID: {$wp_term_id}).";
            if ($images_were_updated) {
                $log_message .= " Images forcefully updated.";
            }
            WP_CLI::success( $log_message );
        }

        private function validate_prerequisites( $file_path ) {
            if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
                WP_CLI::error( "File not found or is not readable at: " . $file_path );
                return false;
            }
            if( ! function_exists('cob_get_unified_tax_importer_config') || ! function_exists('cob_get_or_create_term_for_linking') ) {
                WP_CLI::error( "A required helper function is missing. Ensure the main unified importer file and its helpers are loaded by your theme." );
                return false;
            }
            return true;
        }

        private function read_csv( $file_path ) {
            $handle = @fopen( $file_path, 'r' );
            if ( ! $handle ) {
                WP_CLI::error( "Could not open the CSV file." );
                return null;
            }
            $headers = array_map( 'trim', fgetcsv( $handle ) );
            $rows = [];
            while( ( $row = fgetcsv( $handle ) ) !== false ) {
                $rows[] = $row;
            }
            fclose( $handle );

            if ( empty( $rows ) ) {
                WP_CLI::success( "CSV file is empty." );
                return null;
            }
            return [ 'headers' => $headers, 'rows' => $rows ];
        }

        private function cache_all_compound_names() {
            $all_terms = get_terms([
                'taxonomy'   => 'compound',
                'hide_empty' => false,
                'fields'     => 'id=>name',
            ]);
            if ( ! is_wp_error($all_terms) ) {
                $this->all_compound_names = $all_terms;
            }
        }
    }

    WP_CLI::add_command( 'cob smart-link-compounds', 'COB_Smart_Link_Compounds_Command' );
}