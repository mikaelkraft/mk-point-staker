jQuery(document).ready(function($) {
  // Enhanced Stake Form Handling
  $('#mkps-stake-form').on('submit', function(e) {
    e.preventDefault();
    var $form = $(this);
    var $feedback = $('#mkps-stake-feedback');
    var $submitBtn = $form.find('button[type="submit"]');
    
    $feedback.html('<div class="mkps-spinner"></div> Processing...').css('color', '');
    $submitBtn.prop('disabled', true);
    
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
          $feedback.html('<div class="mkps-success">' + response.data.message + '</div>');
          if (response.data.refresh) {
            setTimeout(function() {
              location.reload();
            }, 2000);
          }
        } else {
          $feedback.html('<div class="mkps-error">' + response.data.message + '</div>');
        }
      },
      error: function() {
        $feedback.html('<div class="mkps-error">An error occurred. Please try again.</div>');
      },
      complete: function() {
        $submitBtn.prop('disabled', false);
      }
    });
  });
  
  // Enhanced Stake Cancellation
  $('#mkps-cancel-stake').on('click', function() {
    if (!confirm('Are you sure you want to cancel this stake? Your points will be refunded.')) {
      return;
    }
    
    var $button = $(this);
    var stakeId = $button.data('stake-id');
    var $spinner = $button.find('.mkps-spinner');
    
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
          $('#mkps-stake-feedback').html('<div class="mkps-success">' + response.data.message + '</div>');
          if (response.data.refresh) {
            setTimeout(function() {
              location.reload();
            }, 1500);
          }
        } else {
          alert(response.data.message);
        }
      },
      error: function() {
        alert('An error occurred. Please try again.');
      },
      complete: function() {
        $spinner.hide();
        $button.prop('disabled', false);
      }
    });
  });
  
  // Enhanced Notification Handling
  $(document).on('click', '.mkps-sitewide-notifications li', function() {
    var $item = $(this);
    if ($item.hasClass('unread')) {
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
          var $count = $('.mkps-unread-count');
          if ($count.length) {
            var current = parseInt($count.text()) || 0;
            if (current > 0) {
              $count.text(current - 1);
            }
          }
        }
      });
    }
  });
  
  // Dynamic Notification Count Update
  function updateNotificationCounts() {
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
          
          // Update browser tab title if there are unread notifications
          if (response.data.unread > 0) {
            document.title = '(' + response.data.unread + ') ' + document.title.replace(/^\(\d+\)\s/, '');
          } else {
            document.title = document.title.replace(/^\(\d+\)\s/, '');
          }
        }
      }
    });
  }
  
  // Update counts every 30 seconds
  setInterval(updateNotificationCounts, 30000);
  updateNotificationCounts();
});