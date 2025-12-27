jQuery(document).ready(function($) {
    let btw_importer_isImporting = false;
    let btw_importer_isPaused = false;
    let btw_importer_startTime = null;
    let btw_importer_timerInterval = null;
    let btw_importer_currentStep = 0; // 0 = notice visible, 1-3 = steps
    let btw_importer_elapsedBeforePause = 0; // Track elapsed time before pause
    
    /**
     * Set the current active step in the step indicator
     * Updates visual state and shows/hides step content sections (pagination behavior)
     * @param {number} stepNumber - Step number (1, 2, or 3)
     */
    function btw_importer_setStep(stepNumber) {
        btw_importer_currentStep = stepNumber;
        
        // Show step indicator if hidden
        $('#btw_importer_steps').show();
        
        // Update step indicator visual state
        $('.btw_importer_step_item').each(function() {
            const itemStep = parseInt($(this).data('step'));
            $(this).removeClass('active pending');
            
            if (itemStep === stepNumber) {
                $(this).addClass('active');
            } else if (itemStep > stepNumber && !$(this).hasClass('completed')) {
                $(this).addClass('pending');
            }
        });
        
        // Hide all step content sections
        $('#btw_importer_step_upload, #btw_importer_step_extract, #btw_importer_step_import').hide();
        
        // Show only the current step's content section
        if (stepNumber === 1) {
            $('#btw_importer_step_upload').slideDown();
        } else if (stepNumber === 2) {
            $('#btw_importer_step_extract').slideDown();
        } else if (stepNumber === 3) {
            $('#btw_importer_step_import').slideDown();
        }
    }
    
    /**
     * Mark a step as completed
     * Adds 'completed' class and shows checkmark icon
     * @param {number} stepNumber - Step number (1, 2, or 3)
     */
    function btw_importer_completeStep(stepNumber) {
        const stepItem = $('.btw_importer_step_item[data-step="' + stepNumber + '"]');
        stepItem.removeClass('active pending').addClass('completed');
    }
    
    // Format duration helper
    function btw_importer_formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return hours + 'h ' + minutes + 'm ' + secs + 's';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        }
        return secs + 's';
    }
    
    // Update timer display
    function btw_importer_updateTimer() {
        if (btw_importer_startTime) {
            const elapsed = btw_importer_elapsedBeforePause + Math.floor((Date.now() - btw_importer_startTime) / 1000);
            $('#btw_importer_timer').text('Elapsed time: ' + btw_importer_formatDuration(elapsed));
        }
    }
    
    // Pause timer (save elapsed time)
    function btw_importer_pauseTimer() {
        if (btw_importer_timerInterval) {
            clearInterval(btw_importer_timerInterval);
            btw_importer_timerInterval = null;
        }
        if (btw_importer_startTime) {
            btw_importer_elapsedBeforePause += Math.floor((Date.now() - btw_importer_startTime) / 1000);
            btw_importer_startTime = null;
        }
    }
    
    // Resume timer
    function btw_importer_resumeTimer() {
        btw_importer_startTime = Date.now();
        btw_importer_timerInterval = setInterval(btw_importer_updateTimer, 1000);
        btw_importer_updateTimer();
    }
    
    // Stop timer completely
    function btw_importer_stopTimer() {
        if (btw_importer_timerInterval) {
            clearInterval(btw_importer_timerInterval);
            btw_importer_timerInterval = null;
        }
    }
    
    // Get total time
    function btw_importer_getTotalTime() {
        let total = btw_importer_elapsedBeforePause;
        if (btw_importer_startTime) {
            total += Math.floor((Date.now() - btw_importer_startTime) / 1000);
        }
        return total;
    }
    
    function btw_importer_escapeHtml(text) {
        return $('<div>').text(text).html();
    }
    
    // Step 1: Show upload section after agreeing to notice
    $('#btw_importer_agree_notice').on('change', function() {
        if ($(this).is(':checked')) {
            $('#btw_importer_notice').slideUp();
            btw_importer_setStep(1);
        } else {
            $('#btw_importer_notice').slideDown();
            $('#btw_importer_steps').hide();
            $('#btw_importer_step_upload').slideUp();
            btw_importer_currentStep = 0;
        }
    });
    
    // Enable upload button when file selected
    $('#btw_importer_file_input').on('change', function() {
        const hasFile = this.files && this.files.length > 0;
        $('#btw_importer_upload_btn').prop('disabled', !hasFile);
        
        if (hasFile) {
            const fileName = this.files[0].name;
            const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
            $('#btw_importer_upload_status').html('<span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span> Selected: ' + btw_importer_escapeHtml(fileName) + ' (' + fileSize + ' MB)');
        }
    });
    
    // Step 2: Upload file
    $('#btw_importer_upload_btn').on('click', function() {
        const fileInput = $('#btw_importer_file_input')[0];
        if (!fileInput.files.length) return;
        
        const formData = new FormData();
        formData.append('action', 'btw_importer_upload_file');
        formData.append('nonce', btw_importer.nonce);
        formData.append('file', fileInput.files[0]);
        
        $('#btw_importer_upload_btn').prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Uploading...');
        $('#btw_importer_upload_status').html('<span class="dashicons dashicons-cloud-upload"></span> Uploading file...');
        
        $.ajax({
            url: btw_importer.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#btw_importer_upload_status').html('✅ File uploaded successfully: ' + btw_importer_escapeHtml(response.data.filename) + ' (' + response.data.size + ')');
                    
                    // Mark Step 1 as completed and advance to Step 2
                    btw_importer_completeStep(1);
                    btw_importer_setStep(2);
                    
                    $('html, body').animate({ scrollTop: $('#btw_importer_step_extract').offset().top - 100 }, 500);
                } else {
                    $('#btw_importer_upload_status').html('❌ Upload failed: ' + btw_importer_escapeHtml(response.data));
                    $('#btw_importer_upload_btn').prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Upload File');
                }
            },
            error: function() {
                $('#btw_importer_upload_status').html('❌ Upload failed. Please try again.');
                $('#btw_importer_upload_btn').prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Upload File');
            }
        });
    });
    
    // Step 3: Extract data
    $('#btw_importer_extract_btn').on('click', function() {
        $('#btw_importer_extract_btn').prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Extracting...');
        $('#btw_importer_extract_status').html('<span class="dashicons dashicons-unlock"></span> Extracting data from file...');
        
        $.post(btw_importer.ajaxUrl, {
            action: 'btw_importer_extract_data',
            nonce: btw_importer.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                $('#btw_importer_extract_status').html('<span class="dashicons dashicons-yes-alt"></span> Successfully extracted ' + data.total + ' items');
                $('#btw_import_info').html(
                    '<div class="btw_importer_info_item"><strong>Total Items</strong><span>' + data.total + '</span></div>' +
                    '<div class="btw_importer_info_item"><strong>Posts</strong><span>' + data.posts + '</span></div>' +
                    '<div class="btw_importer_info_item"><strong>Pages</strong><span>' + data.pages + '</span></div>'
                );
                
                // Mark Step 2 as completed and advance to Step 3
                btw_importer_completeStep(2);
                btw_importer_setStep(3);
                
                $('html, body').animate({ scrollTop: $('#btw_importer_step_import').offset().top - 100 }, 500);
            } else {
                $('#btw_importer_extract_status').html('❌ Failed to extract: ' + btw_importer_escapeHtml(response.data));
                $('#btw_importer_extract_btn').prop('disabled', false).html('<span class="dashicons dashicons-admin-page"></span> Extract Data');
            }
        }).fail(function() {
            $('#btw_importer_extract_status').html('❌ Extraction failed. Please try again.');
            $('#btw_importer_extract_btn').prop('disabled', false).html('<span class="dashicons dashicons-admin-page"></span> Extract Data');
        });
    });
    
    /**
     * Update button states based on import status
     * - Not running: Start enabled, others disabled
     * - Running: Start/Resume disabled, Pause/Cancel enabled
     * - Paused: Pause/Start disabled, Resume/Cancel enabled
     */
    function btw_importer_updateButtonStates() {
        if (!btw_importer_isImporting) {
            // Not running - only Start enabled
            $('#btw_importer_start_import_btn').prop('disabled', false);
            $('#btw_importer_pause_btn').prop('disabled', true);
            $('#btw_importer_resume_btn').prop('disabled', true);
            $('#btw_importer_cancel_btn').prop('disabled', true);
        } else if (btw_importer_isPaused) {
            // Paused - Resume and Cancel enabled
            $('#btw_importer_start_import_btn').prop('disabled', true);
            $('#btw_importer_pause_btn').prop('disabled', true);
            $('#btw_importer_resume_btn').prop('disabled', false);
            $('#btw_importer_cancel_btn').prop('disabled', false);
        } else {
            // Running - Pause and Cancel enabled
            $('#btw_importer_start_import_btn').prop('disabled', true);
            $('#btw_importer_pause_btn').prop('disabled', false);
            $('#btw_importer_resume_btn').prop('disabled', true);
            $('#btw_importer_cancel_btn').prop('disabled', false);
        }
    }
    
    // Step 4: Start import
    $('#btw_importer_start_import_btn').on('click', function() {
        btw_importer_isImporting = true;
        btw_importer_isPaused = false;
        btw_importer_elapsedBeforePause = 0; // Reset elapsed time
        btw_importer_startTime = Date.now();
        
        btw_importer_updateButtonStates();
        $('#btw_importer_progress_container').show();
        
        // Start timer
        btw_importer_timerInterval = setInterval(btw_importer_updateTimer, 1000);
        btw_importer_updateTimer();
        
        btw_importer_importBatch();
    });
    
    // Pause import
    $('#btw_importer_pause_btn').on('click', function() {
        btw_importer_isPaused = true;
        btw_importer_pauseTimer();
        btw_importer_updateButtonStates();
        
        $.post(btw_importer.ajaxUrl, {
            action: 'btw_importer_pause_import',
            nonce: btw_importer.nonce
        }, function(response) {
            if (response.success) {
                $('#btw_importer_import_log').append('<div class="btw_importer_log_item"><span class="dashicons dashicons-controls-pause"></span> Import paused</div>');
                btw_importer_scrollToBottom();
            }
        });
    });
    
    // Resume import
    $('#btw_importer_resume_btn').on('click', function() {
        btw_importer_isPaused = false;
        btw_importer_resumeTimer();
        btw_importer_updateButtonStates();
        
        $.post(btw_importer.ajaxUrl, {
            action: 'btw_importer_resume_import',
            nonce: btw_importer.nonce
        }, function(response) {
            if (response.success) {
                $('#btw_importer_import_log').append('<div class="btw_importer_log_item"><span class="dashicons dashicons-controls-play"></span> Import resumed</div>');
                btw_importer_scrollToBottom();
                btw_importer_importBatch();
            }
        });
    });
    
    // Cancel import
    $('#btw_importer_cancel_btn').on('click', function() {
        if (!confirm('Are you sure you want to cancel the import? This cannot be undone.')) {
            return;
        }
        
        btw_importer_isPaused = true;
        btw_importer_isImporting = false;
        btw_importer_stopTimer();
        
        $.post(btw_importer.ajaxUrl, {
            action: 'btw_importer_cancel_import',
            nonce: btw_importer.nonce
        }, function(response) {
            if (response.success) {
                $('#btw_importer_import_log').append('<div class="btw_importer_log_item"><span class="dashicons dashicons-no-alt"></span> Import cancelled</div>');
                btw_importer_updateButtonStates();
                btw_importer_scrollToBottom();
            }
        });
    });
    
    // Import batch function
    function btw_importer_importBatch() {
        if (btw_importer_isPaused || !btw_importer_isImporting) {
            return;
        }
        
        $.post(btw_importer.ajaxUrl, {
            action: 'btw_importer_import_batch',
            nonce: btw_importer.nonce,
            batchSize: $('input[name="btw_importer_batch_size"]:checked').val() || 3 
        }, function(response) {
            if (response.success) {
                const data = response.data;
                const progress = (data.processed / data.total) * 100;
                
                // Update progress bar
                $('#btw_importer_progress_fill').css('width', progress + '%');
                $('#btw_importer_progress_text').text(Math.round(progress) + '% (' + data.processed + '/' + data.total + ')');
                
                // Log results
                if (data.results && data.results.length > 0) {
                    data.results.forEach(function(result) {
                        if (result.success) {
                            // Main log
                            $('#btw_importer_import_log').append(
                                '<div class="btw_importer_log_item btw_importer_log_header">' +
                                '<span class="dashicons dashicons-smiley"></span> Imported ' + btw_importer_escapeHtml(result.type) + ': ' + 
                                btw_importer_escapeHtml(result.title) + '</div>'
                            );
                            
                            // Detail messages
                            if (result.messages && result.messages.length > 0) {
                                result.messages.forEach(function(msg) {
                                    $('#btw_importer_import_log').append(
                                        '<div class="btw_importer_log_item btw_importer_log_detail">' +
                                        btw_importer_escapeHtml(msg) + '</div>'
                                    );
                                });
                            }
                        } else {
                            $('#btw_importer_import_log').append(
                                '<div class="btw_importer_log_item btw_importer_log_error">' +
                                '❌ Failed: ' + btw_importer_escapeHtml(result.title) + '</div>'
                            );
                            if (result.messages && result.messages.length > 0) {
                                result.messages.forEach(function(msg) {
                                    $('#btw_importer_import_log').append(
                                        '<div class="btw_importer_log_item btw_importer_log_detail">' +
                                        btw_importer_escapeHtml(msg) + '</div>'
                                    );
                                });
                            }
                        }
                    });
                    btw_importer_scrollToBottom();
                }

                
                // Check if completed
                if (data.status === 'completed') {
                    btw_importer_finishImport();
                } else if (!btw_importer_isPaused) {
                    // Continue importing immediately
                    setTimeout(btw_importer_importBatch, 50);
                }
            } else {
                $('#btw_importer_import_log').append('<div class="btw_importer_log_item btw_importer_log_error">❌ Error: ' + btw_importer_escapeHtml(response.data) + '</div>');
                btw_importer_scrollToBottom();
            }
        }).fail(function() {
            $('#btw_importer_import_log').append('<div class="btw_importer_log_item btw_importer_log_error">❌ Connection error. Retrying...</div>');
            btw_importer_scrollToBottom();
            if (!btw_importer_isPaused) {
                setTimeout(btw_importer_importBatch, 1000);
            }
        });
    }
    
    // Finish import
    function btw_importer_finishImport() {
        btw_importer_stopTimer();
        btw_importer_isImporting = false;
        btw_importer_isPaused = false;
        
        const totalTime = btw_importer_getTotalTime();
        
        // Disable all buttons after completion
        $('#btw_importer_start_import_btn, #btw_importer_pause_btn, #btw_importer_resume_btn, #btw_importer_cancel_btn').prop('disabled', true);
        
        // Mark Step 3 as completed (all steps now completed)
        btw_importer_completeStep(3);
        
        $('#btw_importer_import_log').append(
            '<div class="btw_importer_summary">' +
            '🎉 Import completed successfully!<br>' +
            '<span class="dashicons dashicons-clock"></span> Total time: ' + btw_importer_formatDuration(totalTime) +
            '</div>'
        );
        
        btw_importer_scrollToBottom();
    }
    
    // Scroll to bottom of log
    function btw_importer_scrollToBottom() {
        const log = $('#btw_importer_import_log');
        if (log.length) {
            log.scrollTop(log[0].scrollHeight);
        }
    }
    
    // Warn user before leaving if import is running
    window.addEventListener('beforeunload', function(e) {
        if (btw_importer_isImporting && !btw_importer_isPaused) {
            e.preventDefault();
            e.returnValue = 'Import is in progress. Are you sure you want to leave?';
        }
    });
});
