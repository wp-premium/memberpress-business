jQuery(document).ready(function() {
  //Showing editable
  jQuery('.status_initial a').hover(
  function() {
    jQuery(this).css({'color' : 'red', 'cursor' : 'pointer'});
  },
  function() {
    jQuery(this).css({'color' : '#21759B', 'cursor' : 'auto'});
  });

  //Click initial status field, show select
  jQuery('.status_initial').click(function() {
    var i = jQuery(this).attr('data-value');
    jQuery(this).hide();
    jQuery('.status_editable_'+i).show();
    return false;
  });

  //Click cancel
  jQuery('.cancel_change').click(function() {
    var i = jQuery(this).attr('data-value');
    jQuery('div.status_editable_' + i).hide();
    jQuery('div.status_initial_' + i).show();
  });

  //Saving status field
  jQuery('.status_save').click(function() {
    var i = jQuery(this).attr('data-value');
    var v = jQuery('.status_edit_'+i).val();
    var data = {
             action: 'mepr_edit_status',
             id: i,
             value: v
    };
    jQuery('.status_editable_'+i).hide();
    jQuery('.status_saving_'+i).show();
    jQuery.post(ajaxurl, data, function(response) {
      var trimmed_data = response.replace(/^\s+|\s+$/g, ''); //Trim whitespace

      jQuery('.status_initial_'+i+' a').html(trimmed_data);
      jQuery('.status_saving_'+i).hide();
      jQuery('.status_initial_'+i).show();
    });
    return false;
  });

  jQuery('.mepr_suggest_user').suggest(
    ajaxurl+'?action=mepr_user_search',
    {
      delay: 500,
      minchars: 2
    }
  );

  jQuery('.mepr_suggest_subscr_num').suggest(
    ajaxurl+'?action=mepr_subscr_num_search',
    {
      delay: 500,
      minchars: 5
    }
  );

  //Delete TXN JS
  jQuery('a.remove-txn-row').click(function() {
    if(confirm(MeprTxn.del_txn)) {
      var i = jQuery(this).attr('data-value');
      var data = {
        action: 'mepr_delete_transaction',
        id: i
      };

      jQuery.post(ajaxurl, data, function(response) {
        var trimmed_data = response.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('tr#record_' + i).fadeOut('slow');
        } else {
          alert(MeprTxn.del_txn_error); //Alerts the user to the fact that the transaction could not be deleted
        }
      });
    }

    return false;
  });

  //Resend TXN Email JS
  jQuery('a.mepr_resend_txn_email').click(function() {
    var i = jQuery(this).attr('data-value');

    jQuery('tr#record_' + i + ' .mepr_loader').show();

    var data = {
      action: 'mepr_resend_txn_email',
      id: i
    };

    jQuery.post(ajaxurl, data, function(response) {
      var trimmed_data = response.replace(/^\s+|\s+$/g, ''); //Trim whitespace

      jQuery('tr#record_' + i + ' .mepr_loader').hide();

      alert(trimmed_data);
    });

    return false;
  });

  // Refund TXN JS
  jQuery('a.mepr-refund-txn').click(function() {
    if(confirm(MeprTxn.refund_txn)) {
      var i = jQuery(this).attr('data-value');
      jQuery('tr#record_' + i + ' .mepr_loader').show();
      var data = {
        action: 'mepr_refund_transaction',
        id: i
      };

      jQuery.post(ajaxurl, data, function(response) {
        jQuery('tr#record_' + i + ' .mepr_loader').hide();

        var trimmed_data = response.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('div.status_initial_' + i + ' a').text(MeprTxn.refunded_text);
          jQuery('select.status_edit_' + i).val('refunded');
          jQuery('tr#record_' + i + ' .mepr-refund-txn-action').remove();
          alert(MeprTxn.refund_txn_success); // Alerts user that the transaction could not be refunded
        } else {
          alert(MeprTxn.refund_txn_error + ": " + response); // Alerts user that the transaction could not be refunded
        }
      });
    }

    return false;
  });

  // Get todays date
  jQuery('a.mepr-today-button').click( function(e) {
    var data_id = jQuery(this).attr('data-id');

    jQuery.post(ajaxurl, { action: 'mepr_todays_date' },
                function(res) {
                  var trimmed_data = res.replace(/^\s+|\s+$/g, ''); //Trim whitespace
                   jQuery( '#' + data_id ).val(trimmed_data);
                });

    return false;
  });

  // Get default expiration date
  jQuery('a.mepr-default-expiration-button').click( function(e) {
    var data = {
      action: 'mepr_default_expiration',
      product_id: jQuery('#product_id').val(),
      created_at: jQuery('#created_at').val()
    };

    jQuery.post(ajaxurl, data,
                function(res) {
                  var trimmed_data = res.replace(/^\s+|\s+$/g, ''); //Trim whitespace
                  jQuery( '#expires_at' ).val(trimmed_data);
                });

    return false;
  });

  // Get lifetime expiration
  jQuery('a.mepr-lifetime-expiration-button').click( function(e) {
    jQuery( '#expires_at' ).val('');
    return false;
  });
});
