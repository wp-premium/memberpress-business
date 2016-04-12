(function($) {
  $(document).ready(function() {
    //Date expiration
    if($('.should-expire').is(":checked")) {
      $('.mepr-coupon-expires').show();
    } else {
      $('.mepr-coupon-expires').hide();
    }
    $('.should-expire').click(function() {
      $('.mepr-coupon-expires').slideToggle('fast');
    });

    //Validate before allowing post to be saved
    $('#publish').click(function() {
      if(!$('select.mepr-coupon-products-select').val()) {
        alert(MeprCoupon.mepr_no_products_message); //Alerts the user that they must create memberships before they can save coupons
        return false;
      }
    });

    //trial period
    if($('#_mepr_coupons_trial').is(":checked")) {
      $('.mepr-coupons-trial-hidden').show();
    } else {
      $('.mepr-coupons-trial-hidden').hide();
    }
    $('#_mepr_coupons_trial').click(function() {
      $('.mepr-coupons-trial-hidden').slideToggle('fast');
    });
  });
})(jQuery);
