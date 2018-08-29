jQuery(document).ready(function($) {
  $('.mepr_suggest_user').suggest(
    ajaxurl+'?action=mepr_user_search', {
      delay: 500,
      minchars: 2
    }
  );

  $('.mepr_suggest_subscr_num').suggest(
    ajaxurl+'?action=mepr_subscr_num_search', {
      delay: 500,
      minchars: 5
    }
  );

  var mepr_set_todays_date = function(button_selector) {
    $.post(ajaxurl, { action: 'mepr_todays_date', datetime: true }, function(res) {
      var trimmed_data = res.replace(/^\s+|\s+$/g, ''); //Trim whitespace
      $(button_selector).parent().find('input.mepr-created-at').val(trimmed_data);
    });
  };

  // no need to do this ... PHP handles this on the initial load
  //mepr_set_todays_date('a.mepr-today-button');

  // Get todays date
  $('a.mepr-today-button').click(function(e) {
    e.preventDefault();
    mepr_set_todays_date(this);
  });

  var mepr_set_default_expiration = function(expires_at_obj, set_only_empty) {
    // don't continue if there aren't any results for the selector
    if(expires_at_obj.length <= 0) { return; }

    // If we are only setting expirations on empty expires_at then bail if not empty
    var expires_at = expires_at_obj.find('input.mepr-expires-at').val();
    if(set_only_empty && expires_at && expires_at.length > 0) { return; }

    var membership_field_id = expires_at_obj.data('membership_field_id');
    var membership_id       = $('#'+membership_field_id).val();

    var created_at_field_id = expires_at_obj.data('created_at_field_id');
    var created_at          = $('#'+created_at_field_id+' input.mepr-created-at').val();

    var data = {
      action: 'mepr_default_expiration',
      product_id: membership_id,
      created_at: created_at
    };

    $.post(ajaxurl, data, function(res) {
      var trimmed_data = res.replace(/^\s+|\s+$/g, ''); //Trim whitespace
      expires_at_obj.find('input.mepr-expires-at').val(trimmed_data);
    });
  };

  //This is causing problems
  //mepr_set_default_expiration($('a.mepr-default-expiration-button').parent(), true);

  // Get default expiration date
  $('a.mepr-default-expiration-button').click(function(e) {
    e.preventDefault();
    mepr_set_default_expiration($(this).parent(), false);
  });

  // Get default expiration date
  $('select.mepr-membership-dropdown').change(function(e) {
    e.preventDefault();
    var expires_at_field_id = $(this).data('expires_at_field_id');

    if(expires_at_field_id != undefined && expires_at_field_id && expires_at_field_id.length>0) {
      mepr_set_default_expiration($('#' + expires_at_field_id), false);
    }
  });

  // Get lifetime expiration
  $('a.mepr-lifetime-expiration-button').click(function(e) {
    e.preventDefault();
    $(this).parent().find('input.mepr-expires-at').val('');
  });
});

