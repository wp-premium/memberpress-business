<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprPowerPressCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_filter( 'powerpress_admin_capabilities', array( $this, 'powerpress_caps' ) );
  }

  public function powerpress_caps($caps) {
    $products = get_posts(array('posts_per_page'=>-1,'post_type'=>MeprProduct::$cpt));

    $caps['mp_active'] = __('MemberPress Active Member', 'memberpress');

    // Add Dynamic MemberPress capabilities into the mix
    foreach( $products as $product ) {
      $caps["mp_membership_authorized_{$product->ID}"] = sprintf(__('MemberPress: %s', 'memberpress'), $product->post_title);
    }

    return $caps;
  }

} //End class

