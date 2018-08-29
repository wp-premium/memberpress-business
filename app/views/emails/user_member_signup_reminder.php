<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom:4px;">Thanks for signing up</h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(_x('Hi %s,', 'ui', 'memberpress'), '{$user_first_name}'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php printf(_x('Thanks for registering for {$product_name}!', 'ui', 'memberpress')); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('Cheers!', 'ui', 'memberpress'); ?></div>
    <div class="section" style="display: block; margin-bottom: 24px;"><?php _ex('The {$blog_name} Team', 'ui', 'memberpress'); ?></div>
  </div>
</div>

