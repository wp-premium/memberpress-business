<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-custom-page-template">
  <input type="checkbox" name="<?php echo MeprProduct::$use_custom_template_str; ?>" id="<?php echo MeprProduct::$use_custom_template_str; ?>" <?php checked($product->use_custom_template); ?> />
  <label for="<?php echo MeprProduct::$use_custom_template_str; ?>"><?php _e('Use Custom Page Template', 'memberpress'); ?></label>
  <div id="mepr-custom-page-template-select" class="mepr_hidden">
    <br/>
    <?php MeprAppHelper::page_template_dropdown( MeprProduct::$custom_template_str, $product->custom_template ); ?>
  </div>
</div>

