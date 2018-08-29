<?php
if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

$member = (isset($_GET['member']))?$_GET['member']:'';
$member = (isset($_GET['search']) && isset($_GET['search-field']) && ($_GET['search-field'] == 'user' || $_GET['search-field'] == 'email'))?$_GET['search']:'';
$member_str = !empty($member)?__('for', 'memberpress').' '.urldecode($member):'';

?>
<div class="wrap">
  <div class="icon32"></div>
  <h2><?php _e('Transactions', 'memberpress'); ?> <?php echo $member_str; ?> <a href="<?php echo admin_url('admin.php?page=memberpress-trans&action=new&user='.$member); ?>" class="add-new-h2"><?php _e('Add New', 'memberpress'); ?></a></h2>
  <input type="hidden" name="mepr-update-transactions" value="Y" />

  <!-- Display which coupon is being filtered on -->
  <?php if(isset($_GET['coupon_id']) && !empty($_GET['coupon_id']) && ($coupon = new MeprCoupon((int)$_GET['coupon_id'])) && $coupon->ID > 0): ?>
    <h3>
      <?php _e('All Transactions for Coupon', 'memberpress'); ?>:
      <span id="txn-coupon-title"><?php echo $coupon->post_title; ?></span>
    </h3>
  <?php endif; ?>

  <?php $list_table->display(); ?>
</div>
