(function($) {
  $(document).ready(function() {
    //Make memberships sortable
    $(function() {
      $('#sortable-products').sortable();
    });
    //Add new membership li
    $('a#add-new-product').click(function() {
      $('ol#sortable-products').append($('div#hidden-line-item').html());
      return false;
    });
    //Remove a membership li
    $('body').on('click', 'a.remove-product-item', function() {
      $(this).parent().parent().remove();
      return false;
    });
    //Alert if membership already is assigned to another group
    $('body').on('change', 'select.group_products_dropdown', function() {
      var data = {
        action: 'mepr_is_product_already_in_group',
        product_id: $(this).val()
      };
      $.post(ajaxurl, data, function(response) {
        if(response != '') {
          alert(response); //Alerts the user to the fact that this Membership is already in a group
        }
      });
    });

    //Change mouse pointer over li items
    $('body').on('mouseenter', '.mepr-sortable li', function() {
      $(this).addClass('mepr-hover');
    });
    $('body').on('mouseleave', '.mepr-sortable li', function() {
      $(this).removeClass('mepr-hover');
    });

    //hide pricing page theme box if Disable Pricing Page is on
    if($('#_mepr_group_pricing_page_disabled').is(":checked")) {
      $('#mepr_hidden_pricing_page_theme').hide();
    } else {
      $('#mepr_hidden_pricing_page_theme').show();
    }
    //hide alternate group url box if Disable Pricing Page is off
    if(!$('#_mepr_group_pricing_page_disabled').is(":checked")) {
      $('#mepr_hidden_alternate_group_url').hide();
    } else {
      $('#mepr_hidden_alternate_group_url').show();
    }
    $('#_mepr_group_pricing_page_disabled').click(function() {
      $('#mepr_hidden_pricing_page_theme').slideToggle('fast');
      $('#mepr_hidden_alternate_group_url').slideToggle('fast');
    });

    // Page Template Toggle
    if( $('#_mepr_use_custom_template').is(':checked') ) {
      $('#mepr-custom-page-template-select').show();
    }

    $('#_mepr_use_custom_template').click( function() {
      if($(this).is(':checked')) {
        $('#mepr-custom-page-template-select').slideDown();
      }
      else {
        $('#mepr-custom-page-template-select').slideUp();
      }
    });
  });
})(jQuery);
