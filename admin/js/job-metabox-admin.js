jQuery(document).ready(function($) {
    'use strict';

    // Function to get the HTML for a new qualification row.
    function getNewQualificationRow() {
        return '<div class="job-qualification">' +
            '<input type="text" name="job_qualifications[]" value="" class="widefat" />' +
            ' <button type="button" class="button remove_qualification">Remove</button>' + // Translation handled by PHP if needed, but "Remove" is common.
            '</div>';
    }

    // Add a new qualification field.
    $('#add_qualification').on('click', function(e) {
        e.preventDefault();
        $('#job_qualifications_container').append(getNewQualificationRow());
    });

    // Remove a qualification field.
    // Use event delegation to handle clicks on dynamically added "Remove" buttons.
    $('#job_qualifications_container').on('click', '.remove_qualification', function(e) {
        e.preventDefault();
        // Don't remove the last one, just clear it.
        if ($('.job-qualification').length > 1) {
            $(this).closest('.job-qualification').remove();
        } else {
            $(this).closest('.job-qualification').find('input[type="text"]').val('');
        }
    });
});
