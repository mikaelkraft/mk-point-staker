jQuery(document).ready(function($) {
    // Handle Stake Form Submission
    $('#mkps-stake-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        var feedback = $('#mkps-stake-feedback');

        feedback.html('Processing... Please Wait').css('color', '#fff');

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
                    feedback.html(response.data.message).css('color', '#38a169');
                    $('#mkps-stake-form')[0].reset();
                } else {
                    feedback.html(response.data.message).css('color', '#e53e3e');
                }
            },
            error: function() {
                feedback.html('An error occurred. Please try again.').css('color', '#e53e3e');
            }
        });
    });

    // Handle Connection Code Form Submission
    $('#mkps-connection-code-form').on('submit', function(e) {
        e.preventDefault();

        var feedback = $('#mkps-connection-code-feedback');
        feedback.html('Processing... Please Wait').css('color', '#fff');

        $.ajax({
            url: mkps_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mkps_submit_connection_code',
                nonce: mkps_ajax_object.nonce,
                mkps_connection_code: $('#mkps_connection_code').val()
            },
            success: function(response) {
                if (response.success) {
                    feedback.html(response.data.message).css('color', '#38a169');
                    $('#mkps-connection-code-form')[0].reset();
                } else {
                    feedback.html(response.data.message).css('color', '#e53e3e');
                }
            },
            error: function() {
                feedback.html('An error occurred. Please try again.').css('color', '#e53e3e');
            }
        });
    });

    // Toggle Notifications Panel
    $('#mkps-notifications-toggle').on('click', function() {
        $('#mkps-notifications-panel').slideToggle();
    });

    // Toggle Sitewide Notifications Panel
    $('#mkps-sitewide-notification').on('click', function() {
        $('.mkps-sitewide-notification-panel').slideToggle();
    });

    // Handle Stake Acceptance via AJAX
    $(document).on('click', '.mkps-accept-button', function() {
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

    // Handle Stake Cancellation via AJAX
    $(document).on('click', '.mkps-cancel-button', function() {
        var button = $(this);
        var stakeId = button.data('stake-id');

        if (confirm('Are you sure you want to cancel this stake?')) {
            $.ajax({
                url: mkps_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'mkps_cancel_stake',
                    nonce: mkps_ajax_object.nonce,
                    stake_id: stakeId
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('li').remove();
                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
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

    // Update UM Profile Tab Title with Count
    function updateUMTabTitle() {
        var count = $('.mkps-notification-bubble').text() || 0;
        var tabTitle = $('.um-profile-nav-item[data-tab="available-stakes"]');
        if (tabTitle.length) {
            if (count > 0 && !tabTitle.find('.mkps-notification-bubble').length) {
                tabTitle.append(' <span class="mkps-notification-bubble">' + count + '</span>');
            } else if (count == 0) {
                tabTitle.find('.mkps-notification-bubble').remove();
            }
        }
    }

    $(document).ready(updateUMTabTitle);
    $(document).ajaxComplete(updateUMTabTitle);
    $(document).on('DOMNodeInserted', updateUMTabTitle);
});