/**
 * JavaScript for the AJAX Property Importer.
 * Version 3.3.0
 * Handles frontend logic and now includes an auto-retry mechanism for failed AJAX requests,
 * making the import process more resilient to server timeouts and network issues.
 */
jQuery(document).ready(function($) {

    // --- Element Selectors ---
    const form = $('#cob-importer-form');
    const fileInput = $('#property_csv');
    const serverFileInput = $('#server_csv_file');
    const languageSelector = $('#import_language');
    const skipImagesCheckbox = $('#skip_images');
    const sourceRadio = $('input[name="import_source"]');
    const startButton = form.find('button[type="submit"]');
    const resumeButton = $('#resume-import');
    const cancelButton = $('#cancel-import');
    const progressContainer = $('#importer-progress-container');
    const progressBar = $('#importer-progress-bar');
    const progressStats = $('#importer-stats');
    const logContainer = $('#importer-log');
    const noticeContainer = $('#importer-notice');

    let isImporting = false;
    // NEW: Constants and variables for the retry mechanism
    const MAX_RETRIES = 5;
    const RETRY_DELAY = 5000; // 5 seconds
    let currentRetries = 0;

    // --- UI Functions ---

    sourceRadio.on('change', function() {
        if (this.value === 'upload') {
            $('#source-upload-container').show();
            $('#source-server-container').hide();
        } else {
            $('#source-upload-container').hide();
            $('#source-server-container').show();
        }
    }).trigger('change');

    function showNotice(message, type = 'error') {
        noticeContainer.text(message)
            .removeClass('notice-error notice-warning')
            .addClass(type === 'error' ? 'notice-error' : 'notice-warning')
            .show();
    }

    function addToLog(message) {
        logContainer.append('<div>' + message + '</div>');
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }

    function updateUI(status) {
        if (!status) return;
        const i18n = cobPropImporter.i18n;
        const progress = status.progress || 0;
        progressBar.css('width', progress + '%').text(progress + '%');
        const statsText = `${i18n.processed} ${status.processed} ${i18n.of} ${status.total_rows} | ` +
            `${i18n.imported}: ${status.imported_count} | ` +
            `${i18n.updated}: ${status.updated_count} | ` +
            `${i18n.failed}: ${status.failed_count}`;
        progressStats.text(statsText);
    }

    function resetUI() {
        isImporting = false;
        form[0].reset();
        sourceRadio.trigger('change');
        progressContainer.hide();
        progressBar.css('width', '0%').text('0%');
        progressStats.text('');
        logContainer.html('');
        noticeContainer.hide();
        startButton.prop('disabled', false).text(cobPropImporter.i18n.start_new_import || 'Start New Import');
        cancelButton.hide();
        resumeButton.hide();
        $('#resume-notice').hide();
    }

    // --- AJAX Functions ---

    function processBatch() {
        if (!isImporting) return;

        $.ajax({
            url: cobPropImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'cob_run_property_importer',
                nonce: cobPropImporter.nonce,
                importer_action: 'run'
            },
            dataType: 'json',
            success: function(response) {
                // NEW: Reset retry count on a successful request
                currentRetries = 0;

                if (response.success) {
                    if (response.data.log && response.data.log.length > 0) {
                        response.data.log.forEach(msg => addToLog(msg));
                    }
                    updateUI(response.data.status);

                    if (response.data.done) {
                        isImporting = false;
                        addToLog('<strong>' + cobPropImporter.i18n.import_complete + '</strong>');
                        startButton.prop('disabled', false);
                        cancelButton.text('Clear Status');
                    } else {
                        setTimeout(processBatch, 100); // Process next batch
                    }
                } else {
                    isImporting = false;
                    addToLog('<span style="color:red;">' + (response.data.message || 'An unknown error occurred.') + '</span>');
                    startButton.prop('disabled', false);
                }
            },
            error: function(xhr) {
                // NEW: Retry logic
                currentRetries++;
                if (currentRetries <= MAX_RETRIES) {
                    addToLog(`<span style="color:orange;">${cobPropImporter.i18n.retrying} (${currentRetries}/${MAX_RETRIES})</span>`);
                    setTimeout(processBatch, RETRY_DELAY); // Wait and then retry the same batch
                } else {
                    isImporting = false;
                    const errorMessage = `${cobPropImporter.i18n.connection_error}: ${xhr.statusText}. ${cobPropImporter.i18n.max_retries_reached}`;
                    addToLog(`<span style="color:red;"><strong>${errorMessage}</strong></span>`);
                    showNotice(errorMessage, 'error');
                    startButton.prop('disabled', false);
                }
            }
        });
    }

    function prepareImport() {
        isImporting = true;
        currentRetries = 0; // Reset retries for a new import
        logContainer.html('');
        noticeContainer.hide();
        progressContainer.show();
        startButton.prop('disabled', true);
        cancelButton.show();
        resumeButton.hide();

        const formData = new FormData();
        formData.append('action', 'cob_run_property_importer');
        formData.append('nonce', cobPropImporter.nonce);
        formData.append('importer_action', 'prepare');
        formData.append('source_type', $('input[name="import_source"]:checked').val());
        formData.append('import_language', languageSelector.val());
        formData.append('skip_images', skipImagesCheckbox.is(':checked')); // Send skip images state

        const source = $('input[name="import_source"]:checked').val();
        if (source === 'upload') {
            formData.append('csv_file', fileInput[0].files[0]);
        } else {
            formData.append('file_name', serverFileInput.val());
        }

        $.ajax({
            url: cobPropImporter.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addToLog(cobPropImporter.i18n.preparing_import);
                    if (response.data.log && response.data.log.length > 0) {
                        response.data.log.forEach(msg => addToLog(msg));
                    }
                    updateUI(response.data.status);
                    processBatch();
                } else {
                    isImporting = false;
                    showNotice(response.data.message || 'An unknown preparation error occurred.');
                    progressContainer.hide();
                    startButton.prop('disabled', false);
                }
            },
            error: function(xhr) {
                isImporting = false;
                showNotice(cobPropImporter.i18n.connection_error + ': ' + xhr.statusText);
                progressContainer.hide();
                startButton.prop('disabled', false);
            }
        });
    }

    // --- Event Handlers ---
    form.on('submit', function(e) {
        e.preventDefault();
        if (isImporting) return;

        const source = $('input[name="import_source"]:checked').val();
        if (source === 'upload' && !fileInput[0].files.length) {
            showNotice(cobPropImporter.i18n.error_selecting_file);
            return;
        }
        if (source === 'server' && !serverFileInput.val()) {
            showNotice(cobPropImporter.i18n.error_selecting_file);
            return;
        }
        if (!confirm(cobPropImporter.i18n.confirm_new_import)) return;

        prepareImport();
    });

    resumeButton.on('click', function() {
        if (isImporting) return;
        if (!confirm(cobPropImporter.i18n.confirm_resume)) return;

        isImporting = true;
        currentRetries = 0;
        progressContainer.show();
        noticeContainer.hide();
        startButton.prop('disabled', true);
        cancelButton.show();
        resumeButton.hide();
        addToLog('Attempting to resume previous import...');

        $.ajax({
            url: cobPropImporter.ajax_url,
            type: 'POST',
            data: { action: 'cob_run_property_importer', nonce: cobPropImporter.nonce, importer_action: 'get_status' },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    updateUI(response.data.status);
                    addToLog('Resumed successfully. Continuing import...');
                    processBatch();
                } else {
                    isImporting = false;
                    showNotice(response.data.message || 'Could not find a resumable import.');
                    resetUI();
                }
            },
            error: function(xhr) {
                isImporting = false;
                showNotice(cobPropImporter.i18n.connection_error + ': ' + xhr.statusText);
                resetUI();
            }
        });
    });

    cancelButton.on('click', function() {
        if (!confirm(cobPropImporter.i18n.confirm_cancel)) return;
        isImporting = false;
        addToLog('Cancelling import...');

        $.ajax({
            url: cobPropImporter.ajax_url,
            type: 'POST',
            data: { action: 'cob_run_property_importer', nonce: cobPropImporter.nonce, importer_action: 'cancel' },
            dataType: 'json',
            success: function(response) {
                addToLog(response.data.message || 'Import cancelled.');
                resetUI();
            },
            error: function(xhr) {
                showNotice(cobPropImporter.i18n.connection_error + ': ' + xhr.statusText);
                resetUI();
            }
        });
    });
});
