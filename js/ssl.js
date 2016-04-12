// Forces ssl payment form
(function($) {
  $(document).ready(function() {
    var set_mepr_ssl_form_action = function() {
      var ssl = mepr_ssl[$('#mepr_payment_method').val()];
      $('form.mepr-signup-form').attr('action', (ssl=='true'?mepr_ssl_url:mepr_http_url));
    }

    set_mepr_ssl_form_action();

    $('#mepr_payment_method').change( function() {
      set_mepr_ssl_form_action();
    });
  });
})(jQuery);
