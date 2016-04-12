<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-login-page-meta-box-form">
  <input type="checkbox" id="_mepr_manual_login_form" name="_mepr_manual_login_form" <?php checked($manual_login_form); ?> />
  <label for="_mepr_manual_login_form"><?php _e('Manually place the login form on the page', 'memberpress'); ?></label>
  <?php
    MeprAppHelper::info_tooltip('mepr-page-manual-login-form',
                                __('Manually place Login form', 'memberpress'),
                                __('By default MemberPress will append the login form to the end of the Login page. If you would like to show it in a different place on the Login page check this box then copy and paste the shortcode that appears below where you want it to appear on the page. The [mepr-login-form] shortcode can be used on any page or post on your site to display a login form.', 'memberpress'));
  ?>
  <div id="mepr-shortcode-login-page-area">
    [mepr-login-form]
  </div>

  <!-- The NONCE below prevents post meta from being blanked on move to trash -->
  <input type="hidden" name="<?php echo MeprUser::$nonce_str; ?>" value="<?php echo wp_create_nonce(MeprUser::$nonce_str.wp_salt()); ?>" />
</div>
