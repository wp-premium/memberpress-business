<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper">
  <h3><?php _ex('Enter your new password', 'ui', 'memberpress'); ?></h3>
  <form name="mepr_reset_password_form" id="mepr_reset_password_form" class="mepr-form" action="" method="post">
    <?php /* nonce not necessary on this form seeing as the user isn't logged in yet */ ?>
    <div class="mp-form-row mepr_password">
      <div class="mp-form-label">
        <label><?php _ex('Password', 'ui', 'memberpress'); ?>:</label>
      </div>
      <input type="password" name="mepr_user_password" id="mepr_user_password" class="mepr-form-input mepr-forgot-password" tabindex="700" />
    </div>
    <div class="mp-form-row mepr_password_confirm">
      <div class="mp-form-label">
        <label><?php _ex('Password Confirmation', 'ui', 'memberpress'); ?>:</label>
      </div>
      <input type="password" name="mepr_user_password_confirm" id="mepr_user_password_confirm" class="mepr-form-input mepr-forgot-password-confirm" tabindex="710"/>
    </div>
    <?php MeprHooks::do_action('mepr-reset-password-after-password-fields'); ?>

    <div class="mepr_spacer">&nbsp;</div>
    <div class="submit">
      <input type="submit" name="wp-submit" id="wp-submit" class="button-primary mepr-share-button " value="<?php _ex('Update Password', 'ui', 'memberpress'); ?>" tabindex="720" />
      <input type="hidden" name="action" value="mepr_process_reset_password_form" />
      <input type="hidden" name="mepr_screenname" value="<?php echo $mepr_screenname; ?>" />
      <input type="hidden" name="mepr_key" value="<?php echo $mepr_key; ?>" />
    </div>
  </form>
</div>
