document.addEventListener('DOMContentLoaded', () => {
    const searchContainer = document.getElementById('cob-search-app');
    if (!searchContainer) {
        return;
    }

    const pluginParams = typeof cob_search_params !== 'undefined' ? cob_search_params : {
        //api_url: 'https://cob.com.eg/wp-json/cob-search/v1/',
        api_url: 'http://cob.io/wp-json/cob-search/v1/',
        //search_results_page_url: 'https://cob.com.eg/',
        search_results_page_url: 'http://cob.io/',
        settings: {
            smart_search_enabled: false,
            ai_summary_enabled: false
        },
        strings: {
            loading_results: 'Loading results...',
            error_loading_results: 'An error occurred while loading search results. Please try again.',
            api_url_not_configured: 'Search initialization failed: API URL not found. Please check plugin settings.',
            autocomplete_failed: 'Autocomplete failed: API URL not found.',
            no_results_found: 'No results found for',
            no_suggestions_for: 'No suggestions for',
            previous: 'Previous',
            next: 'Next',
            ai_summary_title: 'AI Search Summary:',
            view_details: 'View Details',
            clear_search: 'Clear Search',
            no_matching_filter_results: 'No results matching the selected filters.',
            select_property_type: 'Select Property Type',
            bedrooms: 'Bedrooms',
            price: 'Price',
            default_placeholder_image_url: 'https://placehold.co/200x150/cccccc/ffffff?text=No+Image',
        },
        current_lang: 'ar',
    };

    // Main UI elements
    const searchInput = document.getElementById('basicSearchInput');
    const autocompleteContainer = document.getElementById('cob-autocomplete-suggestions');
    const searchButton = document.getElementById('basicSearchButton');
    const propertyTypeSelect = document.getElementById('basicPropertyType');
    const bedroomsSelect = document.getElementById('basicBedrooms');
    const priceSelect = document.getElementById('basicPrice');
    const searchResultsContainer = document.getElementById('search-results');
    const paginationContainer = document.getElementById('cob-pagination');
    const aiSummaryContainer = document.getElementById('cob-ai-summary');

    let currentSearchQuery = '';
    let currentFilters = {};
    let debounceTimeout;
    let allAvailableFacetsData = {};

    const priceValueMap = {
        '0-5000000': '0 - 5,000,000',
        '5000000-9999999': '5,000,000 - 9,999,999',
        '10000000-15000000': '10,000,000 - 15,000,000',
        '15000000+': '15,000,000+',
        '': '',
    };
    const reversePriceValueMap = Object.fromEntries(Object.entries(priceValueMap).map(([key, value]) => [value, key]));

    function getLangFromUrl() {
        const pathSegments = window.location.pathname.split('/').filter(segment => segment);
        let lang = 'en';
        if (pathSegments.length > 0) {
            const firstSegment = pathSegments[0].toLowerCase();
            if (firstSegment === 'ar' || firstSegment === 'en') {
                lang = firstSegment;
            }
        }
        return lang;
    }

    function formatFiltersForAPI(filters) {
        const formatted = [];
        for (const attribute in filters) {
            if (filters.hasOwnProperty(attribute)) {
                filters[attribute].forEach(value => {
                    if (value) {
                        formatted.push(`${attribute}:${value}`);
                    }
                });
            }
        }
        return formatted;
    }

    async function performSearch(query, filters, page = 0, hitsPerPage = 5, redirect = false) {
        if (redirect) {
            const params = new URLSearchParams();
            params.append('s', query);
            const formattedFilters = formatFiltersForAPI(filters);
            formattedFilters.forEach(f => params.append('filters[]', f));
            params.append('lang', getLangFromUrl());
            const redirectUrl = `${pluginParams.search_results_page_url}?${params.toString()}`;
            window.location.href = redirectUrl;
            return;
        }

        // <<<--- THIS IS THE UPDATED PART ---<<<
        // Show a styled loader instead of simple text.
        searchResultsContainer.innerHTML = `
            <div class="cob-loader-container">
                <div class="loader" id="loader-2">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>`;

        paginationContainer.innerHTML = '';
        aiSummaryContainer.innerHTML = '';
        searchResultsContainer.style.display = 'block';

        if (!pluginParams.api_url) {
            console.error("COB Search: API URL is not configured.");
            searchResultsContainer.innerHTML = `<div class="error-message">${pluginParams.strings.api_url_not_configured}</div>`;
            return;
        }

        const isSmartSearchEnabled = pluginParams.settings.smart_search_enabled;
        const endpoint = isSmartSearchEnabled ? 'smart-search' : 'search';
        const apiUrl = pluginParams.api_url + endpoint;
        const formattedFilters = formatFiltersForAPI(filters);
        const currentLang = getLangFromUrl();

        const body = {
            query: query,
            filters: formattedFilters,
            page: page,
            hitsPerPage: hitsPerPage,
            lang: currentLang,
        };

        try {
            const response = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            const data = await response.json();
            renderSearchResults(data.hits, data.query, filters);
            if (pluginParams.settings.ai_summary_enabled && data.ai_summary) {
                aiSummaryContainer.innerHTML = `<div class="ai-summary-box"><h3>${pluginParams.strings.ai_summary_title}</h3><p>${data.ai_summary}</p></div>`;
            } else {
                aiSummaryContainer.innerHTML = '';
            }
        } catch (error) {
            console.error('An error occurred during search:', error);
            searchResultsContainer.innerHTML = `<div class="error-message">${pluginParams.strings.error_loading_results}</div>`;
            aiSummaryContainer.innerHTML = '';
        }
    }

    function renderSearchResults(hits, query, activeFilters) {
        if (hits && hits.length > 0) {
            searchResultsContainer.innerHTML = hits.map(hit => {
                const thumbnailUrl = hit.thumbnail_url || pluginParams.strings.default_placeholder_image_url;
                return `
                <article class="hit-item">
                    <div class="hit-thumbnail">
                        <img src="${thumbnailUrl}" alt="${hit.title || 'No Image'}" onerror="this.onerror=null; this.src='${pluginParams.strings.default_placeholder_image_url}';" />
                    </div>
                      <a class="hit-content" href="${hit.permalink}">
                        <div class="search-result-title">${highlightText(hit.title, query)}</div>
                        <p class="search-result-excerpt">${hit.excerpt ? highlightText(hit.excerpt, query) : ''}</p>
                     </a>
                </article>
            `;
            }).join('');
        } else {
            const hasActiveFilters = Object.keys(activeFilters).length > 0;
            if (query.length > 0) {
                searchResultsContainer.innerHTML = `<div class="no-results-found">${pluginParams.strings.no_results_found} <strong>"${query}"</strong>.</div>`;
            } else if (hasActiveFilters) {
                searchResultsContainer.innerHTML = `<div class="no-results-found">${pluginParams.strings.no_matching_filter_results}</div>`;
            } else {
                searchResultsContainer.innerHTML = '';
            }
        }
    }

    function highlightText(text, query) {
        if (!query || query.length === 0) {
            return text;
        }
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            const newQuery = searchInput.value.trim();
            if (newQuery.length >= 3) {
                currentSearchQuery = newQuery;
                performSearch(currentSearchQuery, currentFilters, 0, 5);
                fetchAutocompleteSuggestions(newQuery);
            } else if (newQuery.length === 0) {
                resetSearchUI();
                currentSearchQuery = '';
            } else {
                searchResultsContainer.innerHTML = '';
                paginationContainer.innerHTML = '';
                autocompleteContainer.innerHTML = '';
                aiSummaryContainer.innerHTML = '';
                currentSearchQuery = newQuery;
            }
        }, 300);
    });

    async function fetchAutocompleteSuggestions(query) {
        if (query.length === 0) {
            autocompleteContainer.innerHTML = '';
            return;
        }
        if (!pluginParams.api_url) {
            console.error("COB Search: API URL is not configured.");
            autocompleteContainer.innerHTML = `<div class="error-message">${pluginParams.strings.autocomplete_failed}</div>`;
            return;
        }
        const apiUrl = pluginParams.api_url + 'search';
        const body = { query: query, filters: [], page: 0, hitsPerPage: 5, lang: getLangFromUrl() };
        try {
            const response = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            const data = await response.json();
            renderAutocompleteSuggestions(data.hits, query);
        } catch (error) {
            console.error('An error occurred while fetching suggestions:', error);
            autocompleteContainer.innerHTML = '';
        }
    }

    function renderAutocompleteSuggestions(suggestions, currentQuery) {
        if (suggestions && suggestions.length > 0) {
            autocompleteContainer.innerHTML = `
                <ul class="autocomplete-list">
                    ${suggestions.map(suggestion => `
                        <li>
                            <a href="${suggestion.permalink}" class="autocomplete-suggestion-item" data-title="${suggestion.title}">
                                ${highlightText(suggestion.title, currentQuery)}
                            </a>
                        </li>
                    `).join('')}
                </ul>
            `;
        } else if (currentQuery.length > 0) {
            autocompleteContainer.innerHTML = `<div class="autocomplete-no-results">${pluginParams.strings.no_suggestions_for} "${currentQuery}"</div>`;
        } else {
            autocompleteContainer.innerHTML = '';
        }
    }

    autocompleteContainer.addEventListener('click', event => {
        const targetLink = event.target.closest('a.autocomplete-suggestion-item');
        if (targetLink) {
            const selectedTitle = targetLink.dataset.title;
            searchInput.value = selectedTitle;
            autocompleteContainer.innerHTML = '';
            currentSearchQuery = selectedTitle;
            performSearch(currentSearchQuery, currentFilters, 0, 5);
        }
    });

    document.addEventListener('click', (event) => {
        if (!searchInput.contains(event.target) && !autocompleteContainer.contains(event.target)) {
            autocompleteContainer.innerHTML = '';
        }
    });

    function renderDynamicFacets(facetsData, currentActiveFilters) {
        const filterConfigs = {
            'taxonomies.type': { element: propertyTypeSelect, defaultLabel: pluginParams.strings.select_property_type },
            'custom_fields.bedrooms': { element: bedroomsSelect, defaultLabel: pluginParams.strings.bedrooms },
            'custom_fields.price_range': { element: priceSelect, defaultLabel: pluginParams.strings.price, reverseValueMap: reversePriceValueMap },
        };
        for (const attribute in filterConfigs) {
            const config = filterConfigs[attribute];
            const selectElement = config.element;
            if (selectElement) {
                selectElement.innerHTML = `<option value="">${config.defaultLabel}</option>`;
                if (facetsData[attribute] && facetsData[attribute].length > 0) {
                    facetsData[attribute].forEach(item => {
                        const option = document.createElement('option');
                        option.value = config.reverseValueMap ? config.reverseValueMap[item.value] : item.value;
                        option.textContent = `${item.label} (${item.count})`;
                        selectElement.appendChild(option);
                    });
                    const activeFilterValue = currentActiveFilters[attribute] ? currentActiveFilters[attribute][0] : '';
                    selectElement.value = activeFilterValue ? (config.reverseValueMap ? config.reverseValueMap[activeFilterValue] : activeFilterValue) : '';
                    selectElement.disabled = false;
                } else {
                    selectElement.disabled = true;
                }
            }
        }
    }

    function handleSelectFilterChange(selectElement, attribute, valueMap = null) {
        selectElement.addEventListener('change', () => {
            const selectedHtmlValue = selectElement.value;
            const refinedIndexValue = valueMap ? valueMap[selectedHtmlValue] : selectedHtmlValue;
            if (refinedIndexValue) {
                currentFilters[attribute] = [refinedIndexValue];
            } else {
                delete currentFilters[attribute];
            }
            performSearch(currentSearchQuery, currentFilters, 0, 5);
        });
    }

    if (propertyTypeSelect) handleSelectFilterChange(propertyTypeSelect, 'taxonomies.type');
    if (bedroomsSelect) handleSelectFilterChange(bedroomsSelect, 'custom_fields.bedrooms');
    if (priceSelect) handleSelectFilterChange(priceSelect, 'custom_fields.price_range', priceValueMap);

    function resetSearchUI() {
        searchInput.value = '';
        if (propertyTypeSelect) propertyTypeSelect.value = '';
        if (bedroomsSelect) bedroomsSelect.value = '';
        if (priceSelect) priceSelect.value = '';
        currentSearchQuery = '';
        currentFilters = {};
        searchResultsContainer.innerHTML = '';
        searchResultsContainer.style.display = 'none';
        paginationContainer.innerHTML = '';
        autocompleteContainer.innerHTML = '';
        aiSummaryContainer.innerHTML = '';
        fetchInitialFacets();
    }

    if (searchButton) {
        searchButton.addEventListener('click', () => {
            const currentQuery = searchInput.value.trim();
            if (currentQuery === '' && Object.keys(currentFilters).length === 0) {
                resetSearchUI();
            } else {
                currentSearchQuery = currentQuery;
                performSearch(currentSearchQuery, currentFilters, 0, 5, true);
            }
            autocompleteContainer.innerHTML = '';
        });
    }

    const allDropdownButtons = document.querySelectorAll('.cob-filter-button');
    allDropdownButtons.forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            const optionsContainer = button.nextElementSibling;
            const isAlreadyOpen = optionsContainer.classList.contains('open');
            document.querySelectorAll('.cob-filter-options.open').forEach(openContainer => {
                openContainer.classList.remove('open');
            });
            if (!isAlreadyOpen) {
                optionsContainer.classList.add('open');
            }
        });
    });

    window.addEventListener('click', () => {
        document.querySelectorAll('.cob-filter-options.open').forEach(openContainer => {
            openContainer.classList.remove('open');
        });
    });

    const toggleButton = document.getElementById('toggleButton');
    const searchTitle = document.getElementById('searchTitle');
    const basicSearchForm = document.getElementById('basicSearchForm');
    const detailedSearchInput = document.getElementById('detailedSearchInput');
    const sliderIcon = document.getElementById('sliderIcon');
    const closeIcon = document.getElementById('closeIcon');
    const SearchTitle2 = document.getElementById('SearchTitle2');

    if (toggleButton && searchTitle && basicSearchForm && detailedSearchInput && sliderIcon && closeIcon && SearchTitle2) {
        detailedSearchInput.style.display = 'none';
        closeIcon.style.display = 'none';
        toggleButton.addEventListener('click', () => {
            const isBasicVisible = basicSearchForm.style.display !== 'none';
            if (isBasicVisible) {
                basicSearchForm.style.display = 'none';
                searchTitle.style.display = 'block';
                detailedSearchInput.style.display = 'block';
                sliderIcon.style.display = 'none';
                closeIcon.style.display = 'block';
                SearchTitle2.style.display = 'none';
            } else {
                basicSearchForm.style.display = 'block';
                searchTitle.style.display = 'none';
                detailedSearchInput.style.display = 'none';
                sliderIcon.style.display = 'block';
                closeIcon.style.display = 'none';
                SearchTitle2.style.display = 'block';
                resetSearchUI();
            }
        });
    }

    async function fetchInitialFacets() {
        if (!pluginParams.api_url) {
            console.error("COB Search: API URL is not configured.");
            return;
        }
        const currentLang = getLangFromUrl();
        const apiUrl = `${pluginParams.api_url}facets/${currentLang}/`;
        try {
            const response = await fetch(apiUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
            const data = await response.json();
            allAvailableFacetsData = data;
            renderDynamicFacets(allAvailableFacetsData, currentFilters);
        } catch (error) {
            console.error('An error occurred while fetching initial facets:', error);
        }
    }

    fetchInitialFacets();
});
