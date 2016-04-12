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
    $products = get_posts(array('numberposts' => -1, 'post_type' => MeprProduct::$cpt, 'post_status' => 'publish'));
    ?>
      <select name="<?php echo MeprGroup::$products_str; ?>[product][]" class="group_products_dropdown">
        <?php foreach($products as $p): ?>
          <option value="<?php echo $p->ID; ?>" <?php selected($p->ID, $chosen) ?>><?php echo $p->post_title; ?></option>
        <?php endforeach; ?>
      </select>
    <?php
  }

  public static function group_page_item($product, $group = null, $preview = false) {
    ob_start();
    if($group === null) { $group = new MeprGroup(); }

    $benefits = '<div class="mepr-price-box-benefits-list">';
    if(!empty($product->pricing_benefits)) {
      foreach($product->pricing_benefits as $b) {
        $benefits .= '<div class="mepr-price-box-benefits-item">' . $b . '</div>';
      }
    }
    $benefits .= '</div>';

    $active = $product->can_you_buy_me();
    $user = MeprUtils::get_currentuserinfo(); //If not logged in, $user will be false

    ?>
    <div id="mepr-price-box-<?php echo $product->ID; ?>" class="mepr-price-box <?php echo ($product->is_highlighted)?'highlighted':''; ?>">
      <div class="mepr-price-box-head">
        <div class="mepr-price-box-title"><?php echo $product->pricing_title; ?></div>
        <?php if($preview): ?>
          <div class="mepr-price-box-price"></div>
          <span class="mepr-price-box-price-loading"><img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>"/></span>
        <?php elseif($product->pricing_show_price): ?>
          <div class="mepr-price-box-price">
          <?php
            if(!isset($mepr_coupon_code)) { $mepr_coupon_code=null; }

            if($product->is_one_time_payment()) {
              if(empty($mepr_coupon_code) || !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $product->ID)) {
                echo MeprProductsHelper::format_currency($product);
              }
              else {
                echo MeprProductsHelper::format_currency($product, true, $mepr_coupon_code);
              }
            }
            else {
              // Setup to possibly do a proration without actually creating a subscription record
              $tmp_sub = new MeprSubscription();
              $tmp_sub->ID = 0;
              $tmp_sub->user_id = ($user === false)?0:$user->ID;
              $tmp_sub->load_product_vars($product, $mepr_coupon_code,true);
              $tmp_sub->maybe_prorate();

              echo MeprAppHelper::format_price_string($tmp_sub, $tmp_sub->price, true, $mepr_coupon_code);
            }
          ?>
          </div>
        <?php endif; ?>
        <?php if(!empty($product->pricing_heading_txt)): ?>
          <div class="mepr-price-box-heading"><?php echo $product->pricing_heading_txt; ?></div>
        <?php endif; ?>
      </div>
      <div class="mepr-price-box-benefits"><?php echo $benefits; ?></div>
      <div class="mepr-price-box-foot">
        <div class="mepr-price-box-footer"><?php echo $product->pricing_footer_txt; ?></div>
        <div class="mepr-price-box-button">
          <?php
            //All this logic is for showing a "VIEW" button instead of "Buy Now" if the member has already purchased it
            //and the membership access URL is set for that membership - and you can't buy the same membership more than once
            if( $user &&
                !$product->simultaneous_subscriptions &&
                $user->is_already_subscribed_to($product->ID) &&
                !empty($product->access_url) ):
          ?>
            <a <?php echo 'href="'.$product->access_url.'"'; ?> class="<?php echo self::price_box_button_classes($group, $product, true); ?>"><?php _e('View', 'memberpress'); ?></a>
          <?php else: ?>
            <a <?php echo $active ? 'href="'.$product->url().'"' : ''; ?> class="<?php echo self::price_box_button_classes($group, $product, $active); ?>"><?php echo $product->pricing_button_txt; ?></a>
          <?php endif; ?>
        </div>
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
} //End class

