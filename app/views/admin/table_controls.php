<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
$search           = (isset($_REQUEST['search']) && !empty($_REQUEST['search']))?$_REQUEST['search']:'';
$perpage          = (isset($_REQUEST['perpage']) && !empty($_REQUEST['perpage']))?$_REQUEST['perpage']:10;
$prds             = get_posts(array('numberposts' => -1, 'post_type' => MeprProduct::$cpt, 'post_status' => 'publish'));

$prd_selected     = (isset($_GET['prd_id']) && $_GET['prd_id'] != 'all')?(int)$_GET['prd_id']:false;
$status_selected  = (isset($_GET['status']) && $_GET['status'] != 'all')?$_GET['status']:false;
?>

<p class="search-box">
  <?php //THE FOLLOWING memberpress-subscriptions table controls are hidden for now until Blair looks at why the queries kill over and die with them ?>
  <?php if(false): //strpos($_SERVER['REQUEST_URI'], 'memberpress-subscriptions')): //Recurring subscriptions controls ?>
    <label><?php _e('Filter Results', 'memberpress'); ?></label>

    <select name="mepr_prd_id" id="mepr_prd_id">
      <option value="all" <?php selected($prd_selected, false); ?>><?php _e('All Memberships', 'memberpress'); ?></option>
      <?php foreach($prds as $p): ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $prd_selected); ?>><?php echo $p->post_title; ?></option>
      <?php endforeach; ?>
    </select>

    <select name="mepr_status" id="mepr_status">
      <option value="all" <?php selected($status_selected, false); ?>><?php _e('All Statuses', 'memberpress'); ?></option>
      <option value="pending" <?php selected($status_selected, 'pending'); ?>><?php _e('Pending', 'memberpress'); ?></option>
      <option value="active" <?php selected($status_selected, 'active'); ?>><?php _e('Enabled', 'memberpress'); ?></option>
      <option value="suspended" <?php selected($status_selected, 'suspended'); ?>><?php _e('Paused', 'memberpress'); ?></option>
      <option value="cancelled" <?php selected($status_selected, 'cancelled'); ?>><?php _e('Cancelled', 'memberpress'); ?></option>
    </select>

    <input type="submit" id="mepr_fake_submit" name="mepr_fake_submit" value="<?php _e('Filter', 'memberpress'); ?>" class="button" />

    <?php _e('or', 'memberpress'); ?>
  <?php endif; ?>

  <?php //THE FOLLOWING memberpress-lifetimes table controls are hidden for now until Blair looks at why the queries kill over and die with them ?>
  <?php if(strpos($_SERVER['REQUEST_URI'], 'memberpress-trans')/* || strpos($_SERVER['REQUEST_URI'], 'memberpress-lifetimes')*/): //Lifetime and Transaction subscriptions controls ?>
    <label><?php _e('Filter Results', 'memberpress'); ?></label>

    <select name="mepr_prd_id" id="mepr_prd_id">
      <option value="all" <?php selected($prd_selected, false); ?>><?php _e('All Memberships', 'memberpress'); ?></option>
      <?php foreach($prds as $p): ?>
        <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $prd_selected); ?>><?php echo $p->post_title; ?></option>
      <?php endforeach; ?>
    </select>

    <?php if(strpos($_SERVER['REQUEST_URI'], 'memberpress-trans')): //this should only show on the Transactions page ?>
      <select name="mepr_status" id="mepr_status">
        <option value="all" <?php selected($status_selected, false); ?>><?php _e('All Statuses', 'memberpress'); ?></option>
        <option value="pending" <?php selected($status_selected, 'pending'); ?>><?php _e('Pending', 'memberpress'); ?></option>
        <option value="complete" <?php selected($status_selected, 'complete'); ?>><?php _e('Complete', 'memberpress'); ?></option>
        <option value="refunded" <?php selected($status_selected, 'refunded'); ?>><?php _e('Refunded', 'memberpress'); ?></option>
        <option value="failed" <?php selected($status_selected, 'failed'); ?>><?php _e('Failed', 'memberpress'); ?></option>
      </select>
    <?php endif; ?>

    <input type="submit" id="mepr_fake_submit" name="mepr_fake_submit" value="<?php _e('Filter', 'memberpress'); ?>" class="button" />

    <?php _e('or', 'memberpress'); ?>

  <?php endif; ?>

  <input id="cspf-table-search" value="<?php echo $search; ?>" data-value="<?php _e('Search ...', 'memberpress'); ?>" />
</p>
<div class="cspf-tablenav-spacer">&nbsp;</div>
