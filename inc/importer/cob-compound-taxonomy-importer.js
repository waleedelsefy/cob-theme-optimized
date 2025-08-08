/**
 * AJAX Importer for Compound Taxonomies in WordPress.
 *
 * Version 5: Corrected JavaScript error 'Cannot read properties of undefined (reading 'replace')'.
 * This version safely handles all log messages and correctly renders HTML for colored logs from the backend.
 */
jQuery(document).ready(function($) {
    // --- Element Selectors ---
    const form = $('#cob-cti-importer-form');
    const progressBar = $('#cob-cti-importer-progress-bar');
    const progressContainer = $('#cob-cti-progress-container');
    const statsElement = $('#cob-cti-importer-stats');
    const logElement = $('#cob-cti-importer-log');

    const startButton = $('#cob-cti-start-new');
    const resumeButton = $('#cob-cti-resume');
    const cancelButton = $('#cob-cti-cancel');

    const fileInput = $('#compound_csv_file');
    const languageSelect = $('#target_language_selector');

    // Localized strings from PHP
    const i18n = cobCTIAjax.i18n || {};

    let isImporting = false;

    /**
     * Disables or enables UI elements to prevent user actions during an active import.
     * @param {boolean} isLocked - True to lock the UI, false to unlock.
     */
    function toggleImporterLock(isLocked) {
        isImporting = isLocked;
        startButton.prop('disabled', isLocked);
        resumeButton.prop('disabled', isLocked);
        cancelButton.prop('disabled', isLocked);
        fileInput.prop('disabled', isLocked);
        languageSelect.prop('disabled', isLocked);
    }

    /**
     * Updates the progress bar and stats text based on the status from the server.
     * @param {object} status - The current import status object.
     */
    function updateUI(status) {
        if (!status) return;
        const percent = parseInt(status.progress, 10) || 0;
        progressBar.css('width', percent + '%').text(percent + '%');

        const langText = status.language ? ` (${status.language})` : '';
        const statsText =
            `${i18n.processed || 'Processed'}: ${status.processed_rows || 0} ${i18n.of || 'of'} ${status.total_rows || 0} | ` +
            `${i18n.imported || 'Imported'}: ${status.imported_count || 0} | ` +
            `${i18n.updated || 'Updated'}: ${status.updated_count || 0} | ` +
            `${i18n.failed || 'Failed'}: ${status.failed_count || 0}${langText}`;

        statsElement.text(statsText);
    }

    /**
     * CORRECTED: Appends messages to the log area with appropriate styling.
     * Safely handles undefined/null messages and renders HTML from the backend.
     * @param {string|Array} messages - The message or array of messages to log.
     * @param {string} type - The message type ('info', 'error', 'success', 'warning').
     */
    function addToLog(messages, type = 'info') {
        let messageContent = '';
        if (Array.isArray(messages)) {
            messageContent = messages.join("\n");
        } else if (messages) { // This handles strings, numbers, etc., and skips null/undefined
            messageContent = messages.toString();
        }

        // The backend provides HTML, so we don't sanitize it. We just replace newlines.
        const formattedMessage = messageContent.replace(/\n/g, '<br>');

        logElement.append('<div>' + formattedMessage + '</div>');
        logElement.scrollTop(logElement[0].scrollHeight);
    }

    /**
     * The main import loop. Calls the 'run' action via AJAX and re-calls itself on success
     * until the import is complete.
     */
    function runBatch() {
        if (!isImporting) {
            toggleImporterLock(false);
            return;
        }

        $.ajax({
            url: cobCTIAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cob_cti_ajax_handler',
                importer_action: 'run',
                nonce: cobCTIAjax.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.status) {
                    if (response.data.log && response.data.log.length > 0) {
                        addToLog(response.data.log);
                    }
                    updateUI(response.data.status);

                    if (response.data.done) {
                        addToLog('<strong>' + i18n.import_complete + '</strong>');
                        toggleImporterLock(false);
                        cancelButton.show().text('Clear Status');
                        resumeButton.hide();
                    } else {
                        setTimeout(runBatch, 100); // Process the next batch
                    }
                } else {
                    addToLog(response.data.message || i18n.error_unknown_processing, 'error');
                    toggleImporterLock(false);
                }
            },
            error: function(xhr) {
                addToLog(`${i18n.connection_error} (Status: ${xhr.status})`, 'error');
                addToLog("You can try to resume or cancel the process.", 'warning');
                toggleImporterLock(false);
            }
        });
    }

    /**
     * Resets the UI to its initial state.
     */
    function resetUI() {
        isImporting = false;
        form[0].reset();
        progressContainer.hide();
        progressBar.css('width', '0%').text('0%');
        statsElement.text('');
        logElement.html('');
        toggleImporterLock(false);
        cancelButton.hide();
        resumeButton.hide();
        $('#cob-cti-resume-notice').hide();
    }

    // --- Event Handlers ---
    form.on('submit', function(e) {
        e.preventDefault();
        if (isImporting) return;
        if (!confirm(i18n.confirm_new_import)) return;
        if (fileInput[0].files.length === 0) {
            alert(i18n.error_selecting_file);
            return;
        }

        toggleImporterLock(true);
        progressContainer.show();
        logElement.html('');
        addToLog(i18n.preparing_import);
        resumeButton.hide();
        cancelButton.show();

        const formData = new FormData();
        formData.append('action', 'cob_cti_ajax_handler');
        formData.append('importer_action', 'prepare');
        formData.append('nonce', cobCTIAjax.nonce);
        formData.append('import_language', languageSelect.val());
        formData.append('csv_file', fileInput[0].files[0]);

        $.ajax({
            url: cobCTIAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.status) {
                    if (response.data.log && response.data.log.length > 0) addToLog(response.data.log);
                    updateUI(response.data.status);
                    if (response.data.status.total_rows > 0) {
                        runBatch();
                    } else {
                        addToLog("No data rows found to process.", 'warning');
                        toggleImporterLock(false);
                    }
                } else {
                    addToLog(response.data.message || i18n.error_unknown_prepare, 'error');
                    toggleImporterLock(false);
                }
            },
            error: function(xhr) {
                addToLog(`${i18n.connection_error} (Status: ${xhr.status})`, 'error');
                toggleImporterLock(false);
            }
        });
    });

    resumeButton.on('click', function() {
        if (!confirm(i18n.confirm_resume)) return;

        toggleImporterLock(true);
        progressContainer.show();
        logElement.append("<div>----------------------------------</div>");
        addToLog(i18n.resuming_import);

        $.ajax({
            url: cobCTIAjax.ajax_url,
            type: 'POST',
            data: { action: 'cob_cti_ajax_handler', importer_action: 'get_status', nonce: cobCTIAjax.nonce },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.status) {
                    updateUI(response.data.status);
                    runBatch();
                } else {
                    addToLog(response.data.message || "Failed to retrieve status for resume.", 'error');
                    toggleImporterLock(false);
                }
            },
            error: function() {
                addToLog("Connection error while trying to get status.", 'error');
                toggleImporterLock(false);
            }
        });
    });

    cancelButton.on('click', function() {
        if (!confirm(i18n.confirm_cancel)) return;
        isImporting = false;
        toggleImporterLock(true);

        $.ajax({
            url: cobCTIAjax.ajax_url,
            type: 'POST',
            data: { action: 'cob_cti_ajax_handler', importer_action: 'cancel', nonce: cobCTIAjax.nonce },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addToLog(response.data.message || i18n.import_cancelled_successfully, 'success');
                    resetUI();
                } else {
                    addToLog(response.data.message || i18n.error_cancelling, 'error');
                }
            },
            error: function(){
                addToLog(i18n.error_connecting_cancel, 'error');
            },
            complete: function() {
                toggleImporterLock(false);
            }
        });
    });

    // Initial check for resumable import on page load
    function checkResumable() {
        $.ajax({
            url: cobCTIAjax.ajax_url,
            type: 'POST',
            data: { action: 'cob_cti_ajax_handler', importer_action: 'get_status', nonce: cobCTIAjax.nonce },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.status && response.data.status.progress < 100 && response.data.status.total_rows > 0) {
                    $('#cob-cti-resume-notice').show();
                    resumeButton.show();
                    cancelButton.show();
                }
            }
        });
    }
    checkResumable();
});
