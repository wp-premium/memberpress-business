<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<input type="text" size="2" name="<?php echo MeprReminder::$trigger_length_str; ?>" id="<?php echo MeprReminder::$trigger_length_str; ?>" value="<?php echo $reminder->trigger_length; ?>" />
<select name="<?php echo MeprReminder::$trigger_interval_str; ?>" id="<?php echo MeprReminder::$trigger_interval_str; ?>">
  <option value="hours"<?php selected($reminder->trigger_interval,'hours'); ?>><?php _e('hours', 'memberpress'); ?></option>
  <option value="days"<?php selected($reminder->trigger_interval,'days'); ?>><?php _e('days', 'memberpress'); ?></option>
  <option value="weeks"<?php selected($reminder->trigger_interval,'weeks'); ?>><?php _e('weeks', 'memberpress'); ?></option>
  <option value="months"<?php selected($reminder->trigger_interval,'months'); ?>><?php _e('months', 'memberpress'); ?></option>
  <option value="years"<?php selected($reminder->trigger_interval,'years'); ?>><?php _e('years', 'memberpress'); ?></option>
</select>
<?php $trigger = "{$reminder->trigger_timing}_{$reminder->trigger_event}"; ?>
<select id="trigger">
  <option value="after_member-signup" <?php selected($trigger,'after_member-signup'); ?>><?php _e("after Member Signs Up", 'memberpress'); ?></option>
  <option value="after_signup-abandoned" <?php selected($trigger,'after_signup-abandoned'); ?>><?php _e("after Signup Abandoned", 'memberpress'); ?></option>
  <option value="before_sub-expires" <?php selected($trigger,'before_sub-expires'); ?>><?php _e("before Subscription Expires", 'memberpress'); ?></option>
  <option value="after_sub-expires" <?php selected($trigger,'after_sub-expires'); ?>><?php _e("after Subscription Expires", 'memberpress'); ?></option>
  <option value="before_sub-renews" <?php selected($trigger,'before_sub-renews'); ?>><?php _e("before Subscription Renews", 'memberpress'); ?></option>
  <?php /*
  <option value="before_rule-drips" <?php selected($trigger,'before_rule-drips'); ?>><?php _e("before Rule Drips", 'memberpress'); ?></option>
  <option value="after_rule-drips" <?php selected($trigger,'after_rule-drips'); ?>><?php _e("after Rule Drips", 'memberpress'); ?></option>
  <option value="before_rule-expires" <?php selected($trigger,'before_rule-expires'); ?>><?php _e("before Rule Expires", 'memberpress'); ?></option>
  <option value="after_rule-expires" <?php selected($trigger,'after_rule-expires'); ?>><?php _e("after Rule Expires", 'memberpress'); ?></option>
  */ ?>
  <option value="before_cc-expires" <?php selected($trigger,'before_cc-expires'); ?>><?php _e("before Credit Card Expires", 'memberpress'); ?></option>
  <option value="after_cc-expires" <?php selected($trigger,'after_cc-expires'); ?>><?php _e("after Credit Card Expires", 'memberpress'); ?></option>
</select>
<input type="hidden" name="<?php echo MeprReminder::$trigger_timing_str; ?>" id="<?php echo MeprReminder::$trigger_timing_str; ?>" value="<?php echo $reminder->trigger_timing; ?>" />
<input type="hidden" name="<?php echo MeprReminder::$trigger_event_str; ?>" id="<?php echo MeprReminder::$trigger_event_str; ?>" value="<?php echo $reminder->trigger_event; ?>" />
<input type="hidden" name="<?php echo MeprReminder::$nonce_str; ?>" value="<?php echo $nonce; ?>" />

