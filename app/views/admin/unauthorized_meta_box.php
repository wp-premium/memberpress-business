<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper">
  <div id="mepr-unauthorized-message">
    <input type="hidden" name="mepr_custom_unauthorized_nonce" value="<?php echo $_wpnonce; ?>" />
    <div class="mepr-main-pane">
      <?php
      MeprOptionsHelper::display_show_excerpts_dropdown( '_mepr_unauth_excerpt_type', $unauth_excerpt_type,
                                                         '_mepr_unauth_excerpt_size', $unauth_excerpt_size );
      ?>
    </div>
    <div class="mepr-main-pane">
      <?php
      MeprOptionsHelper::display_unauth_message_dropdown( '_mepr_unauthorized_message_type', $unauthorized_message_type,
                                                          '_mepr_unauthorized_message', $unauthorized_message );
      ?>
    </div>
    <div class="mepr-main-pane">
      <?php
      MeprOptionsHelper::display_unauth_login_dropdown( '_mepr_unauth_login', $unauth_login );
      ?>
    </div>
  </div>
</div>
<?php MeprHooks::do_action( 'mepr-unauthorized-message-options', $unauthorized_message_type, $unauthorized_message ); ?>
