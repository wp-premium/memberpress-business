<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php _ex('Payment from {$user_full_name}', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;"><?php _ex('{$product_name} &ndash; {$trans_num}', 'ui', 'memberpress'); ?></h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <table style="clear: both;" class="transaction">
    <tr><th style="text-align: left;"><?php _ex('Payment Amount:', 'ui', 'memberpress'); ?></th><td>{$payment_amount}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Invoice Number:', 'ui', 'memberpress'); ?></th><td>{$invoice_num}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Invoice Date:', 'ui', 'memberpress'); ?></th><td>{$trans_date}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Transaction:', 'ui', 'memberpress'); ?></th><td>{$trans_num}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Payment System:', 'ui', 'memberpress'); ?></th><td>{$trans_gateway}</td></tr>
  </table>
  <table style="clear: both; width: 100%;" class="labels">
    <tr>
      <td style="vertical-align: top;">
        <fieldset style="border: none; border-top: 1px solid #dedede; margin: 20px 40px 20px 0;" class="billing">
          <legend style="display: block; font-weight: bold; color: #999;"><?php _ex('Billed to', 'ui', 'memberpress'); ?></legend>
          <address style="font-style: normal;">
            <div class="address_name" style="display: block; font-size: 115%;"><big>{$user_full_name}</big></div>
            <div class="address_email" style="display: block;">{$user_email} (<b>{$user_login}</b>)</div>
            <div class="address_address" style="display: block;">{$user_address}</div>
          </address>
        </fieldset>
      </td>
    </tr>
  </table>
</div>

