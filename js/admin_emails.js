(function($) {
  $(document).ready(function() {
    //Email edit togglers
    $('a.mepr-edit-email-toggle').click(function(e) {
      var email_editor_id = $(this).data('id');
      var edit_text = $(this).data('edit-text');
      var cancel_text = $(this).data('cancel-text');
      var button = this;

      $('#' + email_editor_id).slideToggle('fast', 'linear', function() {
        if($('#' + email_editor_id).is(':hidden'))
          $(button).text(edit_text);
        else
          $(button).text(cancel_text);
      });

      return false;
    });

    $('a.mepr-insert-email-var').click( function(e) {
      e.preventDefault();

      var varid = $(this).data('variable-id');
      var edid  = $(this).data('textarea-id');

      var varstr = $('#'+varid).val();

      var editor = tinyMCE.get(edid);
      if(editor && editor instanceof tinyMCE.Editor && !editor.isHidden()) {
        editor.execCommand('mceInsertContent',false,varstr);
      }
      else {
        var body_selector = '#'+edid;

        var get_cursor_position = function () {
          var el = $(body_selector).get(0);
          var pos = 0;

          if('selectionStart' in el) {
            pos = el.selectionStart;
          } else if ('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
          }

          return pos;
        }

        var position = get_cursor_position();
        var content = $(body_selector).val();
        var newContent = content.substr(0, position) + varstr + content.substr(position);

        $(body_selector).val(newContent);
      }
    });

    function mepr_send_test_email(obj_name, dashed_obj_name, subject, body, use_template) {
      var data = {
        action: 'mepr_send_test_email',
        e: obj_name,
        s: subject,
        b: body,
        t: use_template
      };

      $('#mepr-loader-' + dashed_obj_name).show();

      $.post( ajaxurl, data,
        function(obj) {
          $('#mepr-loader-' + dashed_obj_name).hide();

          if('error' in obj)
            alert(obj.error);
          else
            alert(obj.message);
        },
        'json'
      );
    }

    $('a.mepr-send-test-email').click(function(e) {
      e.preventDefault();

      var obj_name = $(this).data('obj-name');
      var dashed_obj_name = $(this).data('obj-dashed-name');
      var subject_id = $(this).data('subject-id');
      var body_id = $(this).data('body-id');
      var use_template_id = $(this).data('use-template-id');

      var subject_selector = '#'+subject_id;
      var subject = $(subject_selector).val();

      var use_template_selector = '#'+use_template_id;
      var use_template = $(use_template_selector).is(':checked');

      var body = '';

      var get_body_from_textarea = function() {
        var body_selector = '#'+body_id;
        body_selector = body_selector.replace(/([\[\]])/g, '\\$1'); // Escape Brackets
        return $(body_selector).val();
      };

      if(typeof tinyMCE !== 'undefined') {
        var editor = tinyMCE.get(body_id);
        if(editor && editor instanceof tinyMCE.Editor && !editor.isHidden()) {
          body = editor.getContent({format : 'raw'});
        }
        else {
          body = get_body_from_textarea();
        }
      }
      else {
        body = get_body_from_textarea();
      }

      mepr_send_test_email(obj_name, dashed_obj_name, subject, body, use_template);
    });

    function mepr_set_email_defaults(obj_name,dashed_obj_name,subject_id,body_id,use_template_id) {
      var data = {
        action: 'mepr_set_email_defaults',
        e: obj_name
      };

      $('#mepr-loader-' + dashed_obj_name).show();

      $.post(ajaxurl, data, function(obj) {
        $('#mepr-loader-' + dashed_obj_name).hide();

        if( obj.error ) {
          alert(obj.error);
        }
        else {
          var subject_selector = '#'+subject_id;
          $(subject_selector).val(obj.subject);

          if(typeof tinyMCE != "undefined") {
            var editor = tinyMCE.get(body_id);
            if(editor && editor instanceof tinyMCE.Editor && !editor.isHidden()) {
              editor.setContent(obj.body);
              //editor.save({no_events:true});
            }

            var textarea_selector = 'textarea#'+body_id;
            textarea_selector = textarea_selector.replace(/([\[\]])/g, '\\$1'); // Escape Brackets
            $(textarea_selector).val(obj.body);
          }

          // Always defaults to true for now
          $('#'+use_template_id).prop('checked', true);
        }
      }, 'json');
    }

    $('a.mepr-reset-email').click(function(e) {
      e.preventDefault();

      var subject_id = $(this).data('subject-id');
      var body_obj = $(this).data('body-obj');
      var body_id = $(this).data('body-id');
      var dashed_obj_name = $(this).data('obj-dashed-name');

      mepr_set_email_defaults(body_obj,dashed_obj_name,subject_id,body_id);
    });
  });
})(jQuery);

