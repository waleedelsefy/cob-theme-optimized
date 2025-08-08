/**
 * The final, unified, and corrected job application script.
 * All functionalities are included here.
 */
jQuery(document).ready(function($) {
    'use strict';

    // ===================================================================
    // Section 1: Job Listings, Popups, and AJAX Form Submission Logic
    // This is the correct logic that works with your PHP template.
    // ===================================================================

    var $container = $('.jobs-listings');

    // --- Popup Toggle Logic ---
    // Open the correct popup when "Apply Now" is clicked
    if ($container.length) {
        $container.on('click', '.apply-button', function() {
            var $listing = $(this).closest('.job-listing');
            $listing.find('.job-popup, .job-overlay').addClass('active');
        });

        // Close the popup when the close button or overlay is clicked
        $container.on('click', '.close-job-popup, .job-overlay', function() {
            var $listing = $(this).closest('.job-listing');
            $listing.find('.job-popup, .job-overlay').removeClass('active');
        });
    }

    // --- Form Submission Logic ---
    $(document).on('submit', 'form.jobApplicationForm', function(e) {
        e.preventDefault();

        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        var originalButtonHtml = submitButton.html();

        var responseContainer = form.find('.form-response');
        if (responseContainer.length === 0) {
            responseContainer = $('<div class="form-response" style="margin-top: 15px; padding: 10px; border-radius: 5px; display: none;"></div>').appendTo(form.find('li').last());
        }

        // Assuming cobJobAjax might not be set, provide a default text
        var sendingText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_sending) ? cobJobAjax.text_sending : "Sending...";
        submitButton.prop('disabled', true).html(sendingText);
        responseContainer.slideUp().empty();

        var formData = new FormData(this);

        // Make sure cobJobAjax and its ajax_url property are localized correctly in your theme's functions.php
        if (typeof cobJobAjax === 'undefined' || typeof cobJobAjax.ajax_url === 'undefined') {
            console.error('Ajax URL is not defined. Please localize cobJobAjax object properly in your theme.');
            responseContainer.removeClass('success').addClass('error').html("Client-side configuration error.").slideDown();
            submitButton.prop('disabled', false).html(originalButtonHtml);
            return;
        }

        $.ajax({
            url: cobJobAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                var message = (response.data && response.data.message) ? response.data.message : "An error occurred.";
                var messageClass = response.success ? 'success' : 'error';

                responseContainer.removeClass('success error').addClass(messageClass).html(message).slideDown();

                if (response.success) {
                    form[0].reset();
                    var fileText = form.find('.file-text p');
                    if (fileText.length) {
                        var chooseText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_choose) ? cobJobAjax.text_choose : "Choose file";
                        var dragText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_drag) ? cobJobAjax.text_drag : "or drag it here";
                        fileText.html('<span>' + chooseText + '</span> ' + dragText);
                    }
                    setTimeout(function() {
                        form.closest('.job-popup, .job-overlay').removeClass('active');
                    }, 4000);
                }
            },
            error: function() {
                var errorText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_error) ? cobJobAjax.text_error : "A server error occurred.";
                responseContainer.removeClass('success').addClass('error').html(errorText).slideDown();
            },
            complete: function() {
                submitButton.prop('disabled', false).html(originalButtonHtml);
            }
        });
    });

    // --- File Input Label Logic ---
    $(document).on('change', '.file-input', function() {
        var fileName = $(this).val().split('\\').pop();
        var label = $(this).closest('.file-input-container').find('.file-text p');
        var chooseText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_choose) ? cobJobAjax.text_choose : "Choose file";
        var dragText = (typeof cobJobAjax !== 'undefined' && cobJobAjax.text_drag) ? cobJobAjax.text_drag : "or drag it here";
        var originalText = '<span>' + chooseText + '</span> ' + dragText;

        if (fileName) {
            label.text(fileName);
        } else {
            label.html(originalText);
        }
    });

    // --- Details Toggle Logic ---
    // Note: This makes the inline script in your PHP file redundant.
    window.toggleJobDetails = function(element) {
        var $listing = $(element).closest('.job-listing');
        var $details = $listing.find('.job-details');
        $details.stop(true, true).slideToggle(400);
        $(element).find('.toggle-icon').toggleClass('rotated');
    };


    // ===================================================================
    // Section 2: Swiper Slider Initialization
    // ===================================================================

    // Hiring Swiper
    if ($(".hiring-swiper").length > 0) {
        new Swiper(".hiring-swiper", {
            slidesPerView: 2,
            spaceBetween: 10,
            loop: true,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });
    }

    // Owners Swiper
    if ($(".owners-swiper").length > 0) {
        new Swiper(".owners-swiper", {
            spaceBetween: 50,
            slidesPerView: 4,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            breakpoints: {
                320: { slidesPerView: 1, spaceBetween: 20 },
                640: { slidesPerView: 2, spaceBetween: 20 },
                768: { slidesPerView: 3, spaceBetween: 40 },
                1024: { slidesPerView: 4, spaceBetween: 50 },
            },
        });
    }
});