(function ($) {
  // this identifies your website in the createToken call below
  Stripe.setPublishableKey(MeprStripeGateway.public_key);

  $(document).ready(function() {
    $("#payment-form").submit(function(e) {
      e.preventDefault();
      // disable the submit button to prevent repeated clicks
      //$('.submit-button').attr("disabled", "disabled");
      $('.stripe-loading-gif').show();

      var exp = $('.cc-exp').payment('cardExpiryVal');

      var tok_args = {
        name: $('.card-name').val(),
        number: $('.card-number').val(),
        cvc: $('.card-cvc').val(),
        exp_month: exp.month,
        exp_year: exp.year
      };

      // Send address if it's there
      if( $('.card-address-1').length != 0 ) { tok_args['address_line1'] = $('.card-address-1').val(); }
      if( $('.card-address-2').length != 0 ) { tok_args['address_line2'] = $('.card-address-2').val(); }
      if( $('.card-city').length != 0 ) { tok_args['address_city'] = $('.card-city').val(); }
      if( $('.card-state').length != 0 ) { tok_args['address_state'] = $('.card-state').val(); }
      if( $('.card-zip').length != 0 ) { tok_args['address_zip'] = $('.card-zip').val(); }
      if( $('.card-country').length != 0 ) { tok_args['address_country'] = $('.card-country').val(); }

      // createToken returns immediately - the supplied callback submits the form if there are no errors
      Stripe.createToken( tok_args, function(status, response) {
        //console.info('message', response);
        if(response.error) {
          // re-enable the submit button
          $('.mepr-submit').removeAttr("disabled");
          // show the errors on the form
          $('.mepr-stripe-errors').html(response.error.message);
          $('.mepr-stripe-errors').addClass('mepr_error');
          // hide the spinning gif bro
          $('.mepr-loading-gif').hide();
        } else {
          $('.mepr-stripe-errors').removeClass('mepr_error');
          var form$ = $("#payment-form");
          // token contains id, last4, and card type
          var token = response['id'];
          // insert the token into the form so it gets submitted to the server
          form$.append("<input type='hidden' name='stripe_token' value='" + token + "' />");
          // and submit
          form$.get(0).submit();
        }
      });
      return false; // submit from callback
    });
  });
})(jQuery);
