jQuery(document).ready(function($) {
  // Toggle Box from Checkbox
  var mepr_toggle_box = function( checkbox, box, animate, reverse ) {
    var show_box = function(box,animate) {
      animate ? $(box).slideDown() : $(box).show();
    };

    var hide_box = function(box,animate) {
      animate ? $(box).slideUp() : $(box).hide();
    };

    if ($(checkbox).is(':checked')) {
      reverse ? hide_box(box,animate) : show_box(box,animate);
    }
    else {
      reverse ? show_box(box,animate) : hide_box(box,animate);
    }
  };

  // Setup all option toggle boxes
  var mepr_toggle_boxes = function() {
    var checkbox = '.mepr-toggle-checkbox';
    var box = reverse = null;

    $(checkbox).each(function() {
      box = '.'+$(this).data('box');
      reverse  = (typeof $(this).data('reverse') !== 'undefined');

      mepr_toggle_box( this, box, false, reverse );

      $(this).on( 'click', function() {
        box = '.'+$(this).data('box');
        reverse  = (typeof $(this).data('reverse') !== 'undefined');

        mepr_toggle_box( this, box, true, reverse );
      });
    });
  };

  mepr_toggle_boxes();

  // Login form shortcode
  if($('#_mepr_manual_login_form').is(":checked")) {
    $('div#mepr-shortcode-login-page-area').show();
  } else {
    $('div#mepr-shortcode-login-page-area').hide();
  }

  $('#_mepr_manual_login_form').click(function() {
    $('div#mepr-shortcode-login-page-area').slideToggle();
  });

  // Unauthorized stuff
  var unauth_tgl_ids = {
    excerpt: {
      src: '_mepr_unauth_excerpt_type',
      target: '_mepr_unauth_excerpt_type-size'
    },
    message: {
      src: '_mepr_unauthorized_message_type',
      target: '_mepr_unauthorized_message_type-editor'
    }
  };

  var unauth_tgl = function(src,target) {
    if($('#'+src).val()=='custom')
      $('#'+target).slideDown();
    else
      $('#'+target).slideUp();
  };

  unauth_tgl(unauth_tgl_ids.excerpt.src,unauth_tgl_ids.excerpt.target);
  $('#'+unauth_tgl_ids.excerpt.src).change( function() {
    unauth_tgl(unauth_tgl_ids.excerpt.src,unauth_tgl_ids.excerpt.target);
  });

  unauth_tgl(unauth_tgl_ids.message.src,unauth_tgl_ids.message.target);
  $('#'+unauth_tgl_ids.message.src).change( function() {
    unauth_tgl(unauth_tgl_ids.message.src,unauth_tgl_ids.message.target);
  });

  $('table.wp-list-table tr').hover(
    function(e) {
      $(this).find('.mepr-row-actions').css('visibility','visible');
    },
    function(e) {
      $(this).find('.mepr-row-actions').css('visibility','hidden');
    }
  );

  $( '.mepr-auto-trim' ).blur( function(e) {
    var value = $(this).val();
    $(this).val( value.trim() );
  });

  $('.mepr-slide-toggle').click( function(e) {
    e.preventDefault();
    $($(this).attr('data-target')).slideToggle();
  });

  //Change mouse pointer over li items
  $('body').on('mouseenter', '.mepr-sortable li', function() {
    $(this).addClass('mepr-hover');
  });
  $('body').on('mouseleave', '.mepr-sortable li', function() {
    $(this).removeClass('mepr-hover');
  });

  $('.mepr-admin-notice.mepr-auto-open').each( function() {
    var _this = this;

    $.magnificPopup.open({
      items: {
        src: _this,
        type: 'inline'
      }
    });
  });

  var mepr_stop_addon_notices = function(addon, cb) {
    var args = {
      action: 'mepr_addon_stop_notice',
      addon: addon,
    };

    $.post(ajaxurl, args, cb, 'json');
  };

  $('.mepr-addon-stop-notices').on('click', function() {
    var _this = this;
    mepr_stop_addon_notices($(this).parent().data('addon'), function(response) {
      if(typeof response.error === 'undefined') {
        $.magnificPopup.close();
      }
      else {
        alert(response.error);
        $.magnificPopup.close();
      }
    });
  });

  $('.mepr-addon-activate, .mepr-addon-install').on('click', function() {
    var _this = this;
    mepr_stop_addon_notices($(this).parent().data('addon'), function(response) {
      if(typeof response.error === 'undefined') {
        location.href = $(_this).data('href');
      }
      else {
        alert(response.error);
        $.magnificPopup.close();
      }
    });
  });
});

