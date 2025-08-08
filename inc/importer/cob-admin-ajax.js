document.addEventListener('DOMContentLoaded', function () {
    // Find all upload forms on the page
    const forms = document.querySelectorAll('.upload-form');

    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            // Prevent the default form submission (which causes a page reload)
            event.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            const statusMessage = form.querySelector('.status-message');
            const urlInput = form.querySelector('input[type="url"]');
            const listItem = form.closest('li');

            // --- Provide instant feedback to the user ---
            submitButton.disabled = true;
            submitButton.textContent = cob_ajax_obj.uploading_text; // "Uploading..."
            statusMessage.textContent = '';
            statusMessage.classList.remove('success', 'error');

            // --- Collect form data ---
            const formData = new FormData(form);
            // Add the action and nonce from the object passed by PHP
            formData.append('action', 'cob_upload_image_via_ajax');
            formData.append('nonce', cob_ajax_obj.nonce);

            // --- Send the data to the server using Fetch API ---
            fetch(cob_ajax_obj.ajax_url, {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // --- Handle SUCCESS ---
                        statusMessage.textContent = data.data.message;
                        statusMessage.classList.add('success');
                        // Fade out and remove the list item for a clean UX
                        listItem.classList.add('fading-out');
                        setTimeout(() => {
                            listItem.remove();
                        }, 500); // 500ms matches the CSS transition
                    } else {
                        // --- Handle ERROR ---
                        statusMessage.textContent = 'Error: ' + data.data.message;
                        statusMessage.classList.add('error');
                        // Re-enable the button so the user can try again
                        submitButton.disabled = false;
                        submitButton.textContent = cob_ajax_obj.button_text; // "Upload & Set Image"
                    }
                })
                .catch(error => {
                    // --- Handle network or unexpected errors ---
                    console.error('AJAX Error:', error);
                    statusMessage.textContent = 'A network error occurred. Please try again.';
                    statusMessage.classList.add('error');
                    submitButton.disabled = false;
                    submitButton.textContent = cob_ajax_obj.button_text;
                });
        });
    });
});