<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 4px;"><?php _ex('Thanks for Purchasing {$product_name}', 'ui', 'memberpress'); ?></h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('You can login here: {$login_page}', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('Using this username and password:', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;">
      <table style="clear: both;" class="transaction">
        <tr><th style="text-align: left;"><?php _ex('Username:', 'ui', 'memberpress'); ?></th><td>{$username}</td></tr>
        <tr><th style="text-align: left;"><?php _ex('Password:', 'ui', 'memberpress'); ?></th><td><?php _ex('*** Password you set during signup ***', 'ui', 'memberpress'); ?></td></tr>
      </table>
    </div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('Cheers!', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('The {$blog_name} Team', 'ui', 'memberpress'); ?></div>
  </div>
</div>

