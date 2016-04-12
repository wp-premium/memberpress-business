<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper">
  <form class="mepr-signup-form mepr-form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; //Here for lead capturing JS ?>" novalidate>
    <input type="hidden" id="mepr_process_signup_form" name="mepr_process_signup_form" value="Y" />
    <input type="hidden" id="mepr_product_id" name="mepr_product_id" value="<?php echo $product->ID; ?>" />

    <?php if(MeprUtils::is_user_logged_in()): ?>
      <input type="hidden" name="logged_in_purchase" value="1" />
    <?php endif; ?>

    <?php if( $product->register_price_action != 'hidden' ): ?>
      <div class="mp-form-row mepr_bold mepr_price">
        <?php $price_label = ($product->is_one_time_payment() ? _x('Price:', 'ui', 'memberpress') : _x('Terms:', 'ui', 'memberpress')); ?>
        <label><?php echo $price_label; ?></label>
        <div class="mepr_price_cell">
          <?php MeprProductsHelper::display_invoice( $product, $mepr_coupon_code ); ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if((!MeprUtils::is_user_logged_in() ||
              (MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases)) &&
             $mepr_options->show_fname_lname): ?>
      <div class="mp-form-row mepr_first_name">
        <div class="mp-form-label">
          <label><?php _ex('First Name:', 'ui', 'memberpress'); echo ($mepr_options->require_fname_lname)?'*':''; ?></label>
          <span class="cc-error"><?php _ex('First Name Required', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="text" name="user_first_name" id="user_first_name" class="mepr-form-input" value="<?php echo $first_name_value; ?>" <?php echo ($mepr_options->require_fname_lname)?'required':''; ?> />
      </div>
      <div class="mp-form-row mepr_last_name">
        <div class="mp-form-label">
          <label><?php _ex('Last Name:', 'ui', 'memberpress'); echo ($mepr_options->require_fname_lname)?'*':''; ?></label>
          <span class="cc-error"><?php _ex('Last Name Required', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="text" name="user_last_name" id="user_last_name" class="mepr-form-input" value="<?php echo $last_name_value; ?>" <?php echo ($mepr_options->require_fname_lname)?'required':''; ?> />
      </div>
    <?php else: /* this is here to avoid validation issues */ ?>
      <input type="hidden" name="user_first_name" id="user_first_name" value="<?php echo $first_name_value; ?>" />
      <input type="hidden" name="user_last_name" id="user_last_name" value="<?php echo $last_name_value; ?>" />
    <?php endif; ?>

    <?php
      if(MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases) {
        MeprUsersHelper::render_custom_fields($product);
      }
      elseif(!MeprUtils::is_user_logged_in()) { // We only pass the 'signup' flag on initial Signup
        MeprUsersHelper::render_custom_fields($product, true);
      }
    ?>

    <?php if(MeprUtils::is_user_logged_in()): ?>
      <input type="hidden" name="user_email" id="user_email" value="<?php echo stripslashes($mepr_current_user->user_email); ?>" />
    <?php else: ?>
      <input type="hidden" class="mepr-geo-country" name="mepr-geo-country" value="" />

      <?php if(!$mepr_options->username_is_email): ?>
        <div class="mp-form-row mepr_username">
          <div class="mp-form-label">
            <label><?php _ex('Username:*', 'ui', 'memberpress'); ?></label>
            <span class="cc-error"><?php _ex('Invalid Username', 'ui', 'memberpress'); ?></span>
          </div>
          <input type="text" name="user_login" id="user_login" class="mepr-form-input" value="<?php echo (isset($user_login))?esc_attr(stripslashes($user_login)):''; ?>" required />
        </div>
      <?php endif; ?>
      <div class="mp-form-row mepr_email">
        <div class="mp-form-label">
          <label><?php _ex('Email:*', 'ui', 'memberpress'); ?></label>
          <span class="cc-error"><?php _ex('Invalid Email', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="email" name="user_email" id="user_email" class="mepr-form-input" value="<?php echo (isset($user_email))?esc_attr(stripslashes($user_email)):''; ?>" required />
      </div>
      <?php MeprHooks::do_action('mepr-after-email-field'); ?>
      <div class="mp-form-row mepr_password">
        <div class="mp-form-label">
          <label><?php _ex('Password:*', 'ui', 'memberpress'); ?></label>
          <span class="cc-error"><?php _ex('Invalid Password', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="password" name="mepr_user_password" id="mepr_user_password" class="mepr-form-input mepr-password" value="<?php echo (isset($mepr_user_password))?esc_attr(stripslashes($mepr_user_password)):''; ?>" required />
      </div>
      <div class="mp-form-row mepr_password_confirm">
        <div class="mp-form-label">
          <label><?php _ex('Password Confirmation:*', 'ui', 'memberpress'); ?></label>
          <span class="cc-error"><?php _ex('Password Confirmation Doesn\'t Match', 'ui', 'memberpress'); ?></span>
        </div>
        <input type="password" name="mepr_user_password_confirm" id="mepr_user_password_confirm" class="mepr-form-input mepr-password-confirm" value="<?php echo (isset($mepr_user_password_confirm))?esc_attr(stripslashes($mepr_user_password_confirm)):''; ?>" required />
      </div>
      <?php MeprHooks::do_action('mepr-after-password-fields'); ?>
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-before-coupon-field'); ?>

    <?php if($product->adjusted_price() > 0.00): ?>
      <?php if($mepr_options->coupon_field_enabled): ?>
        <div class="mp-form-row mepr_coupon">
          <div class="mp-form-label">
            <label><?php _ex('Coupon Code:', 'ui', 'memberpress'); ?></label>
            <span class="mepr-coupon-loader mepr-hidden">
              <img src="<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>" width="100" height="10" />
            </span>
            <span class="cc-error"><?php _ex('Invalid Coupon', 'ui', 'memberpress'); ?></span>
          </div>
          <input type="text" id="mepr_coupon_code-<?php echo $product->ID; ?>" class="mepr-form-input mepr-coupon-code" name="mepr_coupon_code" value="<?php echo (isset($mepr_coupon_code))?esc_attr(stripslashes($mepr_coupon_code)):''; ?>" data-prd-id="<?php echo $product->ID; ?>" />
        </div>
      <?php else: ?>
        <input type="hidden" id="mepr_coupon_code-<?php echo $product->ID; ?>" name="mepr_coupon_code" value="<?php echo (isset($mepr_coupon_code))?esc_attr(stripslashes($mepr_coupon_code)):''; ?>" />
      <?php endif; ?>
      <?php $active_pms = $product->payment_methods(); ?>
      <?php $pms = $product->payment_methods(); ?>
      <div class="mp-form-row mepr_payment_method">
        <?php echo MeprOptionsHelper::payment_methods_dropdown('mepr_payment_method', $active_pms); ?>
      </div>
    <?php endif; ?>

    <?php if(!MeprUtils::is_user_logged_in()): ?>
      <?php if($mepr_options->require_tos): ?>
        <div class="mp-form-row mepr_tos">
          <label for="mepr_agree_to_tos" class="mepr-checkbox-field mepr-form-input" required>
            <input type="checkbox" name="mepr_agree_to_tos" id="mepr_agree_to_tos" <?php checked(isset($mepr_agree_to_tos)); ?> />
            <a href="<?php echo stripslashes($mepr_options->tos_url); ?>" target="_blank"><?php echo stripslashes($mepr_options->tos_title); ?></a>*
          </label>
        </div>
      <?php endif; ?>

      <?php // This thing needs to be hidden in order for this to work so we do it explicitly as a style ?>
      <input type="text" id="mepr_no_val" name="mepr_no_val" class="mepr-form-input" autocomplete="off" />
    <?php endif; ?>

    <?php MeprHooks::do_action('mepr-user-signup-fields'); ?>

    <div class="mepr_spacer">&nbsp;</div>

    <input type="submit" class="mepr-submit" value="<?php echo stripslashes($product->signup_button_text); ?>" />
    <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />
    <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
  </form>
</div>

