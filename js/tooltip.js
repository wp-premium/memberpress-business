(function($) {
  $(document).ready(function() {
    $('body').on('click', '.mepr-tooltip', function() {
      var tooltip_title = $(this).find('.mepr-data-title').html();
      var tooltip_info = $(this).find('.mepr-data-info').html();
      $(this).pointer({ 'content':  '<h3>' + tooltip_title + '</h3><p>' + tooltip_info + '</p>',
                        'position': {'edge':'left','align':'center'},
                        //'buttons': function() {
                        //  // intentionally left blank to eliminate 'dismiss' button
                        //}
                      })
      .pointer('open');
    });

    //$('body').on('mouseout', '.mepr-tooltip', function() {
    //  $(this).pointer('close');
    //});

    if( MeprTooltip.show_about_notice ) {
      var mepr_about_pointer_id = 'mepr-about-info';

      var mepr_setup_about_pointer = function() {
        $('#'+mepr_about_pointer_id).pointer({
          content: MeprTooltip.about_notice,
          position: {'edge':'bottom','align':'left'},
          close: function() {
            var args = { action: 'mepr_close_about_notice' };
            $.post( ajaxurl, args );
          }
        }).pointer('open');
      };

      $('.toplevel_page_memberpress .wp-menu-name').attr( 'id', mepr_about_pointer_id );
      mepr_setup_about_pointer();
    }
  });
})(jQuery);
