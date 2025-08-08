/**
 * Handles the "Load More" functionality for developers.
 *
 * This script listens for a click on the "Load More" button,
 * sends an AJAX request to WordPress to fetch the next set of developers,
 * and appends them to the container.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find the load more button on the page.
    const loadMoreBtn = document.getElementById('load-more-developers');

    // If the button doesn't exist, do nothing.
    if (!loadMoreBtn) {
        return;
    }

    // Add a click event listener to the button.
    loadMoreBtn.addEventListener('click', function() {
        const container = document.getElementById('developer-cards-container');
        const spinner = document.getElementById('loading-spinner');

        // Get the current page and total pages from the button's data attributes.
        let currentPage = parseInt(this.dataset.page, 10);
        const totalPages = parseInt(this.dataset.totalPages, 10);
        const perPage = parseInt(this.dataset.perPage, 10);

        // We want to fetch the *next* page.
        const nextPage = currentPage + 1;

        // Show the loading spinner and hide the button to prevent multiple clicks.
        loadMoreBtn.style.display = 'none';
        spinner.style.display = 'block';

        // Prepare the data to send with the AJAX request.
        const formData = new FormData();
        formData.append('action', 'load_more_developers'); // This corresponds to the PHP action hook `wp_ajax_load_more_developers`.
        formData.append('page', nextPage);
        formData.append('per_page', perPage);
        formData.append('nonce', cob_ajax_object.nonce); // The security nonce we passed from PHP.

        // Use the Fetch API to make the request.
        fetch(cob_ajax_object.ajax_url, { // The ajax_url is also from PHP.
            method: 'POST',
            body: formData,
        })
            .then(response => response.json()) // Parse the JSON response from the server.
            .then(data => {
                // Hide the spinner.
                spinner.style.display = 'none';

                if (data.success && data.data.html) {
                    // If the request was successful and returned HTML, append it to the container.
                    container.insertAdjacentHTML('beforeend', data.data.html);

                    // Update the button's current page data attribute.
                    loadMoreBtn.dataset.page = nextPage;

                    // If we haven't reached the last page, show the "Load More" button again.
                    if (nextPage < totalPages) {
                        loadMoreBtn.style.display = 'block';
                    } else {
                        // If it was the last page, remove the button completely.
                        loadMoreBtn.remove();
                    }

                    // IMPORTANT: Your original code uses a 'lazyload' class.
                    // After adding new images, you may need to tell your lazy-loading library
                    // to check for new images. The method to do this depends on the library you use.
                    // Example: if (window.lazyLoadInstance) { window.lazyLoadInstance.update(); }

                } else {
                    // If there are no more posts or an error occurred, remove the button.
                    loadMoreBtn.remove();
                    console.log(data.data.message || 'No more developers to load.');
                }
            })
            .catch(error => {
                // Handle network errors.
                console.error('Error fetching developers:', error);
                // Hide spinner and show button again so the user can try again.
                spinner.style.display = 'none';
                loadMoreBtn.style.display = 'block';
            });
    });
});
