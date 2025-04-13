jQuery(document).ready(function($) {
  // Initialize variables
  var notificationUpdateInterval;
  var isProcessing = false;
  
  // Toggle Notifications Panel
  $('#mkps-notifications-toggle').on('click', function(e) {
    e.preventDefault();
    $('#mkps-stakes-panel').slideToggle();
  });
  
  // Enhanced Stake Form Handling
  $('#mkps-stake-form').on('submit', function(e) {
    e.preventDefault();
    handleStakeFormSubmit();
  });
  
  // Handle Accept Stake Button
  $(document).on('click', '.mkps-accept-button', function(e) {
    e.preventDefault();
    handleStakeAcceptance($(this));
  });
  
  // Handle stake cancellation
  $(document).on('click', '.mkps-cancel-stake', function(e) {
    e.preventDefault();
    handleStakeCancellation($(this));
  });
  
  // Notification Handling
  $(document).on('click', '.mkps-sitewide-notifications li', function() {
    handleNotificationClick($(this));
  });
  
  // Initialize notification updates
  initNotificationUpdates();
  
  // Helper Functions
  function handleStakeFormSubmit() {
    if (isProcessing) return;
    isProcessing = true;
    
    var $form = $('#mkps-stake-form');
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
      success: handleStakeSubmissionResponse,
      error: handleAjaxError,
      complete: function() {
        $submitBtn.prop('disabled', false);
        isProcessing = false;
      }
    });
  }
  
  function handleStakeAcceptance($button) {
    if (isProcessing) return;
    isProcessing = true;
    
    var stakeId = $button.data('stake-id');
    var $spinner = $button.find('.mkps-spinner');
    
    $spinner.show();
    $button.prop('disabled', true);
    
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
          $button.replaceWith(
            '<span class="mkps-accepted-text">' +
            response.data.message +
            '</span>'
          );
          updateNotificationCounts();
        } else {
          showFeedback($('#mkps-stake-feedback'), response.data.message, 'error');
        }
      },
      error: handleAjaxError,
      complete: function() {
        $spinner.hide();
        $button.prop('disabled', false);
        isProcessing = false;
      }
    });
  }
  
  function handleStakeCancellation($button) {
    if (isProcessing) return;
    isProcessing = true;
    
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
            $stakeItem.fadeOut(300, function() {
              $(this).remove();
              showFeedback($feedback, response.data.message, 'success');
              checkStakeLimit();
            });
          } else {
            showFeedback($feedback, response.data.message, 'success');
            if (response.data.refresh) {
              setTimeout(location.reload.bind(location), 1500);
            }
          }
          updateNotificationCounts();
        } else {
          showFeedback($feedback, response.data.message, 'error');
        }
      },
      error: handleAjaxError,
      complete: function() {
        $spinner.hide();
        $button.prop('disabled', false);
        isProcessing = false;
      }
    });
  }
  
  function handleNotificationClick($item) {
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
        error: handleAjaxError,
        complete: function() {
          isProcessing = false;
        }
      });
    }
  }
  
  function initNotificationUpdates() {
    updateNotificationCounts();
    notificationUpdateInterval = setInterval(updateNotificationCounts, 30000);
  }
  
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
      
      document.title = newCount > 0 ?
        '(' + newCount + ') ' + document.title.replace(/^\(\d+\)\s/, '') :
        document.title.replace(/^\(\d+\)\s/, '');
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
      error: handleAjaxError
    });
  }
  
  function checkStakeLimit() {
    if ($('.mkps-stake-item').length === 0) {
      $('.mkps-stake-limit-reached').hide();
      $('#mkps-stake-form').show();
    }
  }
  
  function handleStakeSubmissionResponse(response) {
    var $feedback = $('#mkps-stake-feedback');
    if (response.success) {
      showFeedback($feedback, response.data.message, 'success');
      if (response.data.refresh) {
        setTimeout(location.reload.bind(location), 2000);
      } else {
        updateStakesUI();
      }
    } else {
      showFeedback($feedback, response.data.message, 'error');
    }
  }
  
  function updateStakesUI() {
    if ($('.mkps-stake-item').length < 3) {
      $('.mkps-stake-limit-reached').hide();
      $('#mkps-stake-form').show();
    }
    $('#mkps_stake_points').val('');
  }
  
  function handleAjaxError(xhr) {
    var errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ?
      xhr.responseJSON.data.message :
      'An error occurred. Please try again.';
    showFeedback($('#mkps-stake-feedback'), errorMsg, 'error');
    console.error('AJAX Error:', errorMsg);
  }
});