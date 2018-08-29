<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAccountHelper
{
  public static function active_nav($tab='home',$active_class='mepr-active-nav-tab')
  {
    $class = 'mepr-' . $tab;
    $action = '';

    if(isset($_REQUEST['action'])) { $action = $_REQUEST['action']; }

    if($tab == $action)
      $class = $class . ' ' . $active_class;

    echo MeprHooks::apply_filters('mepr-active-nav-tab', $class, $tab, $active_class);
  }

  public static function purchase_link($product, $name=null) {
    $name = is_null($name)?_x('Subscribe', 'ui', 'memberpress'):$name;

    ?>
    <a href="<?php echo $product->url(); ?>" class="mepr-account-row-action mepr-account-purchase"><?php echo $name; ?></a>
    <?php
  }

  public static function group_link($txn) {
    $product  = $txn->product();
    $user     = $txn->user();
    ?>
      <?php if(($grp = $product->group()) && $grp->is_upgrade_path && count($grp->products('ids')) > 1): //Can't upgrade to no other options ?>
        <div id="mepr-upgrade-txn-<?php echo $txn->id; ?>" class="mepr-white-popup mfp-hide">
          <center>
            <div class="mepr-upgrade-txn-text">
              <?php _e('Please select a new plan', 'memberpress'); ?>
            </div>
            <br/>
            <div>
              <select id="mepr-upgrade-dropdown-<?php echo $txn->id; ?>" class="mepr-upgrade-dropdown">
                <?php foreach($grp->products() as $p): ?>
                  <?php if($p->can_you_buy_me()): ?>
                    <option value="<?php echo $p->url(); ?>"><?php printf('%1$s (%2$s)', $p->post_title, MeprProductsHelper::product_terms($p, $user)); ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <br/>
            <div class="mepr-cancel-txn-buttons">
              <button class="mepr-btn mepr-upgrade-buy-now" data-id="<?php echo $txn->id; ?>"><?php _e('Select Plan', 'memberpress'); ?></button>
              <button class="mepr-btn mepr-upgrade-cancel"><?php _e('Cancel', 'memberpress'); ?></button>
            </div>
          </center>
        </div>

        <?php ob_start(); ?>
        <?php if(!$grp->disable_change_plan_popup): ?>
          <a href="#mepr-upgrade-txn-<?php echo $txn->id; ?>" class="mepr-open-upgrade-popup mepr-account-row-action mepr-account-upgrade"><?php _e('Change Plan', 'memberpress'); ?></a>
        <?php else: ?>
          <a href="<?php echo $grp->url(); ?>" class="mepr-account-row-action mepr-account-upgrade"><?php _e('Change Plan', 'memberpress'); ?></a>
        <?php endif; ?>
        <?php echo MeprHooks::apply_filters('mepr_custom_upgrade_link_txn', ob_get_clean(), $txn); ?>

      <?php endif; ?>
    <?php
  }
} //End class
