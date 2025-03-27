jQuery(document).ready(function($) {
    // Handle Stake Form Submission
    $('#mkps-stake-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        var feedback = $('#mkps-stake-feedback');

        feedback.html('Processing... Please Wait');

        $.ajax({
            url: mkps_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mkps_submit_stake',
                nonce: mkps_ajax_object.nonce,
                mkps_stake_points: $('#mkps_stake_points').val()
            },
            success: function(response) {
                if (response.success) {
                    feedback.html(response.data.message).css('color', 'green');
                    $('#mkps-stake-form')[0].reset();
                } else {
                    feedback.html(response.data.message).css('color', 'red');
                }
            },
            error: function() {
                feedback.html('An error occurred. Please try again.').css('color', 'red');
            }
        });
    });

    // Toggle Notifications Panel
    $('#mkps-notifications-toggle').on('click', function() {
        $('#mkps-notifications-panel').slideToggle();
    });

    // Handle Stake Acceptance via AJAX
    $('.mkps-accept-button').on('click', function() {
        var button = $(this);
        var stakeId = button.data('stake-id');
        var spinner = button.find('.mkps-spinner');

        spinner.show();
        button.prop('disabled', true);

        $.ajax({
            url: mkps_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mkps_accept_stake',
                nonce: mkps_ajax_object.nonce,
                stake_id: stakeId
            },
            success: function(response) {
                if (response.success) {
                    button.replaceWith('<span class="mkps-accepted-text">' + response.data.message + '</span>');
                } else {
                    alert(response.data.message);
                    spinner.hide();
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                spinner.hide();
                button.prop('disabled', false);
            }
        });
    });

    // Handle Available Stakes Button Click
    $('.mkps-available-stakes-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var panel = $('#mkps-stakes-panel');
        var nonce = button.data('nonce');

        if (panel.is(':visible')) {
            panel.slideUp();
        } else {
            panel.slideDown();
            if (!panel.html().trim()) {
                $.ajax({
                    url: mkps_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mkps_load_notifications',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            panel.html(response.data.html);
                        } else {
                            panel.html('<p>' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        panel.html('<p>An error occurred. Please try again.</p>');
                    }
                });
            }
        }
    });

    // Update Tab Title with Count Dynamically
    function updateTabTitle() {
        var count = $('.mkps-notification-bubble').text() || 0;
        // Adjust this selector based on your profile manager's tab structure (e.g., UM or WP Profile)
        var tabTitle = $('.um-form .um-profile-nav .um-profile-nav-item:contains("Available Stakes")'); // Replace with correct selector
        if (tabTitle.length) {
            if (count > 0 && !tabTitle.find('.mkps-notification-bubble').length) {
                tabTitle.append(' <span class="mkps-notification-bubble">' + count + '</span>');
            } else if (count == 0) {
                tabTitle.find('.mkps-notification-bubble').remove();
            }
        }
    }

    // Run on page load and when AJAX or DOM changes
    $(document).ready(updateTabTitle);
    $(document).ajaxComplete(updateTabTitle);
    $(document).on('DOMNodeInserted', updateTabTitle);
});