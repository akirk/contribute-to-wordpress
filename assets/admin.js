jQuery(document).ready(function($) {

    // Client-side debug state tracking (resets on page reload)
    let debugOverrides = {};


    // Verify WordPress.org username
    $('#verify-username').on('click', function() {
        verifyUsername();
    });

    // Toggle section debug mode
    window.toggleDebugMode = function(section) {
        toggleSectionDebug(section);
    };

    // Platform override functions
    window.showPlatformOverride = function() {
        $('#platform-wrong-link').hide();
        $('#platform-override').show();
    };

    window.overridePlatform = function(platform) {
        if (!platform) return;

        $.ajax({
            url: ctw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ctw_set_platform',
                platform: platform,
                nonce: ctw_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#current-platform').text(response.data.platform);
                    $('#platform-override').hide();
                    $('#platform-wrong-link').show();

                    // Update instructions for all sections that depend on platform
                    updatePlatformInstructions();
                }
            }
        });
    };

    function updatePlatformInstructions() {
        // Reload the page to get updated platform-specific instructions
        window.location.reload();
    }

    function toggleSectionDebug(section) {
        const $section = $('[data-section="' + section + '"]');

        // Check if already in debug mode
        if (debugOverrides.hasOwnProperty(section)) {
            // Toggle the debug state
            debugOverrides[section] = !debugOverrides[section];
            console.log('Toggled debug state for', section, 'to', debugOverrides[section]);
        } else {
            // Get current state and set opposite
            $.ajax({
                url: ctw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctw_toggle_debug',
                    section: section,
                    nonce: ctw_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Store the debug state
                        debugOverrides[section] = response.data.available;

                        // Update UI
                        updateSectionUI(section, response.data.available, response.data.section_data);
                        updateContributionStages();
                    }
                }
            });
            return;
        }

        // Update UI with current debug state
        updateSectionUI(section, debugOverrides[section]);

        // Always update stages after toggling
        updateContributionStages();

    }


    function updateAllSections(results) {
        for (const [section, isAvailable] of Object.entries(results)) {
            updateSectionUI(section, isAvailable);
        }
    }

    function verifyUsername() {
        const username = $('#wporg-username').val().trim();
        const $button = $('#verify-username');
        const $status = $('#username-status');

        if (!username) {
            showUsernameStatus('Please enter a username', 'error');
            return;
        }

        $button.prop('disabled', true).text('Verifying...');
        $status.hide();

        $.ajax({
            url: ctw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ctw_verify_username',
                username: username,
                nonce: ctw_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showUsernameStatus(response.data, 'success');
                    // Refresh the page to show updated account status
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showUsernameStatus(response.data, 'error');
                }
            },
            error: function() {
                showUsernameStatus('Failed to verify username', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Verify & Save Username');
            }
        });
    }

    function updateAllSections(results) {
        for (const [section, isAvailable] of Object.entries(results)) {
            updateSectionUI(section, isAvailable);
        }
    }

    function updateSectionUI(section, isAvailable, sectionData) {
        const $section = $('[data-section="' + section + '"]');
        const $icon = $section.find('.dashicons');
        const $badge = $section.find('.status-badge');
        const $instructions = $('#instructions-' + section);

        // Update classes
        $section.removeClass('complete incomplete').addClass(isAvailable ? 'complete' : 'incomplete');

        // Update icon
        $icon.removeClass('dashicons-yes-alt dashicons-dismiss')
              .addClass(isAvailable ? 'dashicons-yes-alt' : 'dashicons-dismiss');

        // Update status badge
        $badge.removeClass('success error')
              .addClass(isAvailable ? 'success' : 'error')
              .text(isAvailable ? '✓ Available' : '✗ Not available');

        // Show/hide instructions
        if (isAvailable) {
            $instructions.slideUp();
        } else {
            if ($instructions.length === 0 && sectionData && sectionData.instructions) {
                // Create instructions if they don't exist
                $section.append('<div class="instructions" id="instructions-' + section + '">' + sectionData.instructions + '</div>');
            }
            $instructions.slideDown();
        }
    }

    function updateContributionStages() {
        // Send current debug overrides to get updated stages
        console.log('Updating stages with debug overrides:', debugOverrides);
        $.ajax({
            url: ctw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ctw_get_stages',
                debug_overrides: JSON.stringify(debugOverrides),
                nonce: ctw_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#contribution-stages-container').html(response.data.html);
                    console.log('Stages updated successfully');
                } else {
                    console.log('Failed to update stages:', response);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX error updating stages:', error);
            }
        });
    }

    function showUsernameStatus(message, type) {
        const $status = $('#username-status');
        $status.removeClass('success error')
               .addClass(type)
               .text(message)
               .fadeIn();
    }

    function showNotification(message, type) {
        // Create a temporary notification
        const $notification = $('<div class="notice notice-' + (type === 'success' ? 'success' : 'error') + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss">' +
            '<span class="screen-reader-text">Dismiss this notice.</span>' +
            '</button>' +
            '</div>');

        // Insert after the h1
        $('#ctw-admin h1').after($notification);

        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);

        // Manual dismiss
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }


    // Add smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 50
            }, 500);
        }
    });

    // Add hover effects for interactive elements
    $('.status-badge').on('mouseenter', function() {
        $(this).attr('title', 'Click to refresh this section');
    });

    // Keyboard accessibility
    $('.status-badge').on('keydown', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).click();
        }
    });

    // Initialize tooltips (if WordPress has them available)
    if (typeof $.fn.tooltip === 'function') {
        $('.status-badge').tooltip();
    }
});