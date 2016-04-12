//AUTO GEN TITLE VALUE
//OTHER PART OF THIS IS IN THE document.ready() function below
var rule_title_curr_val = jQuery('#title').val();

function mepr_show_content_dropdown(field_name, type) {
  var data = {
    action: 'mepr_show_content_dropdown',
    field_name: field_name,
    type: type
  };

  jQuery('#_mepr_rules_content-wrap').hide();
  jQuery('#mepr-rule-loading-icon').show();

  jQuery.post(ajaxurl, data, function(response) {
    jQuery('#'+field_name+"-wrap").replaceWith(response);

    //get and set page title
    jQuery('#_mepr_auto_gen_title').val('true'); //set the auto gen back to true since we just changed the type
    rule_title_curr_val = mepr_update_rule_post_title( jQuery( '#_mepr_rules_type' ).val(), '');

    mepr_autocomplete_setup( type );
    jQuery('#_mepr_rules_content-wrap').show();
    jQuery('#mepr-rule-loading-icon').hide();
  });
}

function mepr_autocomplete_setup( type ) {
  // If there's no autocomplete thing setup then just blow outta here
  if( jQuery('.mepr-rule-types-autocomplete').length == 0 ) { return; }

  jQuery('.mepr-rule-types-autocomplete').keydown( function(e) {
    var key = e.keyCode || e.charCode;

    if( key == 8 || key == 46 ) {
      jQuery( "#_mepr_rules_content" ).val( '' );
      jQuery( "#rule-content-info" ).html( '' );
    }

    if( jQuery( this ).val().length <= 1 )
      jQuery( this ).removeClass( 'mepr-red-border' );
    else
      jQuery( this ).addClass( 'mepr-red-border' );
  });

  jQuery('.mepr-rule-types-autocomplete').autocomplete({
    source: ajaxurl+'?action=mepr_rule_content_search&type='+type,
    minLength: 2,
    focus: function( event, ui ) {
      jQuery( "#rule-content-text" ).val( ui.item.label );
      jQuery( this ).removeClass( 'mepr-red-border' );
      return false;
    },
    select: function( event, ui ) {
      jQuery( "#rule-content-text" ).val( ui.item.label );
      jQuery( "#_mepr_rules_content" ).val( ui.item.id );
      jQuery( "#rule-content-info" ).html( ui.item.desc );
      jQuery( this ).removeClass( 'mepr-red-border' );
      rule_title_curr_val = mepr_update_rule_post_title( jQuery( '#_mepr_rules_type' ).val(), ui.item.id );
      return false;
    }
  })
  .data( "ui-autocomplete" )._renderItem = function( ul, item ) {
    var max_title_size = 30;
    var elipses = '';
    if( item.label.length > max_title_size ) { elipses = '...'; }
    return jQuery( "<li>" )
      .append( "<a><b>" + item.label.substr(0,max_title_size) + elipses + "</b><br/><small>ID: " + item.id + " | Slug: " + item.slug + "</small></a>" )
      .appendTo( ul );
  };
}

//May need to move this to WP Ajax too
//Actually we should just implement issue #222
//https://github.com/Caseproof/memberpress/issues/222
function mepr_update_rule_post_title( type, content ) {
  var post_title = MeprRule.types[type] + ': ' + content;

  if(jQuery('#_mepr_auto_gen_title').val() == 'true') {
    jQuery('#title').val(post_title);
    rule_title_curr_val = post_title;
    return post_title;
  }

  return rule_title_curr_val;
}

