(function($) {
  $(document).ready(function() {
    $('#mepr-edge-updates').click( function(e) {
      e.preventDefault();
      var wpnonce = $(this).attr('data-nonce');

      $('#mepr-edge-updates-wrap .mepr_loader').show();
      $(this).prop('disabled',true);

      var data = {
        action: 'mepr_edge_updates',
        edge: $(this).is(':checked'),
        wpnonce: wpnonce
      };

      var bigthis = this;

      $.post(ajaxurl, data, function(obj) {
        $('#mepr-edge-updates-wrap .mepr_loader').hide();
        $(bigthis).prop('disabled',false);

        if('error' in obj)
          alert(obj.error);
        else {
          $(bigthis).prop('checked',(obj.state=='true'));
        }
      }, 'json');
    });
  });
})(jQuery);
