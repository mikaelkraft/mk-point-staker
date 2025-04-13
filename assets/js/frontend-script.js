jQuery(document).ready(function($) {
    // Initialize variables
    var notificationUpdateInterval;
    var isProcessing = false;

    // Enhanced Stake Form Handling with multi-stake support
    $('#mkps-stake-form').on('submit', function(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        isProcessing = true;
        
        var $form = $(this);
        var $feedback = $('#mkps-stake-feedback');
        var $submitBtn = $form.find('button[type="submit"]');
        var stakePoints = $('#mkps_stake_points').val().trim();
        
        // Basic validation
        if (!stakePoints || isNaN(stakePoints)) {
            showFeedback($feedback, 'Please enter a valid stake amount', 'error');
            isProcessing = false;
            return;
        }
        
        $feedback.html(createSpinner()).css('color', '');
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: mkps_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mkps_submit_stake',
                nonce: mkps_ajax_object.nonce,
                mkps_stake_points: stakePoints
            },
            success: function(response) {
                if (response.success) {
                    showFeedback($feedback, response.data.message, 'success');
                    if (response.data.refresh) {
                        setTimeout(location.reload.bind(location), 2000);
                    } else {
                        // Update UI without refresh if possible
                        updateStakesUI();
                    }
                } else {
                    showFeedback($feedback, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message 
                    ? xhr.responseJSON.data.message 
                    : 'An error occurred. Please try again.';
                showFeedback($feedback, errorMsg, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                isProcessing = false;
            }
        });
    });
  
    // Handle stake cancellation (both single and list items)
    $(document).on('click', '.mkps-cancel-stake', function(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        isProcessing = true;
        
        var $button = $(this);
        var stakeId = $button.data('stake-id');
        var $spinner = $button.find('.mkps-spinner');
        var $stakeItem = $button.closest('.mkps-stake-item');
        var $feedback = $('#mkps-stake-feedback');
        
        if (!confirm('Are you sure you want to cancel this stake? Your points will be refunded.')) {
            isProcessing = false;
            return;
        }
        
        $spinner.show();
        $button.prop('disabled', true);
        
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
                    if ($stakeItem.length) {
                        // Remove from list view
                        $stakeItem.fadeOut(300, function() {
                            $(this).remove();
                            showFeedback($feedback, response.data.message, 'success');
                            checkStakeLimit();
                        });
                    } else {
                        // Single stake view
                        showFeedback($feedback, response.data.message, 'success');
                        if (response.data.refresh) {
                            setTimeout(location.reload.bind(location), 1500);
                        }
                    }
                } else {
                    showFeedback($feedback, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message 
                    ? xhr.responseJSON.data.message 
                    : 'An error occurred while canceling the stake.';
                showFeedback($feedback, errorMsg, 'error');
            },
            complete: function() {
                $spinner.hide();
                $button.prop('disabled', false);
                isProcessing = false;
            }
        });
    });
  
    // Notification Handling
    $(document).on('click', '.mkps-sitewide-notifications li', function() {
        var $item = $(this);
        if ($item.hasClass('unread') && !isProcessing) {
            isProcessing = true;
            var index = $item.data('index');
            
            $.ajax({
                url: mkps_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'mkps_mark_notification_read',
                    nonce: mkps_ajax_object.nonce,
                    index: index
                },
                success: function() {
                    $item.removeClass('unread').addClass('read');
                    updateUnreadCount(-1);
                },
                error: function() {
                    console.error('Failed to mark notification as read');
                },
                complete: function() {
                    isProcessing = false;
                }
            });
        }
    });
  
    // Initialize notification updates
    function initNotificationUpdates() {
        updateNotificationCounts();
        notificationUpdateInterval = setInterval(updateNotificationCounts, 30000);
    }
  
    // Helper Functions
    function showFeedback($element, message, type) {
        var className = type === 'error' ? 'mkps-error' : 'mkps-success';
        $element.html('<div class="' + className + '">' + message + '</div>');
    }
  
    function createSpinner() {
        return '<div class="mkps-spinner"></div> Processing...';
    }
  
    function updateUnreadCount(change) {
        var $count = $('.mkps-unread-count');
        if ($count.length) {
            var current = parseInt($count.text()) || 0;
            var newCount = Math.max(0, current + change);
            $count.text(newCount);
            
            // Update document title
            document.title = newCount > 0 
                ? '(' + newCount + ') ' + document.title.replace(/^\(\d+\)\s/, '')
                : document.title.replace(/^\(\d+\)\s/, '');
        }
    }
  
    function updateNotificationCounts() {
        if (isProcessing) return;
        
        $.ajax({
            url: mkps_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mkps_get_notification_counts',
                nonce: mkps_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.mkps-unread-count').text(response.data.unread);
                    $('.mkps-available-count').text(response.data.available);
                    
                    if (response.data.unread > 0) {
                        document.title = '(' + response.data.unread + ') ' + document.title.replace(/^\(\d+\)\s/, '');
                    } else {
                        document.title = document.title.replace(/^\(\d+\)\s/, '');
                    }
                }
            },
            error: function() {
                console.error('Failed to update notification counts');
            }
        });
    }
  
    function checkStakeLimit() {
        if ($('.mkps-stake-item').length === 0) {
            $('.mkps-stake-limit-reached').hide();
            $('#mkps-stake-form').show();
        }
    }
  
    function updateStakesUI() {
        // This could be enhanced to dynamically update the stakes list via AJAX
        // For now, we'll just enable the form if under limit
        if ($('.mkps-stake-item').length < 3) {
            $('.mkps-stake-limit-reached').hide();
            $('#mkps-stake-form').show();
        }
        $('#mkps_stake_points').val('');
    }
  
    // Initialize
    initNotificationUpdates();
});