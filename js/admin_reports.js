var main_view = jQuery('div#mepr-reports-main-view').attr('data-value');
var currency_symbol = jQuery('div#mepr-reports-currency-symbol').attr('data-value');

var drawReportingCharts = function () {
  var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
  var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
  var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
  var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

  //Monthly Amounts Area Chart
  var args = {
    action: 'mepr_month_report',
    type: 'amounts',
    month: month,
    year: year,
    product: product
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.AreaChart(document.getElementById('monthly-amounts-area-graph'));

      var chartSettings = {
         height:'350',
         width: main_width,
         title:jQuery('div#mepr-reports-monthly-areas-title').attr('data-value'),
         hAxis:{
            title:jQuery('div#mepr-reports-monthly-htitle').attr('data-value')
         },
         vAxis:{
            format:currency_symbol
         }
      };

      //NOT WORKING
      // var monthlyAmountsFormatter = new google.visualization.NumberFormat({fractionDigits: 2});
      // monthlyAmountsFormatter.format(MonthlyAmountsChartData, 2);

      chart.draw(chartData, chartSettings);
  });

  //Yearly Amounts Area Chart
  args = {
    action: 'mepr_year_report',
    type: 'amounts',
    year: year,
    product: product
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.AreaChart(document.getElementById('yearly-amounts-area-graph'));

      var chartSettings = {
        height: '350',
        width: main_width,
        title: jQuery('div#mepr-reports-yearly-areas-title').attr('data-value'),
        hAxis: {
          title: jQuery('div#mepr-reports-yearly-htitle').attr('data-value')
        },
        vAxis:{
          format: currency_symbol
        }
      };

      chart.draw(chartData, chartSettings);
  });

  //Monthly Transactions Area Chart
  args = {
    action: 'mepr_month_report',
    type: 'transactions',
    month: month,
    year: year,
    product: product
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.AreaChart(document.getElementById('monthly-transactions-area-graph'));

      var chartSettings = {
        height: '350',
        width: main_width,
        title:jQuery('div#mepr-reports-monthly-transactions-title').attr('data-value'),
        hAxis: {
          title:jQuery('div#mepr-reports-monthly-htitle').attr('data-value')
        }
      };

      chart.draw(chartData, chartSettings);
    });

  //Yearly Transactions Area Chart
  args = {
    action: 'mepr_year_report',
    type: 'transactions',
    year: year,
    product: product
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.AreaChart(document.getElementById('yearly-transactions-area-graph'));

      var chartSettings = {
        height:'350',
        width: main_width,
        title:jQuery('div#mepr-reports-yearly-transactions-title').attr('data-value'),
        hAxis:{
          title:jQuery('div#mepr-reports-yearly-htitle').attr('data-value')
        }
      };

      chart.draw(chartData, chartSettings);
    });

  //Monthly Pie Chart Totals
  args = {
    action: 'mepr_pie_report',
    type: 'monthly',
    month: month,
    year: year
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.PieChart(document.getElementById('monthly-pie-chart-area'));

      var chartSettings = {
        height:185,
        width:330,
        title:jQuery('div#mepr-reports-pie-title').attr('data-value')
      };

      chart.draw(chartData, chartSettings);
    });

  //Yearly Pie Chart Totals
  args = {
    action: 'mepr_pie_report',
    type: 'yearly',
    year: year
  };

  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.PieChart(document.getElementById('yearly-pie-chart-area'));

      var chartSettings = {
        height:185,
        width:330,
        title:jQuery('div#mepr-reports-pie-title').attr('data-value')
      };

      chart.draw(chartData, chartSettings);
    });

  //All-Time Pie Chart Totals
  args = {
    action: 'mepr_pie_report',
    type: 'all-time'
  };

  jQuery
    .getJSON( ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.PieChart(document.getElementById('all-time-pie-chart-area'));

      var chartSettings = {
        height: 185,
        width: 330,
        title: jQuery('div#mepr-reports-pie-title').attr('data-value')
      };

      chart.draw(chartData, chartSettings);
    });
}

google.load("visualization", "1", {packages:["corechart"]});
google.setOnLoadCallback(drawReportingCharts);

(function($) {
  $(document).ready(function() {
    //SHOW CHOSEN AREA
    $('.main-nav-tab').removeClass('nav-tab-active');
    $('a#'+main_view).addClass('nav-tab-active');
    $('div#'+main_view+'-reports-area').show();
    $('div#monthly-amounts-area-graph').show();
    $('div#yearly-amounts-area-graph').show();

    //MAIN NAV TABS CONTROL
    $('a.main-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      var chosen = $(this).attr('id');

      $('a.main-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.mepr_reports_area').hide();
      $('div.' + chosen).show();

      return false;
    });

    //MONTHLY NAV TABS CONTROL
    $('a.monthly-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      var chosen = $(this).attr('id');

      $('a.monthly-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.monthly_graph_area').hide();
      $('div.' + chosen).show();

      return false;
    });

    //YEARLY NAV TABS CONTROL
    $('a.yearly-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      var chosen = $(this).attr('id');

      $('a.yearly-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.yearly_graph_area').hide();
      $('div.' + chosen).show();

      return false;
    });

  });
})(jQuery);
