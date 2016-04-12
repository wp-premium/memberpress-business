<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="product-options-panel">
  <div id="mepr-product-registration-button-text">
    <span><?php _e('Registration Button Text:', 'memberpress'); ?></span>
    <input name="<?php echo MeprProduct::$signup_button_text_str; ?>" id="<?php echo MeprProduct::$signup_button_text_str; ?>" type="text" value="<?php echo $product->signup_button_text; ?>" />
  </div>
  <?php $calculate_taxes = get_option('mepr_calculate_taxes'); ?>
  <?php if($calculate_taxes): ?>
    <div id="mepr-membership-tax-exempt" class="mepr-product-adv-item">
      <input type="checkbox" name="<?php echo MeprProduct::$tax_exempt_str; ?>" id="<?php echo MeprProduct::$tax_exempt_str; ?>" <?php checked($product->tax_exempt); ?> />
      <span><?php _e('This Membership is Tax Exempt', 'memberpress'); ?></span>
      <?php
        MeprAppHelper::info_tooltip('mepr-product-tax-exempt',
          __('This Membership is Tax Exempt', 'memberpress'),
          __('If this option is checked then taxes won\'t be calcluated for this Membership.', 'memberpress'));
      ?>
    </div>
  <?php endif; ?>
  <div id="mepr-product-thank-you-message" class="mepr-product-adv-item">
    <input type="checkbox" name="<?php echo MeprProduct::$thank_you_page_enabled_str; ?>" id="<?php echo MeprProduct::$thank_you_page_enabled_str; ?>" <?php checked($product->thank_you_page_enabled); ?> />
    <label for="<?php echo MeprProduct::$thank_you_page_enabled_str; ?>"><?php _e('Enable custom thank you page message', 'memberpress'); ?></label>
    <?php
      MeprAppHelper::info_tooltip('mepr-product-custom-thank-you-page',
                                  __('Enable Custom Thank You Page Message', 'memberpress'),
                                  __('Enabling this option will reveal a new Compose form which you can use to provide a custom message to show on the Thank You page after a member purchases this membership.', 'memberpress'));
    ?>

    <div id="mepr-product-thank-you-area">
      <?php wp_editor(stripslashes($product->thank_you_message), 'meprproductthankyoumessage'); ?>
    </div>
  </div>

  <div id="mepr-product-welcome-email">
    <?php MeprAppHelper::display_emails('MeprBaseProductEmail',array(array('product_id'=>$product->ID))); ?>
  </div>

  <div id="mepr-product-payment-methods-wrap">
    <input type="checkbox" name="<?php echo MeprProduct::$customize_payment_methods_str; ?>" id="<?php echo MeprProduct::$customize_payment_methods_str; ?>" <?php checked($product->customize_payment_methods); ?> />
    <label for="<?php echo MeprProduct::$customize_payment_methods_str; ?>"><?php _e('Customize Payment Methods', 'memberpress'); ?></label>
    <?php
      MeprAppHelper::info_tooltip('mepr-product-customize-payment-methods',
                                  __('Customize Payment Methods', 'memberpress'),
                                  __('Enabling this option will reveal a drag and drop list of the available payment methods. You can use this to re-order or even hide payment methods from the dropdown on this membership registration page.', 'memberpress'));
    ?>
    <div id="mepr-product-payment-methods" class="mepr-options-pane mepr_hidden">
      <h4><?php _e('Active Payment Methods', 'memberpress'); ?></h4>
      <?php
        $pms = $mepr_options->payment_methods();

        unset($pms['free']);
        unset($pms['manual']);

        if(empty($pms)) {
          ?>
            <div><?php _e('No Payment Methods were found. Please go to the options page to configure some.','memberpress'); ?></div>
          <?php
        }
        else {
          $pmkeys = array_keys($pms);
          $active_pms = $product->payment_methods();
          $inactive_pms = array_diff($pmkeys, $active_pms);
          ?>
          <ul id="mepr-product-active-payment-methods" class="mepr-sortable">
            <?php
            foreach($active_pms as $active_pm) {
              $pm = $pms[$active_pm];
              ?>
                <li><input type="checkbox" data-id="<?php echo $active_pm; ?>" checked="checked" /> <?php echo "{$pm->label} ({$pm->name})"; ?></li>
              <?php
            }
            ?>
          </ul>

          <h4 id="mepr-product-inactive-payment-methods-title" class="mepr_hidden"><?php _e('Inactive Payment Methods', 'memberpress'); ?></h4>
          <ul id="mepr-product-inactive-payment-methods" class="mepr_hidden">
            <?php
            foreach( $inactive_pms as $inactive_pm ) {
              $pm = $pms[$inactive_pm];
              ?>
                <li><input type="checkbox" data-id="<?php echo $inactive_pm; ?>" /> <?php echo "{$pm->label} ({$pm->name})"; ?></li>
              <?php
            }
            ?>
          </ul>
          <?php
        }
      ?>
      <textarea name="mepr-product-payment-methods-json" id="mepr-product-payment-methods-json" class="mepr_hidden"><?php echo json_encode($active_pms); ?></textarea>
    </div>
  </div>

  <div id="mepr-product-profile-fields-wrap">
    <input type="checkbox" name="<?php echo MeprProduct::$customize_profile_fields_str; ?>" id="<?php echo MeprProduct::$customize_profile_fields_str; ?>" <?php checked($product->customize_profile_fields); ?> />
    <label for="<?php echo MeprProduct::$customize_profile_fields_str; ?>"><?php _e('Customize User Information Fields', 'memberpress'); ?></label>
    <?php
      MeprAppHelper::info_tooltip('mepr-product-customize-profile-fields',
                                  __('Customize User Information Fields', 'memberpress'),
                                  __('Enabling this option will reveal a drag and drop list of the available Custom User Information Fields you defined in the MemberPress Options. You can use this to show/hide certain fields from this membership registration form.', 'memberpress'));
    ?>
    <div id="mepr-product-profile-fields" class="mepr-options-pane mepr_hidden">
      <h4><?php _e('Active Fields', 'memberpress'); ?></h4>
      <?php
        $fields = $mepr_options->custom_fields;

        if(empty($fields)) {
          ?>
            <div><?php _e('No fields were found. Please go to the options page to configure some.', 'memberpress'); ?></div>
          <?php
        }
        else {
          $slugs = $mepr_options->get_custom_field_slugs();

          if($product->customize_profile_fields)
            $active_slugs = $product->custom_profile_fields;
          else
            $active_slugs = $slugs;
          ?>
          <ul id="mepr-product-active-profile-fields">
            <?php
            foreach($mepr_options->custom_fields as $row) {
              if(in_array($row->field_key, $active_slugs)) {
                ?>
                  <li class="mepr-profile-field-row"><input type="checkbox" name="product-profile-fields[<?php echo $row->field_key; ?>]" checked="checked" class="mepr-profile-field-box" /> <?php echo __(stripslashes($row->field_name), 'memberpress'); ?></li>
                <?php
              }
            }
            ?>
          </ul>

          <h4 id="mepr-product-inactive-profile-fields-title" class="mepr_hidden"><?php _e('Inactive Fields', 'memberpress'); ?></h4>
          <ul id="mepr-product-inactive-profile-fields" class="mepr_hidden">
            <?php
            foreach($mepr_options->custom_fields as $row) {
              if(!in_array($row->field_key, $active_slugs)) {
                ?>
                  <li class="mepr-profile-field-row"><input type="checkbox" name="product-profile-fields[<?php echo $row->field_key; ?>]" class="mepr-profile-field-box" /> <?php echo __(stripslashes($row->field_name), 'memberpress'); ?></li>
                <?php
              }
            }
            ?>
          </ul>
          <?php
        }
      ?>
    </div>
  </div>

  <?php if($mepr_options->show_address_fields && $mepr_options->require_address_fields): ?>
    <div id="mepr-product-disable-address-fields-wrap">
      <input type="checkbox" name="<?php echo MeprProduct::$disable_address_fields_str; ?>" id="<?php echo MeprProduct::$disable_address_fields_str; ?>" <?php checked($product->disable_address_fields); ?> />
      <label for="<?php echo MeprProduct::$disable_address_fields_str; ?>"><?php _e('Disable Address Fields', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip('mepr-product-disable-address-fields',
                                    __('Disable Address Fields', 'memberpress'),
                                    __('When Addresses are enabled and required, you can disable this option only on free memberships.', 'memberpress'));
      ?>
    </div>
  <?php endif; ?>

  <?php MeprHooks::do_action('mepr-product-registration-metabox', $product); ?>

  <div id="mepr-product-manually-place-form">
    <?php //Manually place the registration form on the page ?>
    <a href="#" data-target="#mepr-product-shortcodes" class="mepr-slide-toggle"><?php _e('Membership Shortcodes', 'memberpress'); ?></a><br/><br/>
    <div id="mepr-product-shortcodes" class="mepr-radius-border mepr-hidden">
      <p class="description"><?php _e('You can use this shortcode anywhere on your site to quickly display a link to this membership page. If the text inbetween the shortcode is not present, MemberPress will use the membership title as the link text instead.', 'memberpress'); ?></p>
      [mepr-membership-link id="<?php echo $product->ID; ?>"] <?php _e('Optional link label here...', 'memberpress'); ?> [/mepr-membership-link]<br/><br/>
      <?php if(!empty($product->access_url)): ?>
        <p class="description"><?php _e('This shortcode can be used to show a link to the Membership Access URL set in the Advanced tab.', 'memberpress'); ?></p>
        [mepr-membership-access-url id="<?php echo $product->ID; ?>"] <?php _e('Optional link label here...', 'memberpress'); ?> [/mepr-membership-access-url]<br/><br/>
      <?php endif; ?>
      <p class="description"><?php _e('Shortcode to be used on this membership page to manually place the registration form.', 'memberpress'); ?></p>
      [mepr-membership-registration-form]<br/><br/>
      <p class="description"><?php _e('Shortcode which can be used on any other WordPress page, post or custom post type to manually place the registration form for this membership.', 'memberpress'); ?></p>
      [mepr-membership-registration-form id="<?php echo $product->ID; ?>"]
    </div>
  </div>
</div>

