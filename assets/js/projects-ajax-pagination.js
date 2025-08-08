/**
 * Handles "Load More" functionality for the "Latest Projects" page.
 */
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-projects');
    if (!loadMoreBtn) {
        return;
    }

    loadMoreBtn.addEventListener('click', function() {
        const gridContainer = document.getElementById('projects-grid-container');
        const spinner = document.getElementById('projects-loading-spinner');

        // Get current page, total pages, and items per page from the button's data attributes.
        let currentPage = parseInt(this.dataset.page, 10);
        const totalPages = parseInt(this.dataset.totalPages, 10);
        const perPage = parseInt(this.dataset.perPage, 10);

        // We are fetching the *next* page.
        const nextPage = currentPage + 1;

        // Show loading state: hide button, show spinner.
        loadMoreBtn.style.display = 'none';
        if (spinner) spinner.style.display = 'block';

        // Prepare data for the AJAX request.
        const formData = new FormData();
        formData.append('action', 'load_more_projects'); // Corresponds to the PHP action hook.
        formData.append('nonce', cob_projects_ajax.nonce);
        formData.append('page', nextPage);
        formData.append('per_page', perPage);

        // Make the fetch request.
        fetch(cob_projects_ajax.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.html) {
                    // Append the new cards to the grid.
                    if (gridContainer) {
                        gridContainer.insertAdjacentHTML('beforeend', data.data.html);
                    }

                    // Update the button's current page number.
                    this.dataset.page = nextPage;

                    // If we haven't reached the last page, show the button again.
                    if (nextPage < totalPages) {
                        loadMoreBtn.style.display = 'block';
                    } else {
                        // If it was the last page, remove the button container.
                        loadMoreBtn.parentElement.remove();
                    }
                } else {
                    // If there are no more posts or an error occurred, remove the button container.
                    loadMoreBtn.parentElement.remove();
                    console.log(data.data.message || 'No more projects to load.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                // In case of error, show the button again so the user can retry.
                loadMoreBtn.style.display = 'block';
            })
            .finally(() => {
                // Hide the spinner regardless of the outcome.
                if (spinner) spinner.style.display = 'none';
            });
    });
});
