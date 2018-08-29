<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprGroupsHelper {
  public static function get_existing_products_list($group) {
    $products = $group->products();
    if(!empty($products)) {
      foreach($products as $index => $prd):
        ?>
        <li class="product-item">
          <?php MeprGroupsHelper::get_products_dropdown($prd->ID); ?>
          <span class="remove-span">
            <a href="" class="remove-product-item" title="Remove Membership"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
          </span>
        </li>
        <?php
      endforeach;
    }
    else {
    ?>
      <li class="product-item">
        <?php MeprGroupsHelper::get_products_dropdown(); ?>
        <span class="remove-span">
          <a href="" class="remove-product-item" title="Remove Membership"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a>
        </span>
      </li>
    <?php
    }
  }

  public static function theme_dropdown($selected = null) {
    $themes = MeprGroup::group_themes();
    ?>
    <select name="<?php echo MeprGroup::$group_theme_str; ?>" class="group_theme_dropdown">
      <?php
      foreach($themes as $theme) {
        $css = basename($theme);
        $name = preg_replace('#\.css$#', '', $css);
        $name = ucwords(preg_replace('#_#', ' ', $name));
        ?>
        <option value="<?php echo $css; ?>" <?php selected($css, $selected) ?>><?php echo $name; ?></option>
        <?php
      }
      ?>
      <option value="custom" <?php selected('custom', $selected) ?>><?php _e('None / Custom', 'memberpress'); ?></option>
    </select>
    <?php
  }

  public static function get_products_dropdown($chosen = null) {
    $products = MeprCptModel::all('MeprProduct');
    ?>
      <select name="<?php echo MeprGroup::$products_str; ?>[product][]" class="group_products_dropdown">
        <?php foreach($products as $p): ?>
          <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $chosen) ?>><?php echo $p->post_title; ?></option>
        <?php endforeach; ?>
      </select>
    <?php
  }

  public static function get_product_fallback_dropdown($group) {
    $products = $group->products();
    $selected = $group->fallback_membership;
    ?>
      <select name="<?php echo MeprGroup::$fallback_membership_str; ?>" class="group_theme_dropdown">
        <option value="" <?php selected('', $selected) ?>><?php echo _e('Default', 'memberpress'); ?></option>
        <?php foreach($products as $p): ?>
          <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $selected) ?>><?php echo $p->post_title; ?></option>
        <?php endforeach; ?>
      </select>
    <?php
  }

  public static function group_page_item($product, $group = null, $preview = false) {
    ob_start();
    $benefits = '';

    if($group === null) { $group = new MeprGroup(); }

    if(!empty($product->pricing_benefits)) {
      $benefits = '<div class="mepr-price-box-benefits-list">';

      foreach($product->pricing_benefits as $index => $b) {
        $benefits .= '<div class="mepr-price-box-benefits-item">' . MeprHooks::apply_filters('mepr_price_box_benefit',$b,$index) . '</div>';
      }

      $benefits .= '</div>';
    }

    $user = MeprUtils::get_currentuserinfo(); //If not logged in, $user will be false
    $active = true; //Always true for now - that way users can click the button and see the custom "you don't have access" message now

    ?>
    <div id="mepr-price-box-<?php echo $product->ID; ?>" class="mepr-price-box <?php echo ($product->is_highlighted)?'highlighted':''; ?>">
      <div class="mepr-most-popular"><?php _e('Most Popular', 'memberpress'); ?></div>
      <div class="mepr-price-box-head">
        <div class="mepr-price-box-title"><?php echo $product->pricing_title; ?></div>
        <?php if($preview): ?>
          <div class="mepr-price-box-price"></div>
          <span class="mepr-price-box-price-loading"><img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>"/></span>
        <?php elseif($product->pricing_display!=='none'): ?>
          <div class="mepr-price-box-price">
          <?php
            if(!isset($mepr_coupon_code) || !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $product->ID)) {
              $mepr_coupon_code=null;
            }

            if($product->pricing_display == 'auto') {
              echo MeprProductsHelper::format_currency($product, true, $mepr_coupon_code, false);
            }
            else {
              echo $product->custom_price;
            }
          ?>
          </div>
        <?php endif; ?>
        <?php if(!empty($product->pricing_heading_txt)): ?>
          <div class="mepr-price-box-heading"><?php echo $product->pricing_heading_txt; ?></div>
        <?php endif; ?>
        <?php
          if(in_array($product->pricing_button_position, array('header','both'))) {
            echo self::price_box_button($user, $group, $product, $active);
          }
        ?>
      </div>
      <div class="mepr-price-box-benefits"><?php echo $benefits; ?></div>
      <div class="mepr-price-box-foot">
        <div class="mepr-price-box-footer"><?php echo $product->pricing_footer_txt; ?></div>
        <?php
          if(in_array($product->pricing_button_position, array('footer','both'))) {
            echo self::price_box_button($user, $group, $product, $active);
          }
        ?>
      </div>
    </div>
    <?php
    $output = ob_get_clean();
    echo MeprHooks::apply_filters('mepr-group-page-item-output', $output, $product, $group, $preview);
  }

  public static function price_box_button_classes( $grp, $prd, $active ) {
    $bc = '';

    if( $prd->is_highlighted ) {
      $bc .= $grp->page_button_highlighted_class;
    }
    else {
      $bc .= $grp->page_button_class;
    }

    if( !$active ) {
      $bc .= " mepr-disabled {$grp->page_button_disabled_class}";
    }

    return trim( $bc );
  }

  public static function price_box_button($user, $group, $product, $active) {
    ob_start();

    ?>
    <div class="mepr-price-box-button">
      <?php
        //All this logic is for showing a "VIEW" button instead of "Buy Now" if the member has already purchased it
        //and the membership access URL is set for that membership - and you can't buy the same membership more than once
        if( $user && !$product->simultaneous_subscriptions &&
            $user->is_already_subscribed_to($product->ID) &&
            !empty($product->access_url) ):
        ?>
          <a <?php echo 'href="'.$product->access_url.'"'; ?> class="<?php echo self::price_box_button_classes($group, $product, true); ?>"><?php _e('View', 'memberpress'); ?></a>
      <?php else: ?>
          <a <?php echo $active ? 'href="'.$product->url().'"' : ''; ?> class="<?php echo self::price_box_button_classes($group, $product, $active); ?>"><?php echo $product->pricing_button_txt; ?></a>
      <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
  }
} //End class
