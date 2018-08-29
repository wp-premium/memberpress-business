/* Admin Reminders */
jQuery(document).ready(function($) {
  $('#mepr_trigger_length').spinner({ min: 0 });

  $('#config-mepr-admin-' + $('#mepr_trigger_event').val() + '-reminder-email').show();
  $('#config-mepr-user-' + $('#mepr_trigger_event').val() + '-reminder-email').show();

  $('select#trigger').on( 'change', function(e) {
    var rval = $(this).val();
    rval = rval.split('_');

    var trigger_timing = rval[0];
    var trigger_event = rval[1];

    $('#mepr_trigger_timing').val( trigger_timing );
    $('#mepr_trigger_event').val( trigger_event );

    $('.mepr-config-email-row').hide();
    $('#config-mepr-admin-' + trigger_event + '-reminder-email').show();
    $('#config-mepr-user-' + trigger_event + '-reminder-email').show();

    $('#mepr-reminder-emails').css("background-color","#fffbcc");
    $('#mepr-reminder-emails').animate({ "background-color": "none" }, 2000);
  });

  if($('#_mepr_reminder_filter_products_str').is(":checked")) {
    $('#mepr-reminder-products-hidden').show();
  } else {
    $('#mepr-reminder-products-hidden').hide();
  }
  $('#_mepr_reminder_filter_products_str').click(function() {
    $('#mepr-reminder-products-hidden').slideToggle('fast');
  });
});
