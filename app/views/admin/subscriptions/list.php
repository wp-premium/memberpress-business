<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
$member_login = (isset($_GET['member']))?__('for', 'memberpress').' '.urldecode($_GET['member']):'';
?>

<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Subscriptions', 'memberpress'); ?> <?php echo $member_login; ?><a href="<?php echo admin_url('admin.php?page=memberpress-' . ($sub_table->lifetime ? 'lifetimes' : 'subscriptions') . '&action=new'); ?>" class="add-new-h2"><?php _e('Add New', 'memberpress'); ?></a></h2>
  <?php $sub_table->display(); ?>
</div>
