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
    $name = is_null($name)?_x('Buy', 'ui', 'memberpress'):$name;

    ?>
    <a href="<?php echo $product->url(); ?>" class="mepr-account-row-action mepr-account-purchase"><?php echo $name; ?></a>
    <?php
  }

  public static function group_link($product, $name=null) {
    $name = is_null($name)?_x('Upgrade', 'ui', 'memberpress'):$name;
    if( ($grp = $product->group()) and $grp->is_upgrade_path ) {
      ?>
      <a href="<?php echo $grp->url(); ?>" class="mepr-account-row-action mepr-account-upgrade"><?php echo $name; ?></a>
      <?php
    }
  }
} //End class
