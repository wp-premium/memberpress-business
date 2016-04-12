//Load the larger zxcvbn.js file asynchronously
(function(){var a;a=function(){var a,b;b=document.createElement("script");b.src=MeprZXCVBN.script_url;b.type="text/javascript";b.async=!0;a=document.getElementsByTagName("script")[0];return a.parentNode.insertBefore(b,a)};null!=window.attachEvent?window.attachEvent("onload",a):window.addEventListener("load",a,!1)}).call(this);

jQuery(document).ready(function() {
  jQuery('.mepr-password').keyup(function() {
    //console.log(zxcvbn(jQuery(this).val()).score);
    var score = zxcvbn(jQuery(this).val()).score;

    if(jQuery(this).val().length == 0) {
      jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
      jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
      jQuery(this).parent().parent().find('.mp-pass-strength').val('0');
    } else {
      switch(score) {
        case 0:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.very_weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('0');
          break;
        case 1:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('1');
          break;
        case 2:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-mediumpass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.medium + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('2');
          break;
        case 3:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('3');
          break;
        case 4:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.very_strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('4');
          break;
        default:
          jQuery(this).parent().parent().find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
          jQuery(this).parent().parent().find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
          jQuery(this).parent().parent().find('.mp-pass-strength').val('0');
          break;
      }
    }
  });
});
