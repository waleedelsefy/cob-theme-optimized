<?php
/**
 * Taxonomy Compound Featured Template Part with JavaScript Pagination
 *
 * This refactored version improves upon the original by:
 * 1. Centralizing JavaScript logic and parameters.
 * 2. Removing redundant PHP pagination rendering in favor of a pure JS solution for consistency.
 * 3. Eliminating duplicated SVG icon code (DRY principle).
 * 4. Preparing for proper script localization (wp_localize_script).
 * 5. Adding robust JSDoc comments and clearer structure.
 * 6. FIX: Corrected a JS syntax error that prevented the script from running.
 * 7. FIX: Added dynamic update for the results count.
 * 8. IMPROVEMENT: JavaScript now fully controls the visibility of the pagination block.
 * 9. UPDATE: Changed the price sorting meta key to 'max_price'.
 *
 * @package Capital_of_Business
 */

// Get the current term object from the queried page.
$term = get_queried_object();

// Retrieve and sanitize the sort option from the URL, defaulting to 'date_desc'.
$sort_option = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';

// Define WP_Query arguments based on the selected sort option.
$args = [
    'post_type'      => 'properties',
    'posts_per_page' => 6,
    'paged'          => 1, // Always start with the first page on initial load.
    'post_status'    => 'publish',
    'tax_query'      => [
        [
            'taxonomy' => $term->taxonomy,
            'field'    => 'slug',
            'terms'    => $term->slug,
        ],
    ],
];

// Set orderby parameters based on the sort option.
switch ($sort_option) {
    case 'price_asc':
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'ASC';
        $args['meta_key'] = 'max_price'; // Changed from 'price'
        break;
    case 'price_desc':
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
        $args['meta_key'] = 'max_price'; // Changed from 'price'
        break;
    case 'date_asc':
        $args['orderby']  = 'date';
        $args['order']    = 'ASC';
        break;
    default: // 'date_desc'
        $args['orderby']  = 'date';
        $args['order']    = 'DESC';
        break;
}

// Execute the initial query to load the first page of properties.
$properties_query = new WP_Query($args);
$total_results    = $properties_query->found_posts;
$max_pages        = $properties_query->max_num_pages;

// Placeholder image URI.
$placeholder = get_template_directory_uri() . '/assets/imgs/placeholder.jpg';
?>

