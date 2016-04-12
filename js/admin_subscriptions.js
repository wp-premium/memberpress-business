jQuery(document).ready(function() {
  //Show hidden dropdown to change subscr status
  jQuery('a.status_editable').click(function() {
    var row_id = jQuery(this).attr('data-value');
    jQuery(this).hide();
    jQuery('div#status-hidden-' + row_id).show();
    return false;
  });

  //Cancel subscr status change
  jQuery('.cancel_change').click(function() {
    var row_id = jQuery(this).attr('data-value');

    jQuery('div#status-hidden-' + row_id).hide();
    jQuery('a#status-row-' + row_id).show();
  });

  //Change subscr status
  jQuery('a.status_save').click(function() {
    var row_id = jQuery(this).attr('data-value');
    var row_status = jQuery('select#status-select-' + row_id).val();
    var data = {
             action: 'mepr_subscr_edit_status',
             id: row_id,
             value: row_status
    };

    jQuery('div#status-hidden-' + row_id).hide();
    jQuery('div#status-saving-' + row_id).show();

    jQuery.post(ajaxurl, data, function(data) {
      var trimmed_data = data.replace(/^\s+|\s+$/g, ''); //Trim whitespace

      jQuery('div#status-saving-' + row_id).hide();
      jQuery('a#status-row-' + row_id).html(trimmed_data);
      jQuery('a#status-row-' + row_id).show();
    });
    return false;
  });

  //Delete SUB JS
  jQuery('a.remove-sub-row').click(function() {
    if(confirm(MeprSub.del_sub)) {
      var i = jQuery(this).attr('data-value');
      var data = {
        action: 'mepr_delete_subscription',
        id: i
      };

      jQuery.post(ajaxurl, data, function(data) {
        var trimmed_data = data.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('tr#record_' + i).fadeOut('slow');
        } else {
          alert(MeprSub.del_sub_error); //Alerts user that the subscription could not be deleted
        }
      });
    }

    return false;
  });

  //Suspend SUB JS
  jQuery('a.mepr-suspend-sub').click(function() {
    if(confirm(MeprSub.suspend_sub)) {
      var i = jQuery(this).attr('data-value');

      jQuery('tr#record_' + i + ' .mepr_loader').show();

      var data = {
        action: 'mepr_suspend_subscription',
        id: i
      };

      jQuery.post(ajaxurl, data, function(data) {
        jQuery('tr#record_' + i + ' .mepr_loader').hide();

        var trimmed_data = data.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('a#status-row-' + i).text(MeprSub.suspend_text);
          jQuery('select#status-select-' + i).val('suspended');
          jQuery('tr#record_' + i + ' .mepr-resume-sub-action').show();
          jQuery('tr#record_' + i + ' .mepr-suspend-sub-action').hide();
          alert(MeprSub.suspend_sub_success);
        } else {
          alert(MeprSub.suspend_sub_error); //Alerts user that the subscription could not be deleted
        }
      });
    }

    return false;
  });

  //Suspend SUB JS
  jQuery('a.mepr-resume-sub').click(function() {
    if(confirm(MeprSub.resume_sub)) {
      var i = jQuery(this).attr('data-value');

      jQuery('tr#record_' + i + ' .mepr_loader').show();

      var data = {
        action: 'mepr_resume_subscription',
        id: i
      };

      jQuery.post(ajaxurl, data, function(data) {
        jQuery('tr#record_' + i + ' .mepr_loader').hide();

        var trimmed_data = data.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('a#status-row-' + i).text(MeprSub.resume_text);
          jQuery('select#status-select-' + i).val('active');
          jQuery('tr#record_' + i + ' .mepr-resume-sub-action').hide();
          jQuery('tr#record_' + i + ' .mepr-suspend-sub-action').show();
          alert(MeprSub.resume_sub_success);
        } else {
          alert(MeprSub.resume_sub_error); //Alerts user that the subscription could not be deleted
        }
      });
    }

    return false;
  });

  //Cancel SUB JS
  jQuery('a.mepr-cancel-sub').click(function() {
    if(confirm(MeprSub.cancel_sub)) {
      var i = jQuery(this).attr('data-value');

      jQuery('tr#record_' + i + ' .mepr_loader').show();

      var data = {
        action: 'mepr_cancel_subscription',
        id: i
      };

      jQuery.post(ajaxurl, data, function(data) {
        jQuery('tr#record_' + i + ' .mepr_loader').hide();

        var trimmed_data = data.replace(/^\s+|\s+$/g, ''); //Trim whitespace

        if(trimmed_data == 'true') {
          jQuery('a#status-row-' + i).text(MeprSub.cancelled_text);
          jQuery('select#status-select-' + i).val('cancelled');
          jQuery('tr#record_' + i + ' .mepr-cancel-sub-action').remove();
          alert(MeprSub.cancel_sub_success);
        } else {
          alert(MeprSub.cancel_sub_error); //Alerts user that the subscription could not be deleted
        }
      });
    }

    return false;
  });

}); //End document.ready

