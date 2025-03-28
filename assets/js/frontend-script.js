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
                $('#mkps_stake-form')[0].reset();
            } else {
                feedback.html('Error: ' + response.data.message).css('color', 'red');
                console.log('AJAX Error Response:', response); // Log the full response
            }
        },
        error: function(xhr, status, error) {
            feedback.html('AJAX Failed: ' + error).css('color', 'red');
            console.log('AJAX Failure:', xhr.responseText); // Log server-side error
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
  
  // Handle Stake Cancellation via AJAX
  function addCancelButtons() {
    $('.mkps-stake-item').each(function() {
      var $stake = $(this);
      var stakeId = $stake.data('stake-id');
      var isAccepted = $stake.data('accepted');
      
      if (!isAccepted && !$stake.find('.mkps-cancel-button').length) {
        var cancelBtn = $('<button class="mkps-cancel-button button">Cancel Stake</button>');
        cancelBtn.on('click', function() {
          if (confirm('Cancel this stake? You will receive a 90% refund.')) {
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
                  $stake.remove();
                  alert(response.data.message);
                } else {
                  alert(response.data.message);
                }
              },
              error: function() {
                alert('An error occurred.');
              }
            });
          }
        });
        $stake.append(cancelBtn);
      }
    });
  }
  
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
    var tabTitle = $('.um-form .um-profile-nav .um-profile-nav-item:contains("Available Stakes")'); // Adjust selector as needed
    if (tabTitle.length) {
      if (count > 0 && !tabTitle.find('.mkps-notification-bubble').length) {
        tabTitle.append(' <span class="mkps-notification-bubble">' + count + '</span>');
      } else if (count == 0) {
        tabTitle.find('.mkps-notification-bubble').remove();
      }
    }
  }
  
  // Run on page load and when AJAX or DOM changes
  $(document).ready(function() {
    updateTabTitle();
    addCancelButtons();
  });
  $(document).ajaxComplete(function() {
    updateTabTitle();
    addCancelButtons();
  });
  $(document).on('DOMNodeInserted', updateTabTitle);
});