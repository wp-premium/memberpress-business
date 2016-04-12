jQuery(document).ready(function($) {
  //Set the correct tab to display
  var hash = location.hash.replace('#','');

  if(hash == '') {
    hash = 'pages';
  }
  else {
    hash = hash.replace('mepr-','');
  }

  show_chosen_tab(hash);

  function show_chosen_tab(chosen) {
    var hash = '#mepr-' + chosen;

    //Adjust tab's style
    $('a.nav-tab-active').removeClass('nav-tab-active');
    $('a#' + chosen).addClass('nav-tab-active');

    //Adjust pane's style
    $('div.mepr-options-hidden-pane').hide();
    $('div#' + chosen).show();

    //Set action to the proper tab
    $('#mepr_options_form').attr('action', hash);
    $('.nav-tab-wrapper').trigger('mepr-show-nav-tab',[chosen]);
    window.location.hash = hash;
  }

  $('a.nav-tab').click(function() {
    var chosen = $(this).attr('id');

    show_chosen_tab(chosen);

    return false;
  });

  // Payment configuration options
  $('div#integration').on('click', '#mepr-add-integration', function() {
    show_integration_form();
    return false;
  });

  function show_integration_form() {
    var data = {
      action: 'mepr_gateway_form'
    };
    $.post(ajaxurl, data, function(response) {
      if( response.error === undefined ) {
        $(response.form).hide().appendTo('#integrations-list').slideDown('fast');
        $('.clippy').clippy({clippy_path: MeprOptions.jsUrl + '/clippy.swf', width: '14px'});
      }
      else {
        alert('Error');
      }
    }, 'json');
  }

  $('div#integration').on('click', '.mepr-integration-delete a', function() {
    if(confirm(MeprOptions.confirmPMDelete)) {
      $(this).parent().parent().slideUp('fast', function() {
        $(this).remove();
      });
    }
    return false;
  });

  $('div#integration').on('change', 'select.mepr-gateways-dropdown', function() {
    var data_id = $(this).attr('data-id');
    var gateway = $(this).val();
    var data = {
      action: 'mepr_gateway_form',
      g: gateway
    };
    $.post(ajaxurl, data, function(response) {
      if( response.error === undefined ) {
        $('#mepr-integration-'+data_id).replaceWith(response.form);
        $('.clippy').clippy({clippy_path: MeprOptions.jsUrl + '/clippy.swf', width: '14px'});
        if( gateway === 'MeprStripeGateway' ) {
          $('#mepr-stripe-live-keys-'+response.id).show();
        }
      }
      else {
        alert('Error');
      }
    }, 'json');
    return false;
  });

  $('input.mepr-stripe-testmode').each( function() {
    var integration = $(this).data('integration');

    if( $(this).is(':checked') ) {
      $('#mepr-stripe-test-keys-'+integration).show();
    }
    else {
      $('#mepr-stripe-live-keys-'+integration).show();
    }
  });

  $('div#integration').on('change', 'input.mepr-stripe-testmode', function() {
    var integration = $(this).data('integration');
    if( $(this).is(':checked') ) {
      $('#mepr-stripe-live-keys-'+integration).hide();
      $('#mepr-stripe-test-keys-'+integration).show();
    }
    else {
      $('#mepr-stripe-live-keys-'+integration).show();
      $('#mepr-stripe-test-keys-'+integration).hide();
    }
  });

  //Custom Fields JS
  function get_new_line()
  {
    var random_id = Math.floor(Math.random() * 100000001); //easiest way to do this
    return  '<li class="mepr-custom-field postbox"> \
              <label>' + MeprOptions.nameLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + random_id + '][name]" /> \
               \
              <label>' + MeprOptions.typeLabel + '</label> \
              <select name="mepr-custom-fields[' + random_id + '][type]" class="mepr-custom-fields-select" data-value="' + random_id + '"> \
                <option value="text">' + MeprOptions.textOption + '</option> \
                <option value="email">' + MeprOptions.emailOption + '</option> \
                <option value="date">' + MeprOptions.dateOption + '</option> \
                <option value="textarea">' + MeprOptions.textareaOption + '</option> \
                <option value="checkbox">' + MeprOptions.checkboxOption + '</option> \
                <option value="dropdown">' + MeprOptions.dropdownOption + '</option> \
                <option value="multiselect">' + MeprOptions.multiselectOption + '</option> \
                <option value="radios">' + MeprOptions.radiosOption + '</option> \
                <option value="checkboxes">' + MeprOptions.checkboxesOption + '</option> \
              </select> \
               \
              <label>' + MeprOptions.defaultLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + random_id + '][default]" /> \
               \
              <input type="checkbox" name="mepr-custom-fields[' + random_id + '][signup]" id="mepr-custom-fields-signup-' + random_id + '" /> \
              <label for="mepr-custom-fields-signup-' + random_id + '">' + MeprOptions.signupLabel + '</label> \
               \
              &nbsp;&nbsp;&nbsp;<input type="checkbox" name="mepr-custom-fields[' + random_id + '][required]" id="mepr-custom-fields-required-' + random_id + '" /> \
              <label for="mepr-custom-fields-required-' + random_id + '">' + MeprOptions.requiredLabel + '</label> \
              <input type="hidden" name="mepr-custom-fields-index[]" value="' + random_id + '" /> \
               \
              <a href="" class="mepr-custom-field-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
              <div id="dropdown-hidden-options-' + random_id + '" style="display:none;"></div> \
              \
              <input type="hidden" name="mepr-custom-fields[' + random_id + '][slug]" value="mepr_none" />\
            </li>';
  }

  function get_initial_dropdown_options(my_id)
  {
    return '<ul class="custom_options_list"> \
              <li> \
                <label>' + MeprOptions.optionNameLabel + '</label> \
                <input type="text" name="mepr-custom-fields[' + my_id + '][option][]" /> \
                 \
                <label>' + MeprOptions.optionValueLabel + '</label> \
                <input type="text" name="mepr-custom-fields[' + my_id + '][value][]" /> \
                 \
                <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
              </li> \
              <a href="" id="mepr-add-new-option" title="' + MeprOptions.addOptionLabel + '" data-value="' + my_id + '"><i class="mp-icon mp-icon-plus-circled mp-16"></i></a> \
            </ul>';
  }

  function get_new_option_line(my_id)
  {
    return '<li> \
              <label>' + MeprOptions.optionNameLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + my_id + '][option][]" /> \
               \
              <label>' + MeprOptions.optionValueLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + my_id + '][value][]" /> \
               \
              <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
            </li>';
  }

  $('a#mepr-add-new-custom-field').click(function() {
    $(this).before(get_new_line());
    return false;
  });

  $('body').on('click', 'a#mepr-add-new-option', function() {
    var my_id = $(this).attr('data-value');
    $(this).before(get_new_option_line(my_id));
    return false;
  });

  $('body').on('click', 'a.mepr-custom-field-remove', function() {
    $(this).parent().remove();
    return false;
  });
  $('body').on('click', 'a.mepr-option-remove', function() {
    $(this).parent().remove();
    return false;
  });

  $('body').on('change', 'select.mepr-custom-fields-select', function() {
    var my_id = $(this).data('value');
    var type = $(this).val();

    if( $.inArray(type,['dropdown','multiselect','radios','checkboxes']) > -1 ) {
      $('div#dropdown-hidden-options-' + my_id).html(get_initial_dropdown_options(my_id));
      $('div#dropdown-hidden-options-' + my_id).show();
    } else {
      $('div#dropdown-hidden-options-' + my_id).html('');
      $('div#dropdown-hidden-options-' + my_id).hide();
    }

    return false;
  });

  //Terms of Service JS stuff
  if($('#mepr-require-tos').is(":checked")) {
    $('div#mepr_tos_hidden').show();
  } else {
    $('div#mepr_tos_hidden').hide();
  }
  $('#mepr-require-tos').click(function() {
    $('div#mepr_tos_hidden').slideToggle('fast');
  });

  //Unauthorized stuff
  if($('#mepr-redirect-on-unauthorized').is(':checked')) {
    $('#mepr-unauthorized-redirect').slideDown();
  } else {
    $('#mepr-unauthorized-redirect').slideUp();
  }

  $('#mepr-redirect-on-unauthorized').click(function() {
    if($('#mepr-redirect-on-unauthorized').is(':checked')) {
      $('#mepr-unauthorized-redirect').slideDown();
    } else {
      $('#mepr-unauthorized-redirect').slideUp();
    }
  });

  //Unauthorized excerpts type
  var toggle_excerpt_type = function() {
    if($('#mepr-unauth-show-excerpts').is(':checked')) {
      $('#mepr-unauthorized-show-excerpts-type').slideDown();
    } else {
      $('#mepr-unauthorized-show-excerpts-type').slideUp();
    }
  };
  toggle_excerpt_type();
  $('#mepr-unauth-show-excerpts').click(toggle_excerpt_type);

  //Unauthorized excerpt size
  var toggle_excerpt_size = function() {
    if($('#mepr-unauth-excerpt-type').val()=='custom') {
      $('#mepr-unauth-excerpt-type-size').slideDown();
    } else {
      $('#mepr-unauth-excerpt-type-size').slideUp();
    }
  };

  toggle_excerpt_size();
  $('#mepr-unauth-excerpt-type').change(toggle_excerpt_size);

  //Unauthorized message toggle
  $('.mp-toggle-unauthorized-message').click( function(e) {
    e.preventDefault();
    $('.mp-unauthorized-message').slideToggle();
  });

  //Clippy
  $('.clippy').clippy({clippy_path: MeprOptions.jsUrl + '/clippy.swf', width: '14px'});

  //Make who can purchase list sortable
  $(function() {
    $('ol#custom_profile_fields').sortable();
  });

  //Hide/Show SEO Unauthorized Noindex stuff
  if($('#mepr-authorize-seo-views').is(":checked")) {
    $('div#mepr-seo-noindex-area').hide();
  } else {
    $('div#mepr-seo-noindex-area').show();
  }
  $('#mepr-authorize-seo-views').click(function() {
    $('div#mepr-seo-noindex-area').slideToggle('fast');
  });

  //Hide/Show PayWall Stuff
  if($('#mepr-paywall-enabled').is(":checked")) {
    $('div#mepr-paywall-options-area').show();
  } else {
    $('div#mepr-paywall-options-area').hide();
  }
  $('#mepr-paywall-enabled').click(function() {
    $('div#mepr-paywall-options-area').slideToggle('fast');
  });

  //PAYPAL STANDARD STUFF SHNIZZLE
  $('.advanced_mode_checkbox').each(function() {
    if($(this).is(':checked')) {
      var id = $(this).attr('data-value');
      $('.advanced_mode_row-' + id).show();
    }
  });

  $('body').on('click', '.advanced_mode_checkbox', function(e) {
    // e.preventDefault(); //Don't do this on checkbox's -- they will never uncheck apparently
    var id = $(this).attr('data-value');
    $('.advanced_mode_row-' + id).toggle();
  });

  if($('#mepr_calculate_taxes').is(':checked')) {
    $('#address-tax-info').show();
    $('input#mepr-show-address-fields').prop('checked', true);
    $('input#mepr-require-address-fields').prop('checked', true);
    $('input#mepr-show-address-fields').prop('disabled', true);
    $('input#mepr-require-address-fields').prop('disabled', true);
  }

  $('body').on('click', '#mepr_calculate_taxes', function(e) {
    if($('#mepr_calculate_taxes').is(':checked')) {
      $('#address-tax-info').show();
      $('input#mepr-show-address-fields').prop('checked', true);
      $('input#mepr-require-address-fields').prop('checked', true);
      $('input#mepr-show-address-fields').prop('disabled', true);
      $('input#mepr-require-address-fields').prop('disabled', true);
    }
    else {
      $('#address-tax-info').hide();
      $('input#mepr-show-address-fields').prop('disabled', false);
      $('input#mepr-require-address-fields').prop('disabled', false);
    }
  });

  $('body').on('click', '.mepr-tax-rate-remove', function(e) {
    e.preventDefault();
    if(confirm(MeprOptions.taxRateRemoveStr)) {
      var id = $(this).data('id');
      $.post(ajaxurl, {action:'mepr_remove_tax_rate', id:id})
        .done(function(data, stat) {
          var msg = JSON.parse(data);
          alert(msg.message);
          $('#mepr_tax_rate_row_'+id).slideUp({
            complete: function() {
              $('#mepr_tax_rate_row_'+id).remove();
            }
          });
        })
        .fail(function(data, stat, statname) {
          var msg = JSON.parse(data.responseText);
          alert('ERROR: ' + msg.error);
        }, 'json');
    }
  });

});

