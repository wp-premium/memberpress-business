(function($) {
  $(document).ready(function() {

    //I guess these can be tweaked as time goes, but for now these seem like reasonable targets
    var currentYear = new Date().getFullYear();
    var pastYears = currentYear - 100;
    var futureYears = currentYear + 50;

    $('.mepr-date-picker').datepicker( {
      dateFormat : 'yy-mm-dd',
      yearRange : pastYears + ":" + futureYears,
      changeMonth : true,
      changeYear : true,
      onSelect : function (date, inst) {
        $(this).trigger('mepr-date-picker-selected', [date, inst]);
      },
      onChangeMonthYear : function (month, year, inst) {
        $(this).trigger('mepr-date-picker-changed', [month, year, inst]);
      },
      onClose : function (date, inst) {
        $(this).trigger('mepr-date-picker-closed', [date, inst]);
      }
    });

  });
})(jQuery);
