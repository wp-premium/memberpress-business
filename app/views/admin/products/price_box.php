<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-price-box-configuration" data-value="<?php echo $product->ID; ?>">
  <div id="preview-pane">
    <span id="pricing-preview-head" class="pricing-preview"><?php _e('Preview', 'memberpress'); ?></span>
    <?php MeprGroupsHelper::group_page_item($product, null, true); ?>
  </div>

  <div class="pricing-options-pane">
    <input type="checkbox" name="<?php echo MeprProduct::$is_highlighted_str; ?>" id="<?php echo MeprProduct::$is_highlighted_str; ?>" <?php   checked($product->is_highlighted); ?> />
    <label for="<?php echo MeprProduct::$is_highlighted_str; ?>"><?php _e('Highlighted', 'memberpress'); ?></label>
    <?php
      MeprAppHelper::info_tooltip('mepr-pricing-page-highlight',
                                  __('Highlight', 'memberpress'),
                                  __('<strong>Highlighted:</strong> Make this a Highlighted option on the Group Pricing Page. This makes it stand-out from the other listed memberships.', 'memberpress'));
    ?><span>&nbsp;&nbsp;&nbsp;</span>

    <input type="checkbox" name="<?php echo MeprProduct::$pricing_show_price_str; ?>" id="<?php echo MeprProduct::$pricing_show_price_str; ?>" <?php checked($product->pricing_show_price); ?> />
    <label for="<?php echo MeprProduct::$pricing_show_price_str; ?>"><?php _e('Show Price', 'memberpress'); ?></label>
    <br/></br>

    <label><?php _e('Title:', 'memberpress'); ?></label><br/>
    <input type="text" name="<?php echo MeprProduct::$pricing_title_str; ?>" id="<?php echo MeprProduct::$pricing_title_str; ?>" value="<?php echo   htmlspecialchars($product->pricing_title, ENT_QUOTES); ?>">
    <br/><br/>

    <label><?php _e('Heading Text:', 'memberpress'); ?></label><br/>
    <textarea name="<?php echo MeprProduct::$pricing_heading_txt_str; ?>" id="<?php echo MeprProduct::$pricing_heading_txt_str; ?>"><?php echo   $product->pricing_heading_txt; ?></textarea>
    <br/></br>

    <label><?php _e('Benefits:', 'memberpress'); ?></label>
    <ol id="sortable-benefits" class="mepr-sortable">
      <?php MeprProductsHelper::generate_pricing_benefits_list($product->pricing_benefits); ?>
    </ol>
    <?php MeprProductsHelper::show_pricing_benefits_add_new(); ?>
    <br/><br/>

    <label><?php _e('Footer Text:', 'memberpress'); ?></label><br/>
    <textarea name="<?php echo MeprProduct::$pricing_footer_txt_str; ?>" id="<?php echo MeprProduct::$pricing_footer_txt_str; ?>"><?php echo   $product->pricing_footer_txt; ?></textarea>
    <br/><br/>

    <label><?php _e('Button Text:', 'memberpress'); ?></label><br/>
    <input type="text" name="<?php echo MeprProduct::$pricing_button_txt_str; ?>" id="<?php echo MeprProduct::$pricing_button_txt_str; ?>" value="<?php echo (!empty($product->pricing_button_txt))?htmlspecialchars($product->pricing_button_txt, ENT_QUOTES):__('Sign Up', 'memberpress'); ?>" />
  </div>
</div>
