(function($) {
  $(document).ready(function() {
    $('.mepr-form [data-numeric]').payment('restrictNumeric');
    $('.mepr-form .cc-number').payment('formatCardNumber');
    $('.mepr-form .cc-exp').payment('formatCardExpiry');
    $('.mepr-form .cc-cvc').payment('formatCardCVC');

    var meprValidateInput = function (obj) {
      $(obj).removeClass('invalid');

      var form = $(obj).closest('.mepr-form');

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

      if ($(obj).hasClass('mepr-password-confirm')) {
        var confirmMatch = $(obj).val() === form.find('.mepr-password').val();
        mpToggleFieldValidation($(obj), confirmMatch);
      }

      if ($(obj).hasClass('mepr-coupon-code') && $(obj).val().match(/(\s|\S)/)) {
        $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').fadeIn();

        var data = {
          action: 'mepr_validate_coupon',
          code: $(obj).val(),
          prd_id: $(obj).attr("data-prd-id")
        };

        $.post(MeprI18n.ajaxurl, data, function(res) {
          $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').hide();
          res = res.trim();
          mpToggleFieldValidation($(obj), (res.toString() == 'true'));
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

    $('body').on('click', '.mepr-form .mepr-submit', function (e) {
      // We want to control if/when the form is submitted
      e.preventDefault();

      var form = $(this).closest('.mepr-form');
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

    if($('.mepr-form .cc-number').val() == '') {
      $('.mepr-form .cc-number').addClass('mepr-cards');
    }

    $('body').on('keyup', '.mepr-form .cc-number', function (e) {
      if( $(this).val() == '' ) {
        $(this).addClass('mepr-cards');
      }
      else {
        $(this).removeClass('mepr-cards');
      }
    });

    // Special handling for credit card fields (if they exist)
    $('body').on('change blur', '.mepr-form .cc-number, .mepr-form .cc-exp, .mepr-form .cc-cvc', function (e) {
      $(this).addClass('dirty');
      $(this).removeClass('invalid');

      var form = $(this).closest('.mepr-form');

      form.find('.validation').removeClass('passed failed');

      var cardNum = form.find('.cc-number').val();
      var cardExp = form.find('.cc-exp').payment('cardExpiryVal');
      var cardCvc = form.find('.cc-cvc').val();

      var cardType = $.payment.cardType(form.find('.cc-number').val());

      form.find('.dirty.cc-number').toggleClass('invalid', !$.payment.validateCardNumber(cardNum));
      form.find('.dirty.cc-exp').toggleClass('invalid', !$.payment.validateCardExpiry(cardExp));
      form.find('.dirty.cc-cvc').toggleClass('invalid', !$.payment.validateCardCVC(cardCvc, cardType));

      form.find('.dirty.cc-number').toggleClass('valid', $.payment.validateCardNumber(cardNum));
      form.find('.dirty.cc-exp').toggleClass('valid', $.payment.validateCardExpiry(cardExp));
      form.find('.dirty.cc-cvc').toggleClass('valid', $.payment.validateCardCVC(cardCvc, cardType));

      // Display errors
      form.find('.invalid').prev('.mp-form-label').find('.cc-error').show();
      form.find('.valid').prev('.mp-form-label').find('.cc-error').hide();

      form.find('.cc-type').val(cardType);

      if (form.find('.invalid').length) {
        form.find('.validation').addClass('failed');
      } else {
        form.find('.validation').addClass('passed');
      }
    });

    $('body').on('click', '.mepr_payment_method input.mepr-form-radio', function (e) {
      var form = $(this).closest('.mepr-form');

      var pmid = '#mp-pm-desc-' + $(this).val();
      var pmid_exists = (form.find(pmid).length > 0);

      form.find('.mepr-payment-method-desc-text').addClass('mepr-close');
      if (pmid_exists) { form.find(pmid).removeClass('mepr-close'); }
      form.find('.mepr-payment-method-desc-text.mepr-close').slideUp({
        duration: 200,
        complete: function() {
          if (pmid_exists) { form.find(pmid).slideDown(200); }
        }
      });
    });
  });
})(jQuery);
