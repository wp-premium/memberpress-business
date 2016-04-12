jQuery(document).ready(function() {
  if(jQuery('#cspf-table-search').val() == '') {
    jQuery('#cspf-table-search').val(jQuery('#cspf-table-search').attr('data-value'));
    jQuery('#cspf-table-search').css('color','#767676');
  }

  jQuery('#cspf-table-search').focus( function() {
    if(jQuery('#cspf-table-search').val() == jQuery('#cspf-table-search').attr('data-value')) {
      jQuery('#cspf-table-search').val('');
      jQuery('#cspf-table-search').css('color','#000000');
    }
  });

  jQuery('#cspf-table-search').blur( function() {
    if(jQuery('#cspf-table-search').val() == '') {
      jQuery('#cspf-table-search').val(jQuery('#cspf-table-search').attr('data-value'));
      jQuery('#cspf-table-search').css('color','#767676');
    }
  });

  jQuery("#cspf-table-search").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      var loc = window.location.href;
      loc = loc.replace(/&search=[^&]*/gi, '');

      if(jQuery(this).val() != '')
        window.location = loc + '&search=' + escape(jQuery.trim(jQuery(this).val()));
      else
        window.location = loc;
    }
  });

  jQuery(".current-page").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      var loc = window.location.href;
      loc = loc.replace(/&paged=[^&]*/gi, '');

      if(jQuery(this).val() != '')
        window.location = loc + '&paged=' + escape(jQuery(this).val());
      else
        window.location = loc;
    }
  });

  jQuery("#cspf-table-perpage").change(function(e) {
    var loc = window.location.href;
    loc = loc.replace(/&perpage=[^&]*/gi, '');

    if(jQuery(this).val() != '')
      window.location = loc + '&perpage=' + jQuery(this).val();
    else
      window.location = loc;
  });

  jQuery('#mepr_fake_submit').click(function() {
    var loc = window.location.href;
    loc = loc.replace(/&prd_id=[^&]*/gi, '');
    loc = loc.replace(/&status=[^&]*/gi, '');

    if(jQuery('#mepr_status').val()) {
      window.location = loc + '&prd_id=' + jQuery('#mepr_prd_id').val() + '&status=' + jQuery('#mepr_status').val();
    } else {
      window.location = loc + '&prd_id=' + jQuery('#mepr_prd_id').val();
    }
  });
});
