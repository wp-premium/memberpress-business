jQuery(document).ready(function($) {
  $('body').on('mepr-checkout-submit', function(e, payment_form) {
    payment_form.get(0).submit();
  });
});

