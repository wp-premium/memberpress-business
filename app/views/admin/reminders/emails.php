<div id="mepr-reminder-emails">
  <?php MeprAppHelper::display_emails( 'MeprBaseReminderEmail', array(array('reminder_id'=>$reminder->ID)) ); ?>
</div>
<div id="mepr-reminder-products">
  <input type="checkbox" name="<?php echo MeprReminder::$filter_products_str; ?>" id="<?php echo MeprReminder::$filter_products_str; ?>" <?php checked($reminder->filter_products); ?> />
  <label for="<?php echo MeprReminder::$filter_products_str; ?>"><?php _e("Send only for specific Memberships", 'memberpress'); ?></label>
  <div id="mepr-reminder-products-hidden">
    <?php _e("Memberships for this Reminder", 'memberpress'); ?>
    <br/>
    <?php MeprRemindersHelper::products_multiselect(MeprReminder::$products_str, $reminder->products); ?>
  </div>
</div>
