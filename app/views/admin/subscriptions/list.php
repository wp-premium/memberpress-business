<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
$member_login = (isset($_GET['member']))?__('for', 'memberpress').' '.urldecode($_GET['member']):'';
?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Subscriptions', 'memberpress'); ?> <?php echo $member_login; ?></h2>
  <?php $sub_table->display(); ?>
</div>
