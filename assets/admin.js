jQuery(document).ready(function ($) {

    let overrides = {};

    initializePlatformOverride();
    loadRequirementsAsync();

    // Store original username value for cancel functionality
    const $usernameInput = $('#wporg-username');
    if ($usernameInput.length && $usernameInput.val()) {
        $usernameInput.data('original', $usernameInput.val());
    }


    // Verify WordPress.org username
    $('#verify-username').on('click', function () {
        verifyUsername();
    });

    // Cancel username edit
    $('#cancel-edit').on('click', function () {
        cancelUsernameEdit();
    });

    // Global functions for username editing
    window.editUsername = function () {
        $('#account-form').slideDown();
        $('#edit-username-link').hide();
        $('#wporg-username').focus();
    };

    // Toggle section debug mode
    window.toggleDebugMode = function (section) {
        toggleSectionDebug(section);
    };

    // Platform override functions
    window.showPlatformOverride = function () {
        $('#platform-wrong-link').hide();
        $('#platform-override').show();
    };

    window.overridePlatform = function (platform) {
        if (!platform) return;

        if (platform === 'reset') {
            delete overrides.platform;
            $('#current-platform').text($('#current-platform').data('original') || 'Auto-detected');
        } else {
            overrides.platform = platform;
            $('#current-platform').text(platform);
        }

        $('#platform-override').hide();
        $('#platform-wrong-link').show();
        $('#platform-override').val('');

        updatePlatformInstructions();
    };

    function initializePlatformOverride() {
        const $platform = $('#current-platform');
        const originalPlatform = $platform.text();

        $platform.data('original', originalPlatform);
    }

    function updatePlatformInstructions() {
        loadRequirementsAsync();
    }

    function toggleSectionDebug(section) {
        const $section = $('[data-section="' + section + '"]');

        // Determine current actual state from DOM
        const currentlyAvailable = $section.hasClass('complete');

        // Check if already in debug mode
        if (overrides.hasOwnProperty(section)) {
            // Remove override and return to actual detected state
            delete overrides[section];

            // Get the actual detected result and use it
            loadSingleRequirement(section).then(function () {
                updateContributionStages();
            });
        } else {
            // First time toggling - set opposite of current state
            overrides[section] = !currentlyAvailable;

            const debugResponseData = {
                status: overrides[section],
                version: overrides[section] ? 'Available' : null,
                instructions: ''
            };
            updateSectionUI(section, debugResponseData);
            updateContributionStages();
        }
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
            success: function (response) {
                if (response.success) {
                    showUsernameStatus(response.data, 'success');
                    // Refresh the page to show updated account status
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    showUsernameStatus(response.data, 'error');
                }
            },
            error: function () {
                showUsernameStatus('Failed to verify username', 'error');
            },
            complete: function () {
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

    function updateSectionUI(section, responseData) {
        const $section = $('[data-section="' + section + '"]');
        const $icon = $section.find('.dashicons');
        const $badge = $section.find('.status-badge');
        const $instructions = $('#instructions-' + section);

        let isAvailable = !!responseData.status;
        let isWarning = false;
        let statusText;

        // Check if responseData.status is an object with warning status
        if (typeof responseData.status === 'object' && responseData.status.status === 'warning') {
            isWarning = true;
            isAvailable = true; // Treat as available but with warning
            statusText = "⚠ " + responseData.status.message;
        } else if (isAvailable && responseData.version) {
            statusText = "✓ " + responseData.version + " detected";
        } else {
            statusText = isAvailable ? '✓ Available' : '✗ Not available';
        }

        $section.removeClass('loading complete incomplete warning').addClass(
            isWarning ? 'warning' : (isAvailable ? 'complete' : 'incomplete')
        );

        $icon.removeClass('dashicons-update dashicons-yes-alt dashicons-dismiss dashicons-warning')
            .addClass(isWarning ? 'dashicons-warning' : (isAvailable ? 'dashicons-yes-alt' : 'dashicons-dismiss'));

        $badge.removeClass('loading success error warning')
            .addClass(isWarning ? 'warning' : (isAvailable ? 'success' : 'error'))
            .text(statusText);

        // Update instructions whenever they're provided, regardless of availability
        if (responseData.instructions) {
            if ($instructions.length === 0) {
                $section.append('<div class="instructions" id="instructions-' + section + '">' + responseData.instructions + '</div>');
                $instructions = $('#instructions-' + section);
            } else {
                $instructions.html(responseData.instructions);
            }
        }

        // Show/hide instructions - show for warning state
        if (isAvailable && !isWarning) {
            $instructions.slideUp();
        } else {
            $instructions.slideDown();
        }
    }

    function getToolStatus(toolKey) {
        const $section = $('[data-section="' + toolKey + '"]');
        return $section.hasClass('complete');
    }

    function getToolName(toolKey) {
        const toolNames = {
            'git': 'Git',
            'wp_repo': 'WordPress Core Repository',
            'node': 'Node.js',
            'npm': 'npm',
            'composer': 'Composer',
            'gutenberg': 'Gutenberg Plugin',
            'plugin_theme_git': 'Plugin/Theme Git Repository',
            'plugin_check': 'Plugin Check',
            'wporg_account': 'WordPress.org Account'
        };

        return toolNames[toolKey] || toolKey;
    }

    function updateContributionStages() {
        const stages = {
            'web': { requirements: [] },
            'php': { requirements: ['git', 'wp_repo'], optional: ['composer'] },
            'assets': { requirements: ['git', 'wp_repo', 'node', 'npm'] },
            'blocks': { requirements: ['git', 'wp_repo', 'node', 'npm', 'gutenberg'] },
            'plugins_themes': { requirements: ['git', 'plugin_theme_git'], optional: ['plugin_check'] },
            'contribute': { requirements: ['wporg_account'] }
        };

        Object.keys(stages).forEach(stageKey => {
            const stage = stages[stageKey];
            const $stage = $('[data-stage="' + stageKey + '"]');

            if ($stage.length === 0) return;

            let stageReady = true;
            const missingRequirements = [];
            const missingOptional = [];

            stage.requirements.forEach(req => {
                const isAvailable = overrides[req] !== undefined ?
                    overrides[req] :
                    getToolStatus(req);

                if (!isAvailable) {
                    stageReady = false;
                    const toolName = getToolName(req);
                    missingRequirements.push(toolName);
                }
            });

            if (stage.optional) {
                stage.optional.forEach(opt => {
                    const isAvailable = overrides[opt] !== undefined ?
                        overrides[opt] :
                        getToolStatus(opt);

                    if (!isAvailable) {
                        const toolName = getToolName(opt);
                        missingOptional.push(toolName);
                    }
                });
            }

            updateStageUI(stageKey, stageReady, missingRequirements, missingOptional);
        });
    }

    function updateStageUI(stageKey, isReady, missingRequirements, missingOptional) {
        const $stage = $('[data-stage="' + stageKey + '"]');
        const $title = $stage.find('.stage-title');
        const $checking = $stage.find('.stage-checking');
        let $missing = $stage.find('.stage-missing');
        let $optional = $stage.find('.stage-optional');

        $stage.removeClass('available missing undetermined').addClass(isReady ? 'available' : 'missing');
        $title.removeClass('available missing undetermined').addClass(isReady ? 'available' : 'missing');

        // Remove checking spinner
        $checking.remove();

        // Update title with new icon
        let titleText = $title.text();
        titleText = titleText.replace(/^[✅❌⏳]\s*/, ''); // Remove existing icons
        const newIcon = isReady ? '✅' : '❌';
        $title.text(newIcon + ' ' + titleText);

        if (isReady) {
            $missing.hide();
        } else {
            if (missingRequirements.length > 0) {
                if ($missing.length === 0) {
                    // Create missing requirements element if it doesn't exist
                    $stage.find('.stage-details').append('<p class="stage-missing"><strong>Missing:</strong> <span></span></p>');
                    $missing = $stage.find('.stage-missing');
                }
                $missing.find('span').text(missingRequirements.join(', '));
                $missing.show();
            }
        }

        // Handle optional requirements
        if (missingOptional && missingOptional.length > 0) {
            if ($optional.length === 0) {
                // Create optional requirements element if it doesn't exist
                $stage.find('.stage-details').append('<p class="stage-optional"><strong>Optional:</strong> <span></span> (recommended but not required)</p>');
                $optional = $stage.find('.stage-optional');
            }
            $optional.find('span').text(missingOptional.join(', '));
            $optional.show();
        } else {
            $optional.hide();
        }
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
        setTimeout(function () {
            $notification.fadeOut(function () {
                $(this).remove();
            });
        }, 3000);

        // Manual dismiss
        $notification.find('.notice-dismiss').on('click', function () {
            $notification.fadeOut(function () {
                $(this).remove();
            });
        });
    }


    // Add smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function (e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 50
            }, 500);
        }
    });

    function loadRequirementsAsync() {
        const promises = [];

        $('.checklist-item').each(function () {
            const $section = $(this);
            const sectionKey = $section.data('section');

            if (sectionKey) {
                promises.push(loadSingleRequirement(sectionKey));
            }
        });

        Promise.allSettled(promises).then(function () {
            updateContributionStages();
        });
    }

    function loadSingleRequirement(section) {
        console.log('Loading requirement for section:', section);

        if (!section) {
            console.error('Section is empty or undefined!');
            return Promise.reject(new Error('Invalid section'));
        }

        return new Promise(function (resolve, reject) {
            $.ajax({
                url: ctw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ctw_check_single_requirement',
                    section: section,
                    nonce: ctw_ajax.nonce,
                    overrides: overrides
                },
                success: function (response) {
                    if (response.success) {
                        updateSectionUI(section, response.data);

                        // Update platform if it was detected during this tool check, but only if user hasn't overridden it
                        if (response.data.platform && !overrides.platform) {
                            $('#current-platform').text(response.data.platform);
                        }

                        resolve(response.data);
                    } else {
                        reject(new Error('Server error'));
                    }
                },
                error: function () {
                    const $section = $('[data-section="' + section + '"]');
                    $section.removeClass('loading').addClass('incomplete');
                    $section.find('.dashicons-update').removeClass('dashicons-update').addClass('dashicons-dismiss');
                    $section.find('.status-badge.loading').removeClass('loading').addClass('error').text('✗ Error');
                    reject(new Error('AJAX error'));
                }
            });
        });
    }

    // Add hover effects for interactive elements
    $('.status-badge').on('mouseenter', function () {
        $(this).attr('title', 'Click to refresh this section');
    });

    // Keyboard accessibility
    $('.status-badge').on('keydown', function (e) {
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