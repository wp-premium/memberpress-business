jQuery(document).ready(function($) {
  //if($('#cspf-table-search').val() == '') {
  //  $('#cspf-table-search').val($('#cspf-table-search').attr('data-value'));
  //  $('#cspf-table-search').css('color','#767676');
  //}

  //$('#cspf-table-search').focus( function() {
  //  if($('#cspf-table-search').val() == $('#cspf-table-search').attr('data-value')) {
  //    $('#cspf-table-search').val('');
  //    $('#cspf-table-search').css('color','#000000');
  //  }
  //});

  //$('#cspf-table-search').blur( function() {
  //  if($('#cspf-table-search').val() == '') {
  //    $('#cspf-table-search').val($('#cspf-table-search').attr('data-value'));
  //    $('#cspf-table-search').css('color','#767676');
  //  }
  //});

  //$("#cspf-table-search").keyup(function(e) {
  //  // Apparently 13 is the enter key
  //  if(e.which == 13) {
  //    e.preventDefault();
  //    var loc = window.location.href;
  //    loc = loc.replace(/&search=[^&]*/gi, '');

  //    if($(this).val() != '')
  //      window.location = loc + '&search=' + escape($.trim($(this).val()));
  //    else
  //      window.location = loc;
  //  }
  //});

  $("#cspf-table-search-submit").on('click', function(e) {
    e.preventDefault();

    var loc = window.location.href;

    loc = loc.replace(/[&\?]search=[^&]*/gi, '');
    loc = loc.replace(/[&\?]search-field=[^&]*/gi, '');

    var search = escape($('#cspf-table-search').val());
    var search_field = $('#cspf-table-search-field').val();

    loc = loc + '&search=' + encodeURIComponent(search) + '&search-field=' + search_field;

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  });

  $(".current-page").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      var loc = window.location.href;
      loc = loc.replace(/&paged=[^&]*/gi, '');

      if($(this).val() != '')
        window.location = loc + '&paged=' + escape($(this).val());
      else
        window.location = loc;
    }
  });

  $("#cspf-table-perpage").change(function(e) {
    var loc = window.location.href;
    loc = loc.replace(/&perpage=[^&]*/gi, '');

    if($(this).val() != '')
      window.location = loc + '&perpage=' + $(this).val();
    else
      window.location = loc;
  });

  $("#mepr_search_filter").click( function() {
    var loc = window.location.href;

    $('.mepr_filter_field').each( function() {
      var arg = $(this).attr('id');
      console.log(arg);
      var re = new RegExp("[&\?]" + arg + "=[^&]*","gi");
      console.log(re);
      loc = loc.replace(re, '');
      loc = loc + '&' + arg + "=" + $(this).val();
    } );

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  } );

});
