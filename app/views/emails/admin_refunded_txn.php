<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php _ex('Transaction Refunded', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;"><?php _ex('{$trans_num} &ndash; {$user_full_name}', 'ui', 'memberpress'); ?></h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('A transaction was just refunded on {$blog_name}:', 'ui', 'memberpress'); ?></div>
  <table style="clear: both;" class="transaction">
    <tr><th style="text-align: left;"><?php _ex('Amount:', 'ui', 'memberpress'); ?></th><td>{$payment_amount}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Transaction:', 'ui', 'memberpress'); ?></th><td>{$trans_num}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Date:', 'ui', 'memberpress'); ?></th><td>{$trans_date}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Status:', 'ui', 'memberpress'); ?></th><td><?php _ex('Refunded', 'ui', 'memberpress'); ?></td></tr>
    <tr><th style="text-align: left;"><?php _ex('Payment System:', 'ui', 'memberpress'); ?></th><td>{$trans_gateway}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Name:', 'ui', 'memberpress'); ?></th><td>{$user_full_name}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Email:', 'ui', 'memberpress'); ?></th><td>{$user_email}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Login:', 'ui', 'memberpress'); ?></th><td>{$user_login}</td></tr>
  </table>
</div>

