(function($) {
  $(document).ready(function() {
    $('.mepr-checkout-form [data-numeric]').payment('restrictNumeric');
    $('.mepr-checkout-form .cc-number').payment('formatCardNumber');
    $('.mepr-checkout-form .cc-exp').payment('formatCardExpiry');
    $('.mepr-checkout-form .cc-cvc').payment('formatCardCVC');

    $('body').on('click', '.mepr-checkout-form .mepr-submit', function (e) {
      // We want to control if/when the form is submitted
      e.preventDefault();

      var form = $(this).closest('.mepr-checkout-form');
      var button = $(this);

      if (0 < form.find('.invalid').length) {
        form.find('.validation').addClass('failed');
      }
      else {
        form.find('.validation').addClass('passed');
        this.disabled = true;
        $('.mepr-loading-gif').show();

        $(this).trigger('mepr-checkout-submit',[form]);
        //form.submit(); // This is handled in the appropriate gateway javascript handler
      }
    });

    if($('.mepr-checkout-form .cc-number').val() == '') {
      $('.mepr-checkout-form .cc-number').addClass('mepr-cards');
    }

    $('body').on('keyup', '.mepr-checkout-form .cc-number', function (e) {
      if( $(this).val() == '' ) {
        $(this).addClass('mepr-cards');
      }
      else {
        $(this).removeClass('mepr-cards');
      }
    });

    // Special handling for credit card fields (if they exist)
    $('body').on('change blur', '.mepr-checkout-form .cc-number, .mepr-checkout-form .cc-exp, .mepr-checkout-form .cc-cvc', function (e) {
      $(this).addClass('dirty');
      $(this).removeClass('invalid');

      var form = $(this).closest('.mepr-checkout-form');

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
  });
})(jQuery);

