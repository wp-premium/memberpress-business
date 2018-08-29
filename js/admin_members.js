jQuery(document).ready(function($) {

  $('.mepr-password-toggle button').on('click', function(e) {
    $(this).hide();
    $('.mepr-password-input-wrapper').show();
  });

  $('.mepr-password-input-buttons .mepr-hide-button').on('click', function(e) {
    var input_type = $('#member_user_pass').attr('type');

    var button_hide_text = $(this).find('.text').data('hide');
    var button_show_text = $(this).find('.text').data('show');

    if(input_type==='text') {
      $('#member_user_pass').attr('type','password');
      $(this).find('.dashicons').addClass('dashicons-visibility');
      $(this).find('.dashicons').removeClass('dashicons-hidden');
      $(this).find('.text').text(button_show_text);
    }
    else if(input_type==='password') {
      $('#member_user_pass').attr('type','text');
      $(this).find('.dashicons').removeClass('dashicons-visibility');
      $(this).find('.dashicons').addClass('dashicons-hidden');
      $(this).find('.text').text(button_hide_text);
    }
  });

  $('.mepr-password-input-buttons .mepr-cancel-button').on('click', function(e) {
    $('.mepr-password-toggle button').show();
    $('.mepr-password-input-wrapper').hide();
  });

  $('.wp-list-table tr').on('mouseover', function(e) {
    $(this).find('.mepr-member-actions').show();
  });

  $('.wp-list-table tr').on('mouseout', function(e) {
    $(this).find('.mepr-member-actions').hide();
  });

  //$('.mepr-advanced-link a').on('click',function(e) {
  //  if($(this).find('.dashicons').hasClass('dashicons-arrow-right')) {
  //    $(this).find('.dashicons').removeClass('dashicons-arrow-right');
  //    $(this).find('.dashicons').addClass('dashicons-arrow-down');
  //  }
  //  else {
  //    $(this).find('.dashicons').addClass('dashicons-arrow-right');
  //    $(this).find('.dashicons').removeClass('dashicons-arrow-down');
  //  }
  //});

});

