<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-price-box-configuration" data-value="<?php echo $product->ID; ?>">
  <div id="preview-pane">
    <span id="pricing-preview-head" class="pricing-preview"><?php _e('Preview', 'memberpress'); ?></span>
    <?php MeprGroupsHelper::group_page_item($product, null, true); ?>
  </div>

  <div class="pricing-options-pane">
    <div>
      <input type="checkbox" name="<?php echo MeprProduct::$is_highlighted_str; ?>" id="<?php echo MeprProduct::$is_highlighted_str; ?>" <?php   checked($product->is_highlighted); ?> />
      <label for="<?php echo MeprProduct::$is_highlighted_str; ?>"><?php _e('Highlighted', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip('mepr-pricing-page-highlight',
                                    __('Highlight', 'memberpress'),
                                    __('<strong>Highlighted:</strong> Make this a Highlighted option on the Group Pricing Page. This makes it stand-out from the other listed memberships.', 'memberpress'));
      ?>
    </div>
    <br/>
    <div>
      <label><?php _e('Title:', 'memberpress'); ?></label><br/>
      <input type="text" name="<?php echo MeprProduct::$pricing_title_str; ?>" id="<?php echo MeprProduct::$pricing_title_str; ?>" value="<?php echo htmlspecialchars($product->pricing_title, ENT_QUOTES); ?>">
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-display"><?php _e('Pricing Display', 'memberpress'); ?></label>
      <?php
        MeprAppHelper::info_tooltip(
          'mepr-pricing-display',
          __('Pricing Display', 'memberpress'),
          __('This determines how the price will be displayed on the pricing table. If \'Auto\' is selected then MemberPress will automatically generate the price for you, if \'Custom\' is selected then you\'ll be able to enter your own custom pricing terms and if you select \'None\' then no price will be visible.', 'memberpress')
        );
      ?>
      <select id="mepr-pricing-display" name="<?php echo MeprProduct::$pricing_display_str; ?>">
        <option value="auto" <?php selected($product->pricing_display,'auto'); ?>><?php _e('Auto', 'memberpress'); ?></option>
        <option value="custom" <?php selected($product->pricing_display,'custom'); ?>><?php _e('Custom', 'memberpress'); ?></option>
        <option value="none" <?php selected($product->pricing_display,'none'); ?>><?php _e('None', 'memberpress'); ?></option>
      </select>
    </div>
    <div id="mepr-custom-pricing-display">
      <br/>
      <div class="mepr-sub-box mepr_custom_pricing_display_box">
        <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
        <div><?php _e('Custom Pricing', 'memberpress'); ?></div>
        <div><textarea name="<?php echo MeprProduct::$custom_price_str; ?>" id="mepr-custom-price" class="large-text"><?php echo $product->custom_price; ?></textarea></div>
      </div>
    </div>
    <br/>
    <div>
      <label><?php _e('Heading Text:', 'memberpress'); ?></label><br/>
      <textarea name="<?php echo MeprProduct::$pricing_heading_txt_str; ?>" id="<?php echo MeprProduct::$pricing_heading_txt_str; ?>"><?php echo $product->pricing_heading_txt; ?></textarea>
    </div>
    <br/>
    <div>
      <label><?php _e('Benefits:', 'memberpress'); ?></label>
      <ol id="sortable-benefits" class="mepr-sortable">
        <?php MeprProductsHelper::generate_pricing_benefits_list($product->pricing_benefits); ?>
      </ol>
      <?php MeprProductsHelper::show_pricing_benefits_add_new(); ?>
    </div>
    <br/>
    <div>
      <label><?php _e('Footer Text:', 'memberpress'); ?></label><br/>
      <textarea name="<?php echo MeprProduct::$pricing_footer_txt_str; ?>" id="<?php echo MeprProduct::$pricing_footer_txt_str; ?>"><?php echo   $product->pricing_footer_txt; ?></textarea>
    </div>
    <br/>
    <div>
      <label><?php _e('Button Text:', 'memberpress'); ?></label><br/>
      <input type="text" name="<?php echo MeprProduct::$pricing_button_txt_str; ?>" id="<?php echo MeprProduct::$pricing_button_txt_str; ?>" value="<?php echo (!empty($product->pricing_button_txt))?htmlspecialchars($product->pricing_button_txt, ENT_QUOTES):__('Sign Up', 'memberpress'); ?>" />
    </div>
    <br/>
    <div>
      <label for="mepr-pricing-button-position"><?php _e('Button Position', 'memberpress'); ?></label>
      <select id="mepr-pricing-button-position" name="<?php echo MeprProduct::$pricing_button_position_str; ?>">
        <option value="footer" <?php selected($product->pricing_button_position,'footer'); ?>><?php _e('Footer', 'memberpress'); ?></option>
        <option value="header" <?php selected($product->pricing_button_position,'header'); ?>><?php _e('Header', 'memberpress'); ?></option>
        <option value="both" <?php selected($product->pricing_button_position,'both'); ?>><?php _e('Both', 'memberpress'); ?></option>
      </select>
    </div>
  </div>
</div>

