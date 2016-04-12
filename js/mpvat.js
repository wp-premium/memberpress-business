jQuery(document).ready(function($) {
  var mepr_show_vat_number = function(form) {
    if ($.inArray(form.find('#mepr-address-country').val(),MpVat.countries) >= 0) {
      form.find('#mepr_vat_customer_type_row').slideDown();
      if (form.find('#mepr_vat_customer_type-consumer').is(':checked')) {
        form.find('#mepr_vat_number_row').slideUp();
      }
      else if (form.find('#mepr_vat_customer_type-business').is(':checked')) {
        form.find('#mepr_vat_number_row').slideDown();
      }
    }
    else {
      form.find('#mepr_vat_customer_type_row').slideUp();
      form.find('#mepr_vat_number_row').slideUp();
    }
  };

  $.each($('.mepr-countries-dropdown'), function(i,obj) {
    var form = $(obj).closest('.mepr-form');
    mepr_show_vat_number(form);
  });

  $('#mepr_vat_customer_type-consumer, #mepr_vat_customer_type-business').on('click', function(e) {
    var form = $(this).closest('.mepr-form');
    mepr_show_vat_number(form);
  });

  $('.mepr-countries-dropdown').on('change mepr-geolocated', function(e) {
    var form = $(this).closest('.mepr-form');
    mepr_show_vat_number(form);
  });

  $('.mepr-form #mepr_vat_number.mepr-form-input').on('mepr-validate-input', function (e) {
    var form = $(this).closest('.mepr-form');

    var country = form.find('#mepr-address-country').val();
    var is_business = form.find('#mepr_vat_customer_type-business').is(':checked');
    var vat_number = form.find('#mepr_vat_number').val();

    vat_number = vat_number.replace(/[-.●]/g,'');

    var invalid = ($.inArray(country,MpVat.countries) >= 0 &&
                   is_business && vat_number.length > 0 &&
                   !vat_number.match(new RegExp('^'+MpVat.rates[country].fmt+'$'), 'i'));

    mpToggleFieldValidation($(this), !invalid);
  });
});

