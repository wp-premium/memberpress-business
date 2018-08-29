<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php printf(_x('%s Reminder Sent', 'ui', 'memberpress'), '{$reminder_name}'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;">{$user_full_name} ({$username})</h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(_x('A %1$s Reminder (%2$s) was just sent to %3$s for the following subscription:', 'ui', 'memberpress'), '{$reminder_name}', '{$reminder_description}', '{$username}'); ?></div>
  <table style="clear: both;" class="transaction">
    <tr><th style="text-align: left;"><?php _ex('Membership:', 'ui', 'memberpress'); ?></th><td>{$product_name}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Subscription:', 'ui', 'memberpress'); ?></th><td>{$subscr_num}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Created:', 'ui', 'memberpress'); ?></th><td>{$subscr_date}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Expires:', 'ui', 'memberpress'); ?></th><td>{$subscr_expires_at}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('CC Expires:', 'ui', 'memberpress'); ?></th><td>{$subscr_cc_month_exp}/{$subscr_cc_year_exp}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Name:', 'ui', 'memberpress'); ?></th><td>{$user_full_name}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Email:', 'ui', 'memberpress'); ?></th><td>{$user_email}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Login:', 'ui', 'memberpress'); ?></th><td>{$user_login}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('User ID:', 'ui', 'memberpress'); ?></th><td>{$user_id}</td></tr>
  </table>
</div>