<section class="pagination-section pagination-city pagination-brand">
    <div class="container">
        <div class="top-compounds">
            <div class="right-compounds">
                <!-- Header displays "Discover [Taxonomy Term Name] Units" -->
                <h3 class="head"><?php echo sprintf(esc_html__('Discover %s Units', 'cob_theme'), esc_html($term->name)); ?></h3>
                <!-- Display the dynamic property count -->
                <h5 class="results-count" id="results-count"><?php echo esc_html($total_results); ?> <?php esc_html_e('results', 'cob_theme'); ?></h5>
            </div>
            <div class="tabs-btn">
                <div class="select-tabs">
                    <select class="sort-select" id="sort-select" aria-label="<?php esc_attr_e('Sort properties by', 'cob_theme'); ?>">
                        <option value="date_desc" <?php selected($sort_option, 'date_desc'); ?>>
                            <?php esc_html_e('Sort By', 'cob_theme'); ?>
                        </option>
                        <option value="price_asc" <?php selected($sort_option, 'price_asc'); ?>>
                            <?php esc_html_e('Price: Low to High', 'cob_theme'); ?>
                        </option>
                        <option value="price_desc" <?php selected($sort_option, 'price_desc'); ?>>
                            <?php esc_html_e('Price: High to Low', 'cob_theme'); ?>
                        </option>
                        <option value="date_asc" <?php selected($sort_option, 'date_asc'); ?>>
                            <?php esc_html_e('Date: Oldest', 'cob_theme'); ?>
                        </option>
                        <option value="date_desc" <?php selected($sort_option, 'date_desc'); ?>>
                            <?php esc_html_e('Date: Newest', 'cob_theme'); ?>
                        </option>
                    </select>
                    <span class="arrow">
                        <!-- Down arrow icon -->
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M15.7146 0C16.1879 0 16.5717 0.383759 16.5717 0.857141V16.5714L18.4574 14.0571C18.7414 13.6784 19.2787 13.6017 19.6574 13.8857C20.0362 14.1697 20.1128 14.7069 19.8288 15.0857L16.4003 19.6571C16.1789 19.9523 15.7935 20.0726 15.4435 19.956C15.0935 19.8393 14.8574 19.5117 14.8574 19.1428V0.857141C14.8574 0.383759 15.2412 0 15.7146 0Z" fill="black" />
                            <path d="M0 14.571C0 14.0976 0.383759 13.7139 0.857141 13.7139H11.1428C11.6162 13.7139 12 14.0976 12 14.571C12 15.0444 11.6162 15.4281 11.1428 15.4281H0.857141C0.383759 15.4281 0 15.0444 0 14.571Z" fill="black" />
                            <path opacity="0.7" d="M2.28613 8.85714C2.28613 8.38377 2.66989 8 3.14327 8H11.1433C11.6166 8 12.0004 8.38377 12.0004 8.85714C12.0004 9.33051 11.6166 9.71428 11.1433 9.71428H3.14327C2.66989 9.71428 2.28613 9.33051 2.28613 8.85714Z" fill="black" />
                            <path opacity="0.4" d="M4.57129 3.14327C4.57129 2.66989 4.95505 2.28613 5.42843 2.28613H11.1427C11.6161 2.28613 11.9998 2.66989 11.9998 3.14327C11.9998 3.61666 11.6161 4.00041 11.1427 4.00041H5.42843C4.95505 4.00041 4.57129 3.61666 4.57129 3.14327Z" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>

        <!-- Properties Cards Section -->
        <div class="properties-cards" id="properties-container">
            <?php if ($properties_query->have_posts()) : ?>
                <?php while ($properties_query->have_posts()) : $properties_query->the_post(); ?>
                    <?php get_template_part('template-parts/single/properties-card'); ?>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <p class="no-results"><?php esc_html_e('There are no posts currently available', 'cob_theme'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Pagination container. Visibility is controlled by JavaScript. -->
        <div class="pagination">
            <div class="nav-links" id="pagination-container"></div>
        </div>
    </div>
</section>

<?php
/**
 * NOTE: The following <script> tag should ideally be moved to a dedicated .js file 
 * and enqueued via your theme's functions.php using wp_enqueue_script().
 */
?>
<script>
    jQuery(document).ready(function($) {
        // --- CONFIGURATION ---
        
        // This object simulates the data that would be passed by `wp_localize_script`.
        // It centralizes all parameters passed from PHP to JavaScript.
        const cobPaginationParams = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('load_compound_properties_nonce'); ?>',
            initial_page: 1,
            initial_sort: '<?php echo esc_js($sort_option); ?>',
            max_pages: <?php echo (int) $max_pages; ?>,
            term_id: <?php echo (int) $term->term_id; ?>,
            taxonomy: '<?php echo esc_js($term->taxonomy); ?>',
            i18n: {
                previous: '<?php echo esc_js(__('Previous', 'cob_theme')); ?>',
                next: '<?php echo esc_js(__('Next', 'cob_theme')); ?>',
                loading: '<?php echo esc_js(__('Loading...', 'cob_theme')); ?>',
                error: '<?php echo esc_js(__('Error loading properties. Please try again.', 'cob_theme')); ?>',
                results: '<?php echo esc_js(__('results', 'cob_theme')); ?>'
            }
        };

        // Define SVG icons once to avoid duplicating them in the code.
        const svgPrev = '<svg width="9" height="14" viewBox="0 0 9 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.10212 6.99773L0.997038 1.89666C0.619477 1.5191 0.619477 0.908582 0.997038 0.53504C1.3746 0.161498 1.98512 0.161497 2.36268 0.53504L8.14656 6.31491C8.51208 6.68042 8.52011 7.26684 8.17468 7.6444L2.36669 13.4644C2.17791 13.6532 1.92888 13.7456 1.68387 13.7456C1.43886 13.7456 1.18983 13.6532 1.00105 13.4644C0.623496 13.0869 0.623496 12.4764 1.00105 12.1028L6.10212 6.99773Z" fill="black" /></svg>';
        const svgNext = '<svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.32561 7.00227L7.4307 12.1033C7.80826 12.4809 7.80826 13.0914 7.4307 13.465C7.05314 13.8385 6.44262 13.8385 6.06506 13.465L0.281172 7.68509C-0.084341 7.31958 -0.0923709 6.73316 0.253054 6.3556L6.06104 0.535562C6.24982 0.346784 6.49885 0.254402 6.74386 0.254402C6.98887 0.254402 7.2379 0.346784 7.42668 0.535562C7.80424 0.913122 7.80424 1.52364 7.42668 1.89719L2.32561 7.00227Z" fill="black" /></svg>';


        // --- STATE MANAGEMENT ---
        let currentPage = cobPaginationParams.initial_page;
        let currentSort = cobPaginationParams.initial_sort;
        let maxPages = cobPaginationParams.max_pages;
        let isLoading = false;

        // --- DOM ELEMENTS ---
        const $container = $('#properties-container');
        const $paginationContainer = $('#pagination-container');
        const $sortSelect = $('#sort-select');
        const $resultsCount = $('#results-count');


        /**
         * Builds and renders the pagination controls.
         * It is the single source of truth for the pagination UI's visibility and content.
         */
        function updatePagination() {
            const $paginationWrapper = $paginationContainer.parent('.pagination');

            if (maxPages <= 1) {
                $paginationWrapper.hide();
                $paginationContainer.empty();
                return;
            }
            
            $paginationWrapper.show();
            
            let html = '';
            const pageWindow = 1; // Number of pages to show around the current page.

            // Previous Button
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            html += `<a class="prev page-numbers ${prevDisabled}" href="#" data-page="${currentPage - 1}">${svgPrev} ${cobPaginationParams.i18n.previous}</a>`;

            // Page numbers logic
            const startPage = Math.max(2, currentPage - pageWindow);
            const endPage = Math.min(maxPages - 1, currentPage + pageWindow);

            // First page link
            html += `<a class="page-numbers ${currentPage === 1 ? 'current' : ''}" href="#" data-page="1">1</a>`;
            
            if (startPage > 2) {
                 html += '<span class="page-numbers dots">…</span>';
            }

            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += `<span aria-current="page" class="page-numbers current">${i}</span>`;
                } else {
                    html += `<a class="page-numbers" href="#" data-page="${i}">${i}</a>`;
                }
            }
            
            if (endPage < maxPages - 1) {
                 html += '<span class="page-numbers dots">…</span>';
            }

            if (maxPages > 1) {
                html += `<a class="page-numbers ${currentPage === maxPages ? 'current' : ''}" href="#" data-page="${maxPages}">${maxPages}</a>`;
            }

            // Next Button
            const nextDisabled = currentPage === maxPages ? 'disabled' : '';
            html += `<a class="next page-numbers ${nextDisabled}" href="#" data-page="${currentPage + 1}">${cobPaginationParams.i18n.next} ${svgNext}</a>`;

            $paginationContainer.html(html);
        }

        /**
         * Fetches properties from the server via AJAX.
         * @param {number} page The page number to load.
         * @param {string} sort The sort order to apply.
         */
        function loadProperties(page, sort) {
            if (isLoading) return;
            isLoading = true;

            $container.css('opacity', 0.5).append(`<div class="loading-indicator">${cobPaginationParams.i18n.loading}</div>`);

            $.ajax({
                url: cobPaginationParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_compound_properties',
                    page: page,
                    sort: sort,
                    term_id: cobPaginationParams.term_id,
                    taxonomy: cobPaginationParams.taxonomy,
                    nonce: cobPaginationParams.nonce
                },
                success: function(response) {
                    if (response.success) {
                        currentPage = parseInt(page);
                        currentSort = sort;
                        maxPages = parseInt(response.data.max_pages);

                        $container.html(response.data.html);

                        if (typeof response.data.total_results !== 'undefined') {
                             $resultsCount.text(response.data.total_results + ' ' + cobPaginationParams.i18n.results);
                        }

                        updatePagination();
                        updateURL(page, sort);
                    } else {
                        $container.html(`<p class="no-results">${response.data.message || cobPaginationParams.i18n.error}</p>`);
                    }
                },
                error: function() {
                    $container.html(`<p class="no-results">${cobPaginationParams.i18n.error}</p>`);
                },
                complete: function() {
                    isLoading = false;
                    $container.css('opacity', 1).find('.loading-indicator').remove();
                }
            });
        }

        /**
         * Updates the browser URL without a page reload.
         * @param {number} page The current page number.
         * @param {string} sort The current sort order.
         */
        function updateURL(page, sort) {
            if (window.history.pushState) {
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sort);
                window.history.pushState({ page: page, sort: sort }, '', url.toString());
            }
        }

        // --- EVENT HANDLERS ---
        
        $paginationContainer.on('click', 'a.page-numbers', function(e) {
            e.preventDefault();
            const $this = $(this);
            if ($this.hasClass('disabled') || $this.hasClass('current')) {
                return;
            }
            const page = parseInt($this.data('page'));
            if (!isNaN(page)) {
                loadProperties(page, currentSort);
            }
        });

        $sortSelect.on('change', function() {
            const newSort = $(this).val();
            loadProperties(1, newSort);
        });

        $(window).on('popstate', function(e) {
            const state = e.originalEvent.state;
            if (state) {
                loadProperties(state.page, state.sort);
            } else {
                loadProperties(cobPaginationParams.initial_page, cobPaginationParams.initial_sort);
            }
        });

        // --- INITIALIZATION ---
        // updatePagination is called directly. It contains the logic to show/hide itself.
        updatePagination();
    });
</script