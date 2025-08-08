jQuery(document).ready(function($) {

    var loading = false; // Flag to prevent multiple AJAX requests at the same time.
    var container = $('#similar-units-container');

    // Check if the container exists on the page.
    if (container.length === 0) {
        return;
    }

    $(window).on('scroll', function() {
        var page = parseInt(container.attr('data-page'));
        var maxPages = parseInt(container.attr('data-max-pages'));

        // If we are already loading or have reached the last page, do nothing.
        if (loading || page >= maxPages) {
            return;
        }

        // Check if the user has scrolled to the bottom of the container.
        // The '200' provides a buffer so loading starts a bit before the user hits the absolute bottom.
        if ($(window).scrollTop() >= container.offset().top + container.outerHeight() - window.innerHeight - 200) {

            loading = true;
            $('#similar-units-loader').show(); // Show the loader element.

            // Prepare data for the AJAX request from data attributes.
            var ajaxData = {
                action: 'load_more_similar_units',
                nonce: cob_ajax_obj.nonce, // Nonce passed from WordPress.
                page: page,
                post_id: container.data('post-id'),
                search_by: container.data('search-by'),
                search_term: container.data('search-term')
            };

            // Add language if it's available in the localized object.
            if (cob_ajax_obj.lang) {
                ajaxData.lang = cob_ajax_obj.lang;
            }

            $.ajax({
                url: cob_ajax_obj.ajax_url, // URL passed from WordPress.
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        // Append the new content and update the page number.
                        container.append(response.data.html);
                        container.attr('data-page', page + 1);
                    } else {
                        // If there are no more posts, hide the loader.
                        $('#similar-units-loader').hide();
                    }
                    loading = false;
                },
                error: function() {
                    $('#similar-units-loader').hide();
                    loading = false;
                }
            });
        }
    });
});
