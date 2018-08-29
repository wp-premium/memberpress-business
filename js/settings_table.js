var meprSetPage = function (hash) {
  var $ = jQuery;

  // IF ON INITIAL PAGE
  var page = 'table.mepr-settings-table td.mepr-settings-table-pages .mepr-page:first-child';
  var nav = 'table.mepr-settings-table td.mepr-settings-table-nav ul li:first-child a';

  if(!hash) { hash = window.location.hash; }

  //Fix for non-mepr hashes on other parts of the site
  if(String(hash).indexOf('mepr') === -1) {
    hash = '';
  }

  var url = window.location.href.replace(/#.*$/,'');

  // Open correct page based on the hash
  var trypage = 'table.mepr-settings-table td.mepr-settings-table-pages .mepr-page' + hash;

  if ((hash != '') && ($(trypage).length > 0)) {
    page = trypage;
    nav = 'table.mepr-settings-table td.mepr-settings-table-nav ul li a#mepr-nav-' + hash.replace(/\#/,'');

    var href = url + hash;
    $( 'table.mepr-settings-table' ).trigger( 'mepr-settings-url', [ href, hash, url ] );

    // Don't do this for now ... it will make the page bump around when using anchors
    //window.location.href = href;
  }

  $('table.mepr-settings-table td.mepr-settings-table-nav ul li a').removeClass('mepr-active');
  $(nav).addClass('mepr-active');

  $('.mepr-page').hide();
  $(page).show();

  // Auto hide the menu in mobile mode when the button is clicked
  if($(window).width() <= 782) {
    $('td.mepr-settings-table-nav').hide();
  }
};

var mepr_show_box = function(box,animate) {
  var $ = jQuery;

  $(box).trigger('mepr_show_box');
  animate ? $(box).slideDown() : $(box).show();
};

var mepr_hide_box = function(box,animate) {
  var $ = jQuery;

  $(box).trigger('mepr_hide_box');
  animate ? $(box).slideUp() : $(box).hide();
};

// Toggle Box from Checkbox
var mepr_toggle_checkbox_box = function(checkbox, box, animate, reverse) {
  var $ = jQuery;

  if ($(checkbox).is(':checked')) {
    reverse ? mepr_hide_box(box,animate) : mepr_show_box(box,animate);
  }
  else {
    reverse ? mepr_show_box(box,animate) : mepr_hide_box(box,animate);
  }
};

// Toggle Box from Link
var mepr_toggle_link_box = function(link, box, animate) {
  var $ = jQuery;

  if ($(box).is(':visible')) {
    mepr_hide_box(box,animate);
  }
  else {
    mepr_show_box(box,animate);
  }
};

// Toggle Box from Link
var mepr_toggle_select_box = function(select, boxes, animate) {
  var $ = jQuery;

  var box = '';

  $.each(boxes, function(k,v) {
    box = '.'+v;
    mepr_hide_box(box,animate);
  });

  if (typeof boxes[$(select).val()] !== undefined) {
    box = '.'+boxes[$(select).val()];
    mepr_show_box(box,animate);
  }
};

// Setup all option toggle boxes
var mepr_toggle_boxes = function() {
  var $ = jQuery;

  $('.mepr-toggle-checkbox').each(function() {
    var box = '.'+$(this).data('box');
    var reverse  = (typeof $(this).data('reverse') !== 'undefined');

    mepr_toggle_checkbox_box(this, box, false, reverse);

    $(this).on('click', function() {
      mepr_toggle_checkbox_box(this, box, true, reverse);
    });
  });

  $('.mepr-toggle-link').each(function() {
    var box = '.'+$(this).data('box');
    var reverse = (typeof $(this).data('reverse') !== 'undefined');

    reverse ? mepr_show_box(box, false) : mepr_hide_box(box, false);

    $(this).on('click', function(e) {
      e.preventDefault();
      mepr_toggle_link_box(this, box, true);
    });
  });

  $('.mepr-toggle-select').each(function() {
    var boxes = {};
    var select = this;

    $(this).find('option').each(function() {
      var boxname = $(this).val()+'-box';
      if (typeof $(select).data(boxname) !== 'undefined') {
        boxes[$(this).val()] = $(select).data(boxname);
      }
    });

    mepr_toggle_select_box(this, boxes, false);

    $(this).on('change', function(e) {
      mepr_toggle_select_box(this, boxes, true);
    });
  });
};

jQuery(document).ready(function($) {
  $('table.mepr-settings-table td.mepr-settings-table-nav ul li').each( function() {
    var page_id = $(this).find('a').data('id');

    $(this).find('a').attr('id', 'mepr-nav-'+page_id);
    $(this).find('a').attr('href', '#'+page_id);
  });

  meprSetPage();

  $('table.mepr-settings-table').on( 'click', 'td.mepr-settings-table-nav ul li a', function (e) {
    e.preventDefault();
    meprSetPage($(this).attr('href'));
  });

  $('tr.mepr-mobile-nav a.mepr-toggle-nav').on('click', function(e) {
    e.preventDefault();
    $('td.mepr-settings-table-nav').toggle();
  });

  // This is in place so the settings table doesn't get screwed up when resizing
  // up to desktop mode from mobile ... not that that would ever happen of course
  $(window).on('resize', function(e) {
    if($(this).width() > 782) {
      $('td.mepr-settings-table-nav').css('display','');
    }
  });

  mepr_toggle_boxes();

  // Adjust the action url so we can stay on the same settings page on update
  $('table.mepr-settings-table').on('mepr-settings-url', function( e, href, hash, url ) {
    $('form#mepr-options').attr('action',href);
  });
});