(function($) {
  $(document).ready(function() {
    //MORE AUTO GEN STUFF
    $('#title').blur(function() {
      //Auto generate the title goes back to true if empty
      if($('#title').val().trim() == '') {
        $('#_mepr_auto_gen_title').val('true');
        $('#title').focus();
        rule_title_curr_val = mepr_update_rule_post_title($('#_mepr_rules_type').val(), $('#_mepr_rules_content').val());
        return;
      }

      if($('#title').val() != rule_title_curr_val) {
        $('#_mepr_auto_gen_title').val('false');
        return;
      }
    });

    //We want a green icon for readability
    $('div#visibility').hide(); //hide visibility option
    $('div#minor-publishing-actions').hide(); //make publish box a bit cleaner looking
    $('input#publish').val($('div#save-rule-helper').attr('data-value'));
    $('div#message p').html($('div#rule-message-helper').attr('data-value'));

    mepr_autocomplete_setup( $('#_mepr_rules_type').val() );

    //Hide Partial Content Codes
    $('div#partial_content_codes_hidden').hide();

    $('a#partial_content_codes').click(function() {
      $('div#partial_content_codes_hidden').slideToggle('fast');
      return false;
    });

    //Toggler for drips
    if($('#_mepr_rules_drip_enabled').is(":checked")) {
      $('#mepr-rules-drip-area').show();
    } else {
      $('#mepr-rules-drip-area').hide();
    }
    $('#_mepr_rules_drip_enabled').click(function() {
      $('#mepr-rules-drip-area').slideToggle('fast');
    });

    //Toggler for drips expiration
    if($('#_mepr_rules_expires_enabled').is(":checked")) {
      $('#mepr-rules-expires-area').show();
    } else {
      $('#mepr-rules-expires-area').hide();
    }
    $('#_mepr_rules_expires_enabled').click(function() {
      $('#mepr-rules-expires-area').slideToggle('fast');
    });

    //Validate before saving post
    $('#publish').click(function() {
      if(!$('select.mepr-rule-access-select').val()) {
        alert(MeprRule.mepr_no_products_message); //Alerts user that they must create Memberships before they can save Rules
        return false;
      }
    });

    //Fixed date drips/expirations js
    if($('#_mepr_rules_drip_after').val() == 'fixed') {
      $('#_mepr_rules_drip_after_fixed').show();
    } else {
      $('#_mepr_rules_drip_after_fixed').hide();
    }

    $('#_mepr_rules_drip_after').change(function() {
      if($(this).val() == 'fixed') {
        $('#_mepr_rules_drip_after_fixed').show();
      } else {
        $('#_mepr_rules_drip_after_fixed').hide();
      }
    });

    if($('#_mepr_rules_expires_after').val() == 'fixed') {
      $('#_mepr_rules_expires_after_fixed').show();
    } else {
      $('#_mepr_rules_expires_after_fixed').hide();
    }

    $('#_mepr_rules_expires_after').change(function() {
      if($(this).val() == 'fixed') {
        $('#_mepr_rules_expires_after_fixed').show();
      } else {
        $('#_mepr_rules_expires_after_fixed').hide();
      }
    });

    $('#mepr-rules-form').on( 'blur', '.mepr-rule-types-autocomplete', function() {
      mepr_update_rule_post_title( $('#_mepr_rules_type').val(), $(this).val() );
    });

    var unauth_custom_ids = {
      excerpt: {
        src: '_mepr_rules_unauth_excerpt_type',
        target: '_mepr_rules_unauth_excerpt_type-size'
      },
      message: {
        src: '_mepr_rules_unauth_message_type',
        target: '_mepr_rules_unauth_message_type-editor'
      }
    };

    var unauth_custom = function(src,target) {
      if($('#'+src).val()=='custom')
        $('#'+target).slideDown();
      else
        $('#'+target).slideUp();
    };

    unauth_custom(unauth_custom_ids.excerpt.src,unauth_custom_ids.excerpt.target);
    $('#'+unauth_custom_ids.excerpt.src).change( function() {
      unauth_custom(unauth_custom_ids.excerpt.src,unauth_custom_ids.excerpt.target);
    });

    unauth_custom(unauth_custom_ids.message.src,unauth_custom_ids.message.target);
    $('#'+unauth_custom_ids.message.src).change( function() {
      unauth_custom(unauth_custom_ids.message.src,unauth_custom_ids.message.target);
    });
  });
})(jQuery);
