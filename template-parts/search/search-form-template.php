<?php
/**
 * Template for the COB Search Form.
 * This template is loaded by the [cob_search_form] shortcode.
 * It provides a unified interface for basic search, filtering, and live results.
 *
 * @package Capital_of_Business_Theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="cob-search-app">
    <div class="search-bar">
        <h3 class="head"><?php esc_html_e( 'Find your property', 'cob_theme' ); ?></h3>

        <form id="basicSearchForm" class="basic-search-form" onsubmit="return false;" novalidate>
            <div class="search-bar-content">
                <!-- Main Search Input -->
                <div class="search-form">
                    <svg class="search-form-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7.66634 2.08301C4.58275 2.08301 2.08301 4.58275 2.08301 7.66634C2.08301 10.7499 4.58275 13.2497 7.66634 13.2497C10.7499 13.2497 13.2497 10.7499 13.2497 7.66634C13.2497 4.58275 10.7499 2.08301 7.66634 2.08301ZM0.583008 7.66634C0.583008 3.75432 3.75432 0.583008 7.66634 0.583008C11.5784 0.583008 14.7497 3.75432 14.7497 7.66634C14.7497 11.5784 11.5784 14.7497 7.66634 14.7497C3.75432 14.7497 0.583008 11.5784 0.583008 7.66634Z" fill="#081945" />
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12.8027 12.8027C13.0956 12.5098 13.5704 12.5098 13.8633 12.8027L15.1967 14.136C15.4896 14.4289 15.4896 14.9038 15.1967 15.1967C14.9038 15.4896 14.4289 15.4896 14.136 15.1967L12.8027 13.8633C12.5098 13.5704 12.5098 13.0956 12.8027 12.8027Z" fill="#081945" />
                    </svg>
                    <input type="text" id="basicSearchInput" name="basic_search" placeholder="<?php esc_attr_e( 'Search by compound, location, or developer...', 'cob_theme' ); ?>" value="" autocomplete="off" />
                    <!-- Autocomplete suggestions will be populated here by JS -->
                    <div id="cob-autocomplete-suggestions"></div>
                </div>

                <!-- Filter Dropdowns -->
                <ul class="nav-menu2">
                    <li>
                        <select id="basicPropertyType" name="basic_propertie_type">
                            <option value=""><?php esc_html_e( 'Select Property Type', 'cob_theme' ); ?></option>
                        </select>
                    </li>
                    <li>
                        <select id="basicBedrooms" name="bedrooms">
                            <option value=""><?php esc_html_e( 'Bedrooms', 'cob_theme' ); ?></option>
                        </select>
                    </li>
                    <li>
                        <select id="basicPrice" name="filter_price">
                            <option value=""><?php esc_html_e( 'Price', 'cob_theme' ); ?></option>
                        </select>
                    </li>
                    <li>
                        <button type="button" id="basicSearchButton">
                            <?php esc_html_e( 'Search', 'cob_theme' ); ?>
                        </button>
                    </li>
                </ul>
            </div>
        </form>

        <!-- Container for AI-generated summary -->
        <div id="cob-ai-summary"></div>

        <!-- Container for live search results, hidden by default -->
        <div id="search-results" class="search-results" style="display: none;"></div>

        <!-- Container for pagination controls -->
        <div id="cob-pagination"></div>
    </div>
</div>
