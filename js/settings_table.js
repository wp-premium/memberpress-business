jQuery(document).ready(function($) {
  $('table.mepr-settings-table td.mepr-settings-table-nav ul li').each( function() {
    var page_id = $(this).find('a').data('id');

    $(this).find('a').attr('id', 'mepr-nav-'+page_id);
    $(this).find('a').attr('href', '#'+page_id);
  });

  var meprSetPage = function (hash) {
    // IF ON INITIAL PAGE
    var page = 'table.mepr-settings-table td.mepr-settings-table-pages .mepr-page:first-child';
    var nav = 'table.mepr-settings-table td.mepr-settings-table-nav ul li:first-child a';

    if(!hash) { hash = window.location.hash; }

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
});

