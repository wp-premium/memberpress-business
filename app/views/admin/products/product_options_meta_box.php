<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<h2 class="nav-tab-wrapper">
  <a class="nav-tab main-nav-tab nav-tab-active" href="#" id="registration"><?php _e('Registration', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="who-can-purchase"><?php _e('Permissions', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="group-layout"><?php _e('Price Box', 'memberpress'); ?></a>
  <a class="nav-tab main-nav-tab" href="#" id="advanced"><?php _e('Advanced', 'memberpress'); ?></a>
  <?php MeprHooks::do_action('mepr-product-options-tabs', $product); ?>
</h2>

<div id="product_options_wrapper">
  <div class="product_options_page registration">
    <?php MeprView::render('/admin/products/registration', get_defined_vars()); ?>
  </div>
  <div class="product_options_page who-can-purchase">
    <?php MeprView::render('/admin/products/permissions', get_defined_vars()); ?>
  </div>
  <div class="product_options_page group-layout">
    <?php MeprView::render('/admin/products/price_box', get_defined_vars()); ?>
  </div>
  <div class="product_options_page advanced">
    <?php MeprView::render('/admin/products/advanced', get_defined_vars()); ?>
  </div>
  <?php MeprHooks::do_action('mepr-product-options-pages', $product); ?>
</div>
