(function($) {
  $(document).ready(function() {
    var meprValidateInput = function (obj) {
      $(obj).removeClass('invalid');

      var form = $(obj).closest('.mepr-signup-form');

      if ($(obj).attr('required') !== undefined) {
        var notBlank = true;
        if ($(obj).is('input') || $(obj).is('select') || $(obj).is('textarea')) {
          notBlank = mpValidateNotBlank($(obj).val());
        }
        else if ($(obj).hasClass('mepr-checkbox-field')) {
          notBlank = $(obj).find('input').is(':checked');
        }
        else if ($(obj).hasClass('mepr-radios-field') || $(obj).hasClass('mepr-checkboxes-field')) {
          var input_vals = [];
          $.each($(obj).find('input'), function (i, obj) {
            if($(obj).is(':checked')) {
              input_vals.push(true);
            }
          });

          notBlank = mpValidateNotBlank(input_vals);
        }

        mpToggleFieldValidation($(obj), notBlank);
      }

      // Validate actual email only if it's not empty otherwise let the required/un-required logic hold
      if ($(obj).attr('type')==='email' && $(obj).val().length > 0) {
        var validEmail = mpValidateEmail($(obj).val());
        mpToggleFieldValidation($(obj), validEmail);
      }

      // Validate the URL by using the browser validation functions
      if ($(obj).attr('type')==='url' && $(obj).val().length > 0) {
        var validURL = $(obj).is(':valid');
        mpToggleFieldValidation($(obj), validURL);
      }

      if ($(obj).hasClass('mepr-password-confirm')) {
        var confirmMatch = $(obj).val() === form.find('.mepr-password').val();
        mpToggleFieldValidation($(obj), confirmMatch);
      }

      if ($(obj).hasClass('mepr-coupon-code') && $(obj).val().match(/(\s|\S)/)) {
        $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').fadeIn();

        var data = {
          action: 'mepr_validate_coupon',
          code: $(obj).val(),
          prd_id: $(obj).attr("data-prd-id"),
          coupon_nonce: MeprSignup.coupon_nonce
        };

        $.post(MeprI18n.ajaxurl, data, function(res) {
          $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').hide();
          res = res.trim();
          mpToggleFieldValidation($(obj), (res.toString() == 'true'));

          if(res.toString() == 'true') {
            var data_two = {
              action: 'mepr_update_price_string_with_coupon',
              code: $(obj).val(),
              prd_id: $(obj).attr("data-prd-id"),
              coupon_nonce: MeprSignup.coupon_nonce
            };

            $.post(MeprI18n.ajaxurl, data_two, function(res_two) {
              res_two = res_two.trim();
              if(res_two.toString() != 'false') {
                form.find('div.mepr_price_cell').text(res_two);
                $('body').animate( {
                  scrollTop: form.find('div.mepr_price_cell').offset().top
                }, 200, function() {
                  form.find('div.mepr_price_cell').parent().hide();
                  form.find('div.mepr_price_cell').parent().fadeIn(1000);
                });
              }
            });
          }
        });
      }

      $(obj).trigger('mepr-validate-input');
    };

    $('body').on('focus', '.mepr-form .mepr-form-input', function (e) {
      $(this).prev('.mp-form-label').find('.cc-error').hide();
      $(this).removeClass('invalid');
    });

    $('body').on('blur', '.mepr-form .mepr-form-input', function (e) {
      //Don't validate date fields here, wait til the push the submit button
      if(!$(this).hasClass('mepr-date-picker')) {
        meprValidateInput(this);
      }
    });

    // Specific to validating with the datepicker ui text field
    $('body').on('mepr-date-picker-closed', '.mepr-form .mepr-form-input.mepr-date-picker', function (e, date, inst) {
      meprValidateInput(this);
    });

    $('body').on('click', '.mepr-signup-form .mepr-submit', function (e) {
      // We want to control if/when the form is submitted
      e.preventDefault();

      var form = $(this).closest('.mepr-signup-form');
      var button = $(this);

      $.each(form.find('.mepr-form-input'), function(i,obj) {
        meprValidateInput(obj);
      });

      if (0 < form.find('.invalid').length) {
        form.find('.validation').addClass('failed');
      }
      else {
        form.find('.validation').addClass('passed');
        this.disabled = true;
        $('.mepr-loading-gif').show();
        $(this).trigger('mepr-register-submit');
        form.submit();
      }
    });

    $('body').on('click', '.mepr-signup-form .mepr_payment_method input.mepr-form-radio', function (e) {
      var form = $(this).closest('.mepr-signup-form');

      var pmid = '#mp-pm-desc-' + $(this).val();
      var pmid_exists = (form.find(pmid).length > 0);

      form.find('.mepr-payment-method-desc-text').addClass('mepr-close');

      if(pmid_exists) {
        form.find(pmid).removeClass('mepr-close');
      }

      //If nothing has the mepr-close class, we still need to show this one's description
      var mepr_close_exists = (form.find('.mepr-payment-method-desc-text.mepr-close').length > 0);

      if(mepr_close_exists) {
        form.find('.mepr-payment-method-desc-text.mepr-close').slideUp({
          duration: 200,
          complete: function() {
            if(pmid_exists) {
              form.find(pmid).slideDown(200);
            }
          }
        });
      } else {
        if(pmid_exists) {
          form.find(pmid).slideDown(200);
        }
      }
    });
  });
})(jQuery);

