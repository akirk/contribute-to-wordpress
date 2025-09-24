jQuery(document).ready(function($) {

    // Client-side debug state tracking (resets on page reload)
    let debugOverrides = {};

    // Initialize platform override from localStorage
    initializePlatformOverride();

    // Store original username value for cancel functionality
    const $usernameInput = $('#wporg-username');
    if ($usernameInput.length && $usernameInput.val()) {
        $usernameInput.data('original', $usernameInput.val());
    }


    // Verify WordPress.org username
    $('#verify-username').on('click', function() {
        verifyUsername();
    });

    // Cancel username edit
    $('#cancel-edit').on('click', function() {
        cancelUsernameEdit();
    });

    // Global functions for username editing
    window.editUsername = function() {
        $('#account-form').slideDown();
        $('#edit-username-link').hide();
        $('#wporg-username').focus();
    };

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

        if (platform === 'reset') {
            // Remove override and show auto-detected platform
            localStorage.removeItem('ctw_platform_override');
            $('#current-platform').text($('#current-platform').data('original') || 'Auto-detected');
        } else {
            // Store override in localStorage
            localStorage.setItem('ctw_platform_override', platform);
            $('#current-platform').text(platform);
        }

        $('#platform-override').hide();
        $('#platform-wrong-link').show();
        $('#platform-override').val(''); // Reset dropdown

        // Update instructions for all sections that depend on platform
        updatePlatformInstructions();
    };

    function initializePlatformOverride() {
        const $platform = $('#current-platform');
        const originalPlatform = $platform.text();

        // Store original platform for reset functionality
        $platform.data('original', originalPlatform);

        // Check for stored override
        const override = localStorage.getItem('ctw_platform_override');
        if (override) {
            $platform.text(override);
        }
    }

    function updatePlatformInstructions() {
        // Reload the page to get updated platform-specific instructions
        window.location.reload();
    }

    function toggleSectionDebug(section) {
        const $section = $('[data-section="' + section + '"]');

        // Determine current state from DOM
        const currentlyAvailable = $section.hasClass('complete');

        // Check if already in debug mode
        if (debugOverrides.hasOwnProperty(section)) {
            // Toggle the debug state
            debugOverrides[section] = !debugOverrides[section];
        } else {
            // First time toggling - set opposite of current state
            debugOverrides[section] = !currentlyAvailable;
        }

        console.log('Toggled debug state for', section, 'to', debugOverrides[section]);

        // Update UI instantly with debug state
        updateSectionUI(section, debugOverrides[section]);

        // Update stages instantly
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
        const isUpdate = $button.text().includes('Update');

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
                $button.prop('disabled', false).text(isUpdate ? 'Update Username' : 'Verify & Save Username');
            }
        });
    }

    function cancelUsernameEdit() {
        $('#account-form').slideUp();
        $('#edit-username-link').show();
        $('#username-status').hide();

        // Reset the input to original value if it was changed
        const originalValue = $('#wporg-username').data('original') || '';
        $('#wporg-username').val(originalValue);
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