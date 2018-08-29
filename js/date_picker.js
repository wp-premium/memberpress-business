jQuery(document).ready(function($) {
  //I guess these can be tweaked as time goes, but for now these seem like reasonable targets
  var currentYear = new Date().getFullYear();
  var pastYears = currentYear - 100;
  var futureYears = currentYear + 50;

  var timeFormat = 'HH:mm:ss';
  var showTime   = true;

  //Front End needs to display cleaner
  if(typeof MeprDatePicker != "undefined") {
    timeFormat = MeprDatePicker.timeFormat;
    showTime = Boolean(MeprDatePicker.showTime);
  }

  $('.mepr-date-picker').datetimepicker( {
    dateFormat : 'yy-mm-dd',
    timeFormat: timeFormat,
    yearRange : pastYears + ":" + futureYears,
    changeMonth : true,
    changeYear : true,
    showTime : showTime,
    onSelect : function (date, inst) {
      $(this).trigger('mepr-date-picker-selected', [date, inst]);
    },
    onChangeMonthYear : function (month, year, inst) {
      $(this).trigger('mepr-date-picker-changed', [month, year, inst]);
    },
    onClose : function (date, inst) {
      $(this).val(date.trim()); //Trim off white-space if any
      $(this).trigger('mepr-date-picker-closed', [date, inst]);
    }
  });
});
