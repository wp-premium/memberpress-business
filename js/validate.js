/** Some basic methods to validate form elements */
var mpValidateEmail = function (email) {
  //In case the email is not entered yet and is not required
  if(!email || 0 === email.length) {
    return true;
  }

  // https://stackoverflow.com/a/46181/6114835
  // var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  var re = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i; //Accepts unicode???
  return re.test(email);

  //OLD, but doesn't work super well
  // var filter = /^[A-Z0-9._'%+-]+@[A-Z0-9.-]+\.[A-Z]{2,25}$/i;
  // return filter.test(email);
};

var mpValidateNotBlank = function(val) {
  return (val && val.length > 0);
};

var mpToggleFieldValidation = function(field, valid) {
  field.toggleClass('invalid', !valid);
  field.toggleClass('valid', valid);
  field.prev('.mp-form-label').find('.cc-error').toggle(!valid);

  var form = field.closest('.mepr-form');

  if (0 < form.find('.invalid').length) {
    form.find('.mepr-form-has-errors').show();
  } else {
    form.find('.mepr-form-has-errors').hide();
  }
};

