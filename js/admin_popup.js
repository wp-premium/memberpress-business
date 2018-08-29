jQuery(document).ready(function($) {
  $('.mepr-popup.mepr-auto-open').each( function() {
    var _this = this;

    $.magnificPopup.open({
      items: {
        src: _this,
        type: 'inline'
      },
      closeOnBgClick: false,
      closeBtnInside: false
    });
  });

  var mepr_stop_popup = function(popup, cb) {
    var args = {
      action: 'mepr_stop_popup',
      security: MeprPopup.security,
      popup: popup,
    };

    $.post(ajaxurl, args, cb, 'json')
      .fail(function(response) {
        alert(MeprPopup.error);
        $.magnificPopup.close();
      });
  };

  $('.mepr-stop-popup').on('click', function(e) {
    e.preventDefault();

    var _this = this;
    var popup = $(this).data('popup');
    mepr_stop_popup(popup, function(response) {
      $(_this).trigger('mepr-popup-stopped',[popup]);
      if(typeof response.error === 'undefined') {
        $.magnificPopup.close();
      }
      else {
        alert(response.error);
        $.magnificPopup.close();
      }
    });
  });

  var mepr_delay_popup = function(popup, cb) {
    var args = {
      action: 'mepr_delay_popup',
      security: MeprPopup.security,
      popup: popup
    };

    $.post(ajaxurl, args, cb, 'json')
      .fail(function(response) {
        alert(MeprPopup.error);
        $.magnificPopup.close();
      });
  };

  $('.mepr-delay-popup').on('click', function(e) {
    e.preventDefault();

    var _this = this;
    var popup = $(this).data('popup');

    mepr_delay_popup(popup, function(response) {
      $(_this).trigger('mepr-popup-delayed',[popup]);
      $.magnificPopup.close();
      if(typeof $(_this).data('href') !== 'undefined') {
        window.location.href = $(_this).data('href');
      }
    });
  });
});

