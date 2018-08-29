(function ($) {
  $(document).ready(function() {
    //Trigger a click on stripe checkout automatically
    var done = false; //Prevent double submit (for some reason)
    if(!done) {
      $("button.stripe-button-el").trigger("click");
      done = true;
    }

    var stripe = Stripe(MeprStripeGateway.public_key);
    var elements = stripe.elements();
    var card = elements.create('card');
    card.mount('#card-element');
    card.addEventListener('change', function(event) {
      var displayError = document.getElementById('card-errors');
      if (event.error) {
        displayError.textContent = event.error.message;
      } else {
        displayError.textContent = '';
      }
    });

    var stripePaymentForm = document.getElementById('mepr-stripe-payment-form');
    stripePaymentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      $(stripePaymentForm).find('.mepr-submit').disabled = true;
      $(stripePaymentForm).find('.mepr-loading-gif').show();

      // Returns the form fields in a pretty key/value hash
      var formData = $(stripePaymentForm).serializeArray().reduce(function(obj, item) {
        obj[item.name] = item.value;
        return obj;
      }, {});
      var cardData = {
        name: formData['card-name']
      };
      // Merges in the address fields if required for taxes
      if(formData['address_required'] == 1) {
        $.extend(cardData, {
          address_line1:   formData['card-address-1'],
          address_line2:   formData['card-address-2'],
          address_city:    formData['card-city'],
          address_state:   formData['card-state'],
          address_zip:     formData['card-zip'],
          address_country: formData['card-country']
        });
      }

      stripe.createToken(card, cardData).then(function(result) {
        if (result.error) {
          // re-enable the submit button
          $(stripePaymentForm).find('.mepr-submit').disabled = false;
          // Inform the user if there was an error
          var errorElement = document.getElementById('card-errors');
          errorElement.textContent = result.error.message;
        } else {
          if(!$(stripePaymentForm).hasClass('mepr-payment-submitted')) {
            $(stripePaymentForm).addClass('mepr-payment-submitted');
            var hiddenInput = document.createElement('input');
            hiddenInput.setAttribute('type', 'hidden');
            hiddenInput.setAttribute('name', 'stripeToken');
            hiddenInput.setAttribute('value', result.token.id);
            stripePaymentForm.appendChild(hiddenInput);
            stripePaymentForm.submit();
          }
        }
      });

      return false; // submit from callback
    });
  });
})(jQuery);
