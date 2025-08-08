jQuery(document).ready(function($) {

    const section = $('#developer-compounds-section');
    if (section.length === 0) {
        return;
    }

    let isLoading = false;
    const container = $('#compounds-list-container');
    const imagesContainer = $('#compounds-images-container');
    const loader = $('#compounds-loader');
    const loadMoreBtn = $('#load-more-compounds-btn');
    const loadMoreContainer = $('#load-more-compounds-container');

    function loadCompounds(page, sort, isSortChange = false) {
        if (isLoading) return;
        isLoading = true;
        loader.show();
        loadMoreBtn.hide();

        const termId = section.data('term-id');
        const taxonomy = section.data('taxonomy');

        $.ajax({
            url: cob_dev_compounds_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'load_developer_compounds',
                nonce: cob_dev_compounds_obj.nonce,
                page: page,
                sort: sort,
                term_id: termId,
                taxonomy: taxonomy,
            },
            success: function(response) {
                if (response.success) {
                    if (isSortChange) {
                        container.html(response.data.list_html || '<li class="no-results"><p>No projects available</p></li>');
                        imagesContainer.html(response.data.images_html);
                    } else {
                        container.append(response.data.list_html);
                    }

                    container.attr('data-page', response.data.page);
                    container.attr('data-max-pages', response.data.max_pages);

                    if (response.data.page >= response.data.max_pages) {
                        loadMoreContainer.hide();
                    } else {
                        loadMoreContainer.show();
                        loadMoreBtn.show();
                    }

                } else {
                    if (isSortChange) {
                        container.html('<li class="no-results"><p>No projects available</p></li>');
                    }
                    loadMoreContainer.hide();
                }
            },
            complete: function() {
                isLoading = false;
                loader.hide();
            }
        });
    }

    // Sorting button click handler
    $('.sort-button').on('click', function() {
        const $this = $(this);
        if ($this.hasClass('active') || isLoading) {
            return;
        }

        $('.sort-button').removeClass('active');
        $this.addClass('active');

        const sortBy = $this.data('sort');
        container.attr('data-page', '1'); // Reset page
        loadCompounds(1, sortBy, true);
    });

    // Load more button click handler
    loadMoreBtn.on('click', function() {
        const currentPage = parseInt(container.attr('data-page')) || 1;
        const sortBy = $('.sort-button.active').data('sort');
        loadCompounds(currentPage + 1, sortBy, false);
    });

});
