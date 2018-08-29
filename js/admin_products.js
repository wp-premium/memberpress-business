(function($) {
  $(document).ready(function() {
  //NAV control for membership options meta box
    //MAIN NAV TABS CONTROL
    //Hide all but first tab
    $('div.product_options_page').hide();
    $('div.registration').show();

    $('a.main-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      var chosen = $(this).attr('id');

      $('a.main-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.product_options_page').hide();
      $('div.' + chosen).show();

      return false;
    });

    if( $("#_mepr_product_period_type").val()=='lifetime' ) {
      $('#mepr-product-billing-type').val('single');
    }
    else {
      $('#mepr-product-billing-type').val('recurring');
    }

    var mepr_display_billing_type = function() {
      // If price is zero don't allow recurring
      if( parseFloat($('#_mepr_product_price').val()) <= 0.00 ) {
        $('#mepr-product-billing-type').val('single');
        $('#mepr-product-billing-type').prop('disabled', true);
      }
      else {
        $('#mepr-product-billing-type').prop('disabled', false);
      }

      if( $('#mepr-product-billing-type').val() == 'recurring' ) {
        $('#mepr-non-recurring-options').slideUp({ complete: function() {
          $('#mepr-recurring-options').slideDown();
        }});
      }
      else if( $('#mepr-product-billing-type').val() == 'single' ) {
        $('#mepr-recurring-options').slideUp( { complete: function() {
          $('#mepr-non-recurring-options').slideDown();
          $("#_mepr_product_period_type").val('lifetime');
          $("#_mepr_product_period").val(1);
        }});
      }
    };

    mepr_display_billing_type();
    $('#mepr-product-billing-type').change( mepr_display_billing_type );
    $('#_mepr_product_price').blur( mepr_display_billing_type );

    $('#_mepr_expire_after').spinner({ min: 1 });

    // period and period_type UI id's
    var ptid  = '#_mepr_product_period_type-presets';
    var cpid  = '#_mepr_product_period-custom';
    var cptid = '#_mepr_product_period_type-custom';

    var mepr_period_types_init = function() {
      var period = parseInt($( '#' + $(ptid).data('period-id') ).val(), 10);
      var period_type = $( '#' + $(ptid).data('period-type-id') ).val();

      $(cpid).spinner({ min: 2, max: 12 }); // Default
      $(cpid).val(2); // Default
      $(cptid).val('months'); // Default

      if( period == 1 && period_type == 'months' ) {
        $(ptid).val('monthly');
      }
      else if( period == 1 && period_type == 'years' ) {
        $(ptid).val('yearly');
      }
      else if( period == 1 && period_type == 'weeks' ) {
        $(ptid).val('weekly');
      }
      else if( period == 3 && period_type == 'months' ) {
        $(ptid).val('quarterly');
      }
      else if( period == 6 && period_type == 'months' ) {
        $(ptid).val('semi-annually');
      }
      else {
        $('#mepr-product-custom-period').slideDown();
        $(ptid).val('custom');
        $(cpid).val(period);
        $(cptid).val(period_type);
      }
    };

    var mepr_period_update = function(toggle_custom) {
      if(toggle_custom) {
        $('#mepr-product-custom-period').slideUp();
      }

      if( $(ptid).val()=='monthly' ) {
        $( '#' + $(ptid).data('period-id') ).val( 1 );
        $( '#' + $(ptid).data('period-type-id') ).val('months');
      }
      else if( $(ptid).val()=='yearly' ) {
        $( '#' + $(ptid).data('period-id') ).val( 1 );
        $( '#' + $(ptid).data('period-type-id') ).val('years');
      }
      else if( $(ptid).val()=='weekly' ) {
        $( '#' + $(ptid).data('period-id') ).val( 1 );
        $( '#' + $(ptid).data('period-type-id') ).val('weeks');
      }
      else if( $(ptid).val()=='quarterly' ) {
        $( '#' + $(ptid).data('period-id') ).val( 3 );
        $( '#' + $(ptid).data('period-type-id') ).val('months');
      }
      else if( $(ptid).val()=='semi-annually' ) {
        $( '#' + $(ptid).data('period-id') ).val( 6 );
        $( '#' + $(ptid).data('period-type-id') ).val('months');
      }
      else if( $(ptid).val()=='custom' ) {
        if(toggle_custom) {
          $(cpid).val(2); // Default
          $(cptid).val('months'); // Default
          $(cpid).spinner({ min: 2, max: 12 }); // Default
          $('#mepr-product-custom-period').slideDown();
        }
        else {
          if( $(cptid).val()=='weeks' ) {
            $(cpid).spinner({ min: 2, max: 52 });
          }
          else {
            $(cpid).spinner({ min: 2, max: 12 });
          }
        }

        $( '#' + $(ptid).data('period-id') ).val( $(cpid).val() );
        $( '#' + $(ptid).data('period-type-id') ).val( $(cptid).val() );
      }
    };

    mepr_period_types_init();
    $(ptid).change( function() { mepr_period_update(true); } );
    $(cpid).on( "spinchange", function() { mepr_period_update(false); } );
    $(cpid).on( "spinstop", function() { mepr_period_update(false); } );
    $(cptid).change( function() { mepr_period_update(false); } );

  //Trial Period Checkbox && limit cycles checkbox stuff
    //trial period
    if($('#_mepr_product_trial').is(":checked")) {
      $('.mepr-product-trial-hidden').slideDown();
    } else {
      $('.mepr-product-trial-hidden').slideUp();
    }
    $('#_mepr_product_trial').click(function() {
      $('.mepr-product-trial-hidden').slideToggle();
    });

    $('#_mepr_product_trial_days').spinner({ min: 0 });

    //limit cycles
    if($('#_mepr_product_limit_cycles').is(":checked")) {
      $('.mepr-product-limit-cycles-hidden').slideDown();
    } else {
      $('.mepr-product-limit-cycles-hidden').slideUp();
    }
    $('#_mepr_product_limit_cycles').click(function() {
      $('.mepr-product-limit-cycles-hidden').slideToggle();
    });

    $('#_mepr_product_limit_cycles_num').spinner({ min: 1 });

    // Expire Access
    var mepr_display_expire_access = function() {
      var today = $.datepicker.formatDate('yy-mm-dd', new Date());

      if( $('#_mepr_expire_after').val() == '' ) {
        $('#_mepr_expire_after').val(1);
        $('#_mepr_expire_unit').val('days');
      }

      if( $('#_mepr_expire_fixed').val() == '' ) {
        $('#_mepr_expire_fixed').val(today);
      }

      if( $('#_mepr_expire_type').val() == 'none' ) {
        $('.mepr-product-expire-delay').slideUp({complete: function() {
          $('#_mepr_expire_after').val(1);
          $('#_mepr_expire_unit').val('days');
          $('#_mepr_allow_renewal').prop('checked', false);
          $('#_mepr_allow_renewal-fixed').prop('checked', false); //Same as above really
        }});
        $('.mepr-product-expire-fixed').slideUp({complete: function() {
          $('#_mepr_expire_fixed').val(today);
        }});
      }
      else if( $('#_mepr_expire_type').val() == 'delay' ) {
        $('.mepr-product-expire-fixed').slideUp({complete: function() {
          $('#_mepr_expire_fixed').val(today);
          $('.mepr-product-expire-delay').slideDown();
        }});
      }
      else if( $('#_mepr_expire_type').val() == 'fixed' ) {
        $('.mepr-product-expire-delay').slideUp({complete: function() {
          $('#_mepr_expire_after').val(1);
          $('#_mepr_expire_unit').val('days');
          $('.mepr-product-expire-fixed').slideDown();
        }});
      }
    };

    mepr_display_expire_access();
    $('#_mepr_expire_type').change(mepr_display_expire_access);

  //Custom thank you message checkbox
    if($('#_mepr_thank_you_page_enabled').is(":checked")) {
      $('#_mepr_thank_you_page_type_message:checked, #_mepr_thank_you_page_type_page:checked').nextAll('div:first').show();
      $('div#mepr-product-thank-you-area').show();
    } else {
      $('div#mepr-product-thank-you-area').hide();
    }
    $('#_mepr_thank_you_page_enabled').click(function() {
      $('#_mepr_thank_you_page_type_message:checked, #_mepr_thank_you_page_type_page:checked').nextAll('div:first').show();
      $('div#mepr-product-thank-you-area').slideToggle();
    });
    $('#_mepr_thank_you_page_type_message').on('click', function() {
      $('#mepr-product-thank-you-page-id').hide();
      $('#mepr-product-thank-you-message').show();
    });
    $('#_mepr_thank_you_page_type_page').on('click', function() {
      $('#mepr-product-thank-you-message').hide();
      $('#mepr-product-thank-you-page-id').show();
    });

/*****************************************************************************/
  //CUSTOM LOGIN REDIRECT STUFF
    //Custom Login Redirect Enabled checkbox
    if($('#_mepr_custom_login_urls_enabled').is(":checked")) {
      $('div#mepr-custom-login-urls-area').show();
    } else {
      $('div#mepr-custom-login-urls-area').hide();
    }
    $('#_mepr_custom_login_urls_enabled').click(function() {
      $('div#mepr-custom-login-urls-area').slideToggle();
    });

    //Add new URL row
    $('a.custom-login-urls-new-url').click(function() {
      var new_url_row = get_new_url_row();

      $(new_url_row).hide().appendTo('ul#custom-login-urls-list').fadeIn(500);

      return false;
    });

    //Remove url row
    $('body').on('click', 'a.custom_login_urls_remove_url', function() {
      var answer = confirm("Are you sure?");

      if(answer) {
        $(this).parent().parent().parent().fadeOut(500, function() {
          $(this).remove();
        });
      }

      return false;
    });

    //Get a url row
    function get_new_url_row() {
      return '<li> \
              <span class="custom_login_urls_item">\
                <label>URL</label>\
                <input type="text" name="_mepr_custom_login_urls[]" value="" size="50" />\
                &nbsp;\
                <label>Login #</label>\
                <input type="text" name="_mepr_custom_login_urls_count[]" value="1" size="3" /> \
                <span class="remove-span">\
                  <a href="" class="custom_login_urls_remove_url" title="Remove URL"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>\
                </span>\
              </span>\
            </li>';
    }

/*****************************************************************************/
  //Pricing Page stuff
    //Make benefits sortable
    $(function() {
      $('#sortable-benefits').sortable({
        stop: function() {
          load_pricing_preview(); //Update the preview pane when changing order
        }
      });
    });
    //Add new benefit li
    $('a.add-new-benefit').click(function() {
      line = '<li class="benefit-item">\
                <input type="text" name="_mepr_product_pricing_benefits[]" class="benefit-input" value="" />\
                <span class="remove-span">\
                  <a href="" class="remove-benefit-item" title="' + MeprProducts.removeBenefitStr + '"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>\
                </span>\
              </li>';
      $('#sortable-benefits').append(line);
      load_pricing_preview();
      return false;
    });
    //Remove a benefit li
    $('body').on('click', 'a.remove-benefit-item', function() {
      $(this).parent().parent().remove();
      load_pricing_preview();
      return false;
    });
    //Get the price str via AJAX
    function set_product_price_str(prod_id) {
      $('div.mepr-price-box-price').hide();
      $('span.mepr-price-box-price-loading').show();
      var data = {
        action: 'mepr_get_product_price_str',
        product_id: prod_id
      };
      $.post(ajaxurl, data, function(response) {
        $('div.mepr-price-box-price').html(response);
        $('span.mepr-price-box-price-loading').hide();
        $('div.mepr-price-box-price').show();
      });
    }
    //Load preview pane defaults
    function load_pricing_preview() {
      //Load the benefits
      var benefits = "";
      $('input.benefit-input').each(function() {
        benefits = benefits + "<div class=\"mepr-price-box-benefits-item\">" + $(this).val() + "</div>";
      });
      //Load the title the user typed if creating a new post
      if($('#_mepr_product_pricing_title').val() == '') {
        $('#_mepr_product_pricing_title').val($('input#title').val());
        $('#_mepr_product_pricing_title').keyup();
      } else {
        $('div.mepr-price-box-title').html($('#_mepr_product_pricing_title').val());
      }
      //Load the price stuff
      if($('#_mepr_product_pricing_show_price').is(":checked")) {
        var prod_id = $('#mepr-price-box-configuration').attr('data-value');
        set_product_price_str(prod_id);
      } else {
        $('div.mepr-price-box-price').html('');
      }
      //Load everything else
      $('div.mepr-price-box-heading').html($('#_mepr_product_pricing_heading_text').val());
      $('div.mepr-price-box-benefits-list').html(benefits);
      $('div.mepr-price-box-footer').html($('#_mepr_product_pricing_footer_text').val());
      $('div.mepr-price-box-button a').html($('#_mepr_product_pricing_button_text').val());
    }
  //Update preview when these things happen
    //Call this once when the page loads to get the initial preview
    load_pricing_preview();
    $('body').on('blur', 'div.pricing-options-pane input,\
      div.pricing-options-pane textarea,\
      input#title', function() {
      load_pricing_preview();
    });
    $('#_mepr_product_pricing_show_price').click(function() {
      load_pricing_preview();
    });
    //Add highlight class to preview box
    $('#_mepr_product_is_highlighted').click(function() {
      if($(this).is(':checked'))
        $('div.mepr-price-box').addClass('highlighted');
      else
        $('div.mepr-price-box').removeClass('highlighted');
    });

  //Who Can Purchase? stuff
    //Make who can purchase list sortable
    $(function() {
      $('ol#who-can-purchase-list').sortable();
    });
    //Show/Hide memberships dropdown
    $('body').on('change', 'select.user_types_dropdown', function() {
      var id = $(this).attr('data-value');
      if($(this).val() == 'members') {
        $('span#who_have_purchased-' + id).show();
      } else {
        $('span#who_have_purchased-' + id).hide();
      }
    });
    //Add a new who can purchase row
    $('a.add-new-who').click(function() {
      var id = Math.floor(Math.random() * 995000) + 5000;
      $('div#who_can_purchase_hidden_row span.who_have_purchased').attr('id', 'who_have_purchased-' + id);
      $('div#who_can_purchase_hidden_row span.who_have_purchased').hide();
      $('div#who_can_purchase_hidden_row select.user_types_dropdown').attr('data-value', id);
      $('ol#who-can-purchase-list').append($('div#who_can_purchase_hidden_row').html());

      return false;
    });
    //Remove a who can purchase row
    $('body').on('click', 'a.remove-who-can-purchase-rule', function() {
      $(this).parent().parent().remove();

      return false;
    });

/*****************************************************************************/
  //Misc stuff

    // Page Template Toggle
    if( $('#_mepr_use_custom_template').is(':checked') ) {
      $('#mepr-custom-page-template-select').show();
    }

    $('#_mepr_use_custom_template').click( function() {
      if($(this).is(':checked')) {
        $('#mepr-custom-page-template-select').slideDown();
      }
      else {
        $('#mepr-custom-page-template-select').slideUp();
      }
    });

    $('#mepr-product-active-payment-methods').sortable({
      stop: function() {
        rebuild_product_payment_json();
      }
    });

//MEMBERSHIP PAYMENT METHODS STUFF
    if($('#_mepr_customize_payment_methods').is(':checked')) {
      $('#mepr-product-payment-methods').show();
    }
    else {
      $('#mepr-product-payment-methods').hide();
    }

    $('#_mepr_customize_payment_methods').click( function(e) {
      $('#mepr-product-payment-methods').slideToggle('fast');
    });

    if( $('#mepr-product-inactive-payment-methods li').size() >= 1 ) {
      $('#mepr-product-inactive-payment-methods').show();
      $('#mepr-product-inactive-payment-methods-title').show();
    }

    $('#mepr-product-active-payment-methods').on('change', 'input', function(e) {
      if( $('#mepr-product-active-payment-methods li').size() > 1 ) {
        $('#mepr-product-inactive-payment-methods').append( $(this).parent() );
        $('#mepr-product-inactive-payment-methods').show();
        $('#mepr-product-inactive-payment-methods-title').show();
        rebuild_product_payment_json();
      }
      else {
        $(this).prop('checked', true);
      }
    });

    $('#mepr-product-inactive-payment-methods').on('change', 'input', function(e) {
      $('#mepr-product-active-payment-methods').append( $(this).parent() );
      rebuild_product_payment_json();

      if( $('#mepr-product-inactive-payment-methods li').size() < 1 ) {
        $('#mepr-product-inactive-payment-methods').hide();
        $('#mepr-product-inactive-payment-methods-title').hide();
      }
    });

    function rebuild_product_payment_json() {
      var json = [];
      $("#mepr-product-active-payment-methods input").each( function() {
        json.push($(this).attr('data-id'));
      });

      $("#mepr-product-payment-methods-json").text(JSON.stringify(json));
    }

//CUSTOM USER INFORMATION FIELDS STUFF
    if($('#_mepr_customize_profile_fields').is(':checked')) {
      $('#mepr-product-profile-fields').show();
    } else {
      $('#mepr-product-profile-fields').hide();
    }

    if($('#_mepr_customize_profile_fields').is(':checked') && $('#mepr-product-inactive-profile-fields li').length > 0) {
      $('#mepr-product-inactive-profile-fields-title').show();
      $('#mepr-product-inactive-profile-fields').show();
    }

    $('#_mepr_customize_profile_fields').click(function() {
      $('#mepr-product-profile-fields').slideToggle('fast');
    });

    $('body').on('click', '#mepr-product-active-profile-fields .mepr-profile-field-row', function() {
      $('#mepr-product-inactive-profile-fields-title').show();
      $('#mepr-product-inactive-profile-fields').show();
      $('.mepr-profile-field-box', this).attr('checked', false);
      $('#mepr-product-inactive-profile-fields').append($(this));
    });

    $('body').on('click', '#mepr-product-inactive-profile-fields .mepr-profile-field-row', function() {
      $('.mepr-profile-field-box', this).attr('checked', true);
      $('#mepr-product-active-profile-fields').append($(this));

      if($('#mepr-product-inactive-profile-fields li').length == 0) {
        $('#mepr-product-inactive-profile-fields-title').hide();
        $('#mepr-product-inactive-profile-fields').hide();
      }
    });

    // Show the custom register price text box when custom is selected
    action = (($(MeprProducts.register_price_action_id).val()=='custom')?'show':'hide');
    $(MeprProducts.register_price_id).parent()[action]();
    $(MeprProducts.register_price_action_id).change(function() {
      var act = (($(MeprProducts.register_price_action_id).val()=='custom')?'slideDown':'slideUp');
      $(MeprProducts.register_price_id).parent()[act]('fast');
    });

    //Disable Address Fields on free prds
    if(parseFloat($('#_mepr_product_price').val()) > parseFloat(0.00)) {
      $('#_mepr_disable_address_fields').attr('checked', false);
      $('#mepr-product-disable-address-fields-wrap').hide();
    }
    $('#_mepr_product_price').blur(function() {
      if(parseFloat($('#_mepr_product_price').val()) > parseFloat(0.00)) {
        $('#_mepr_disable_address_fields').attr('checked', false);
        $('#mepr-product-disable-address-fields-wrap').fadeOut();
      }
      else {
        $('#mepr-product-disable-address-fields-wrap').fadeIn();
      }
    });

    // Enable all prices
    var check_pricing_display = function() {
      if($('#mepr-pricing-display').val()=='custom') {
        $('#mepr-custom-pricing-display').slideDown();
      }
      else {
        $('#mepr-custom-pricing-display').slideUp();
      }
    };

    check_pricing_display();
    $('#mepr-pricing-display').on('change', function() {
      check_pricing_display();
    });
  });
})(jQuery);
