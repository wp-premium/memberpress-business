//Load the larger zxcvbn.js file asynchronously
(function(){var a;a=function(){var a,b;b=document.createElement("script");b.src=MeprZXCVBN.script_url;b.type="text/javascript";b.async=!0;a=document.getElementsByTagName("script")[0];return a.parentNode.insertBefore(b,a)};null!=window.attachEvent?window.attachEvent("onload",a):window.addEventListener("load",a,!1)}).call(this);

function mepr_score_it($el) {
  //console.log(zxcvbn($el.val()).score);
  var score = zxcvbn($el.val()).score;

  if($el.val().length == 0) {
    $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
    $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
    $el.parent().parent().find('.mp-pass-strength').val('0');
  } else {
    switch(score) {
      case 0:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.very_weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('0');
        break;
      case 1:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('1');
        break;
      case 2:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-mediumpass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.medium + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('2');
        break;
      case 3:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('3');
        break;
      case 4:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.very_strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('4');
        break;
      default:
        $el.parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
        $el.parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.parent().parent().find('.mp-pass-strength').val('0');
        break;
    }
  }
}

jQuery(document).ready(function() {
  //Signup forms
  function mepr_check() {
    if(typeof zxcvbn !== 'undefined') {
      if(jQuery('.mepr-password').length && jQuery('.mepr-password').val().length > 0) {
        mepr_score_it(jQuery('.mepr-password'));
      }
    } else {
      setTimeout(mepr_check, 100);
    }
  }
  mepr_check();
  jQuery('.mepr-password').keyup(function() {
    mepr_score_it(jQuery(this));
  });

  //Account page new password form
  jQuery('.mepr-new-password').keyup(function() {
    mepr_score_it(jQuery(this));
  });

  //Reset password page form (login page)
  jQuery('.mepr-forgot-password').keyup(function() {
    mepr_score_it(jQuery(this));
  });
});
