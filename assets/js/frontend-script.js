jQuery(document).ready(function($) {
  $('.mkps-accept-stake').on('click', function(e) {
    e.preventDefault();
    var stakeId = $(this).data('stake-id');
    $.ajax({
      url: mkps_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'mkps_accept_stake',
        nonce: mkps_ajax.stake_nonce,
        stake_id: stakeId
      },
      success: function(response) {
        if (response.success) {
          alert(response.data);
          location.reload();
        } else {
          alert(response.data);
        }
      }
    });
  });
  
  $('.mkps-cancel-stake').on('click', function(e) {
    e.preventDefault();
    var stakeId = $(this).data('stake-id');
    if (confirm('Are you sure you want to cancel this stake?')) {
      $.ajax({
        url: mkps_ajax.ajax_url,
        type: 'POST',
        data: {
          action: 'mkps_cancel_stake',
          nonce: mkps_ajax.stake_nonce,
          stake_id: stakeId
        },
        success: function(response) {
          if (response.success) {
            alert(response.data);
            location.reload();
          } else {
            alert(response.data);
          }
        }
      });
    }
  });
  
  $('#mkps-accept-code-form').on('submit', function(e) {
    e.preventDefault();
    var code = $('#mkps_connection_code').val();
    var nonce = mkps_ajax.accept_code_nonce;
    
    $.ajax({
      url: mkps_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'mkps_accept_stake_by_code',
        nonce: nonce,
        connection_code: code
      },
      success: function(response) {
        if (response.success) {
          alert(response.data);
          location.reload();
        } else {
          alert(response.data);
        }
      }
    });
  });
  
  $('.mkps-toggle-view').on('click', function() {
    var view = $(this).data('view');
    $('.mkps-toggle-view').removeClass('active');
    $(this).addClass('active');
    $('.mkps-stakes-list').removeClass('list-view card-view').addClass(view + '-view');
  });
});