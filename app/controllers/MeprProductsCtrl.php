<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprProductsCtrl extends MeprCptCtrl {
  public function load_hooks() {
    add_action('admin_enqueue_scripts', 'MeprProductsCtrl::enqueue_scripts');
    add_action('manage_pages_custom_column', 'MeprProductsCtrl::custom_columns', 10, 2);
    add_filter('manage_edit-'.MeprProduct::$cpt.'_columns', 'MeprProductsCtrl::columns');
    add_filter('manage_edit-'.MeprProduct::$cpt.'_sortable_columns', 'MeprProductsCtrl::sortable_columns');
    add_filter('template_include', 'MeprProductsCtrl::template_include');
    add_action('save_post', 'MeprProductsCtrl::save_postdata');
    add_filter('the_content', 'MeprProductsCtrl::display_registration_form', 10);
    add_action('init', 'MeprProduct::cleanup_db');
    add_action('before_delete_post', 'MeprProductsCtrl::nullify_records_on_delete');
    add_filter('login_redirect', 'MeprProductsCtrl::track_and_override_login_redirect_wp', 999999, 3);
    add_filter('mepr-process-login-redirect-url', 'MeprProductsCtrl::track_and_override_login_redirect_mepr', 10, 2);

    add_shortcode('mepr-product-link', 'MeprProductsCtrl::shortcode_product_link'); // DEPRECATED
    add_shortcode('mepr-product-registration-form', 'MeprProductsCtrl::shortcode_registration_form'); // DEPRECATED
    add_shortcode('mepr-product-purchased', 'MeprProductsCtrl::shortcode_if_product_was_purchased'); // DEPRECATED
    add_shortcode('mepr-product-access-url', 'MeprProductsCtrl::shortcode_access_url_link'); // DEPRECATED

    add_shortcode('mepr-membership-link', 'MeprProductsCtrl::shortcode_product_link');
    add_shortcode('mepr-membership-registration-form', 'MeprProductsCtrl::shortcode_registration_form');
    add_shortcode('mepr-membership-purchased', 'MeprProductsCtrl::shortcode_if_product_was_purchased');
    add_shortcode('mepr-membership-access-url', 'MeprProductsCtrl::shortcode_access_url_link');

    add_action('wp_ajax_mepr_get_product_price_str', 'MeprProductsCtrl::get_price_str_ajax');

    // Cleanup list view
    add_filter('views_edit-'.MeprProduct::$cpt, 'MeprAppCtrl::cleanup_list_view' );
  }

  public function register_post_type() {
    $mepr_options = MeprOptions::fetch();
    $this->cpt = (object)array(
      'slug' => MeprProduct::$cpt,
      'config' => array(
        'labels' => array(
          'name' => __('Memberships', 'memberpress'),
          'singular_name' => __('Membership', 'memberpress'),
          'add_new_item' => __('Add New Membership', 'memberpress'),
          'edit_item' => __('Edit Membership', 'memberpress'),
          'new_item' => __('New Membership', 'memberpress'),
          'view_item' => __('View Membership', 'memberpress'),
          'search_items' => __('Search Membership', 'memberpress'),
          'not_found' => __('No Membership found', 'memberpress'),
          'not_found_in_trash' => __('No Membership found in Trash', 'memberpress'),
          'parent_item_colon' => __('Parent Membership:', 'memberpress')
        ),
        'public' => true,
        'show_ui' => true, //MeprUpdateCtrl::is_activated(),
        'show_in_menu' => 'memberpress',
        'capability_type' => 'page',
        'hierarchical' => true,
        'register_meta_box_cb' => 'MeprProductsCtrl::add_meta_boxes',
        'rewrite' => array("slug" => $mepr_options->product_pages_slug, "with_front" => false),
        'supports' => array('title', 'editor', 'page-attributes', 'comments', 'thumbnail')
      )
    );
    register_post_type( $this->cpt->slug, $this->cpt->config );
  }

  public static function columns($columns) {
    $columns = array(
      "cb" => "<input type=\"checkbox\" />",
      "ID" => __("ID", 'memberpress'),
      "title" => __("Membership Title", 'memberpress'),
      "terms" => __("Terms", 'memberpress'),
      "url" => __('URL', 'memberpress')
    );
    return $columns;
  }

  public static function sortable_columns($columns) {
    $columns['ID'] = 'ID';
    return $columns;
  }

  public static function custom_columns($column, $post_id) {
    $mepr_options = MeprOptions::fetch();
    $product = new MeprProduct($post_id);

    if($product->ID !== null) {
      if("ID" == $column) {
        echo $product->ID;
      }
      elseif("terms" == $column) {
        echo MeprProductsHelper::format_currency($product, true, null, false); //$product, $show_symbol, $coupon_code, $show_prorated
      }
      elseif("url" == $column) {
        echo $product->url();
      }
    }
  }

  // Template selection
  public static function template_include($template) {
    global $post, $wp_query;

    if(!is_singular()) { return $template; }

    if(isset($post) && is_a($post, 'WP_Post') && $post->post_type == MeprProduct::$cpt) {
      $product = new MeprProduct($post->ID);
      $new_template = $product->get_page_template();
    }

    if(isset($new_template) && !empty($new_template)) { return $new_template; }

    return $template;
  }

  public static function add_meta_boxes() {
    global $post_id;

    $product = new MeprProduct($post_id);

    add_meta_box("memberpress-product-meta", __('Membership Terms', 'memberpress'), "MeprProductsCtrl::product_meta_box", MeprProduct::$cpt, "side", "high", array('product' => $product));

    add_meta_box("memberpress-custom-template", __('Custom Page Template', 'memberpress'), "MeprProductsCtrl::custom_page_template", MeprProduct::$cpt, "side", "default", array('product' => $product));

    add_meta_box("memberpress-product-options", __('Membership Options', 'memberpress'), "MeprProductsCtrl::product_options_meta_box", MeprProduct::$cpt, "normal", "high", array('product' => $product));

    MeprHooks::do_action('mepr-product-meta-boxes', $product); // DEPRECATED
    MeprHooks::do_action('mepr-membership-meta-boxes', $product);
  }

  public static function save_postdata($post_id) {
    $post = get_post($post_id);

    if(!wp_verify_nonce((isset($_POST[MeprProduct::$nonce_str]))?$_POST[MeprProduct::$nonce_str]:'', MeprProduct::$nonce_str.wp_salt())) {
      return $post_id; //Nonce prevents meta data from being wiped on move to trash
    }

    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return $post_id;
    }

    if(defined('DOING_AJAX')) {
      return;
    }

    if(!empty($post) && $post->post_type == MeprProduct::$cpt) {
      $product = new MeprProduct($post_id);

      extract($_POST);

      $product->price = (isset($_mepr_product_price))?MeprUtils::format_float(sanitize_text_field($_mepr_product_price)):$product->attrs['price'];
      $product->period = (isset($_mepr_product_period))?sanitize_text_field($_mepr_product_period):$product->attrs['period'];
      $product->period_type = (isset($_mepr_product_period_type))?sanitize_text_field($_mepr_product_period_type):$product->attrs['period_type'];
      $product->signup_button_text = (isset($_mepr_product_signup_button_text))?wp_kses_post($_mepr_product_signup_button_text):$product->attrs['signup_button_text'];
      $product->limit_cycles = isset($_mepr_product_limit_cycles);
      $product->limit_cycles_num = (isset($_mepr_product_limit_cycles_num))?sanitize_text_field($_mepr_product_limit_cycles_num):$product->attrs['limit_cycles_num'];
      $product->limit_cycles_action = (isset($_mepr_product_limit_cycles_action)?sanitize_text_field($_mepr_product_limit_cycles_action):$product->attrs['limit_cycles_action']);
      $product->trial = isset($_mepr_product_trial);
      $product->trial_days = (isset($_mepr_product_trial_days))?sanitize_text_field($_mepr_product_trial_days):$product->attrs['trial_days'];
      $product->trial_amount = (isset($_mepr_product_trial_amount))?sanitize_text_field($_mepr_product_trial_amount):$product->attrs['trial_amount'];
      $product->trial_once = isset($_mepr_product_trial_once);
      $product->who_can_purchase = self::get_who_can_purchase_array();
      $product->is_highlighted = isset($_mepr_product_is_highlighted);
      $product->pricing_title = (isset($_mepr_product_pricing_title))?wp_kses_post($_mepr_product_pricing_title):$product->attrs['pricing_title'];
      $product->pricing_show_price = isset($_mepr_product_pricing_show_price);
      $product->plan_code = isset($_mepr_plan_code) ? sanitize_user($_mepr_plan_code, true) : $product->attrs['plan_code'];

      $product->pricing_display = isset($_mepr_product_pricing_display) ? sanitize_text_field($_mepr_product_pricing_display) : $product->attrs['pricing_display'];

      $product->custom_price = isset($_mepr_product_custom_price) ? sanitize_text_field($_mepr_product_custom_price) : $product->attrs['custom_price'];

      $product->pricing_heading_txt = (isset($_mepr_product_pricing_heading_text))?wp_kses_post($_mepr_product_pricing_heading_text):$product->attrs['pricing_heading_text'];
      $product->pricing_footer_txt = (isset($_mepr_product_pricing_footer_text))?wp_kses_post($_mepr_product_pricing_footer_text):$product->attrs['pricing_footer_txt'];
      $product->pricing_button_txt = (isset($_mepr_product_pricing_button_text))?wp_kses_post($_mepr_product_pricing_button_text):$product->attrs['pricing_button_txt'];
      $product->pricing_button_position = (isset($_mepr_product_pricing_button_position))?sanitize_text_field($_mepr_product_pricing_button_position):$product->attrs['pricing_button_position'];
      $product->pricing_benefits = (isset($_mepr_product_pricing_benefits))?array_map('sanitize_text_field', $_mepr_product_pricing_benefits):$product->attrs['pricing_benefits'];
      $product->register_price_action = (isset($_mepr_register_price_action))?sanitize_text_field($_mepr_register_price_action):$product->attrs['register_price_action'];
      $product->register_price = (isset($_mepr_register_price))?sanitize_text_field($_mepr_register_price):$product->attrs['register_price'];
      $product->thank_you_page_enabled = isset($_mepr_thank_you_page_enabled);
      $product->thank_you_message = (isset($meprproductthankyoumessage) && !empty($meprproductthankyoumessage))?wp_kses_post(wp_unslash($meprproductthankyoumessage)):$product->attrs['thank_you_message'];
      $product->thank_you_page_type = (isset($_mepr_thank_you_page_type)?sanitize_text_field($_mepr_thank_you_page_type):$product->attrs['thank_you_page_type']);
      $product->thank_you_page_id = (isset($_mepr_product_thank_you_page_id) && is_numeric($_mepr_product_thank_you_page_id) && (int)$_mepr_product_thank_you_page_id > 0)?(int)$_mepr_product_thank_you_page_id:$product->attrs['thank_you_page_id'];

      /**
      * Sets thank_you_page_id to the id from the POST or Adds the new page.
      */
      if($product->thank_you_page_type == 'page' && isset($_mepr_product_thank_you_page_id)) {
        if(is_numeric($_mepr_product_thank_you_page_id) && (int)$_mepr_product_thank_you_page_id > 0) {
          $product->thank_you_page_id = (int)$_mepr_product_thank_you_page_id;
        } elseif(preg_match("#^__auto_page:(.*?)$#", $_mepr_product_thank_you_page_id, $matches)) {
          $product->thank_you_page_id = MeprAppHelper::auto_add_page($matches[1]);
        } else {
          $product->thank_you_page_id = $product->attrs['thank_you_page_id'];
        }
      }

      $product->simultaneous_subscriptions = isset($_mepr_allow_simultaneous_subscriptions);
      $product->use_custom_template = isset($_mepr_use_custom_template);
      $product->custom_template = isset($_mepr_custom_template)?sanitize_text_field($_mepr_custom_template):$product->attrs['custom_template'];
      $product->customize_payment_methods = isset($_mepr_customize_payment_methods);
      $product->customize_profile_fields = isset($_mepr_customize_profile_fields);
      $product->custom_profile_fields = array(); //We'll populate it below if we need to
      $product->custom_payment_methods = json_decode(sanitize_text_field(wp_unslash($_POST['mepr-product-payment-methods-json'])));
      $product->custom_login_urls_enabled = isset($_mepr_custom_login_urls_enabled);
      $product->expire_type = isset(${MeprProduct::$expire_type_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_type_str]) : $product->attrs['expire_type'];
      $product->expire_after = isset(${MeprProduct::$expire_after_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_after_str]) : $product->attrs['expire_after'];
      $product->expire_unit = isset(${MeprProduct::$expire_unit_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_unit_str]) : $product->attrs['expire_unit'];
      $product->expire_fixed = isset(${MeprProduct::$expire_fixed_str}) ? sanitize_text_field($_POST[MeprProduct::$expire_fixed_str]) : $product->attrs['expire_fixed'];
      $product->tax_exempt = isset($_POST[MeprProduct::$tax_exempt_str]);
      $product->allow_renewal = (($product->expire_type == 'delay' && isset($_POST[MeprProduct::$allow_renewal_str])) || ($product->expire_type == 'fixed' && isset($_POST[MeprProduct::$allow_renewal_str.'-fixed'])));
      $product->access_url = isset($_mepr_access_url)?sanitize_text_field(wp_unslash($_mepr_access_url)):$product->attrs['access_url'];
      $product->disable_address_fields = (isset($_mepr_disable_address_fields) && $product->price <= 0.00);
      $product->cannot_purchase_message = (!empty($meprcannotpurchasemessage))?wp_kses_post(wp_unslash($meprcannotpurchasemessage)):$product->cannot_purchase_message;

      // Notification Settings
      $emails = array();
      foreach( $_POST[MeprProduct::$emails_str] as $email => $vals ) {
        $emails[$email] = array( 'enabled' => isset( $vals['enabled'] ),
                                          'use_template' => isset( $vals['use_template'] ),
                                          'subject' => sanitize_text_field( wp_unslash( $vals['subject'] ) ),
                                          'body' => MeprUtils::maybe_wpautop( wp_kses_post( wp_unslash( $vals['body'] ) ) ) );
      }
      $product->emails = $emails;

      if($product->custom_login_urls_enabled)
        $product = self::set_custom_login_urls($product);

      //Setup the custom profile fields
      if($product->customize_profile_fields && isset($_POST['product-profile-fields'])) {
        $slugs = array();

        foreach($_POST['product-profile-fields'] as $key => $value)
          $slugs[] = sanitize_title_with_dashes($key);

        $product->custom_profile_fields = $slugs;
      }

      $product = self::validate_product($product);
      $product->store_meta(); // only storing metadata here

      //Some themes rely on this meta key to be set to use the custom template, and they don't use locate_template
      if($product->use_custom_template && !empty($product->custom_template))
        update_post_meta($product->ID, '_wp_page_template', $product->custom_template);
      else
        update_post_meta($product->ID, '_wp_page_template', '');

      MeprHooks::do_action('mepr-product-save-meta', $product); // DEPRECATED
      MeprHooks::do_action('mepr-membership-save-meta', $product);
    }
  }

  public static function set_custom_login_urls($product) {
    extract($_POST);

    $custom_login_urls = array();

    $product->custom_login_urls_default = (isset($_mepr_custom_login_urls_default) && !empty($_mepr_custom_login_urls_default))?stripslashes($_mepr_custom_login_urls_default):'';

    if(isset($_mepr_custom_login_urls) && !empty($_mepr_custom_login_urls)) {
      foreach($_mepr_custom_login_urls as $i => $url) {
        if(!empty($url)) {
          $custom_login_urls[] = (object)array('url' => stripslashes($url), 'count' => (int)$_mepr_custom_login_urls_count[$i]);
        }
      }
    }

    $product->custom_login_urls = $custom_login_urls;

    return $product;
  }

  public static function get_who_can_purchase_array() {
    $rows = array();

    if(empty($_POST[MeprProduct::$who_can_purchase_str.'-user_type'])) {
      return $rows;
    }

    $count = count($_POST[MeprProduct::$who_can_purchase_str.'-user_type']) - 1;

    for($i = 0; $i < $count; $i++) {
      $user_type = sanitize_text_field($_POST[MeprProduct::$who_can_purchase_str.'-user_type'][$i]);
      $product_id = sanitize_text_field($_POST[MeprProduct::$who_can_purchase_str.'-product_id'][$i]);
      $rows[] = (object)array('user_type' => $user_type, 'product_id' => $product_id);
    }

    return $rows;
  }

  public static function validate_product($product) {
    //Validate Periods
    if($product->period_type == 'weeks' && $product->period > 52) {
      $product->period = 52;
    }

    if($product->period_type == 'months' && $product->period > 12) {
      $product->period = 12;
    }

    if(!is_numeric($product->period) || $product->period <= 0 || empty($product->period)) {
      $product->period = 1;
    }

    if(!is_numeric($product->trial_days) || $product->trial_days <= 0 || empty($product->trial_days)) {
      $product->trial_days = 0;
    }

    if($product->trial_days > 365) {
      $product->trial_days = 365;
    }

    //Validate Prices
    if(!is_numeric($product->price) || $product->price < 0.00) {
      $product->price = 0.00;
    }

    if(!is_numeric($product->trial_amount) || $product->trial_amount < 0.00) {
      $product->trial_amount = 0.00;
    }

    //Disable trial && cycles limit if lifetime is set and set period to 1
    if($product->period_type == 'lifetime') {
      $product->limit_cycles = false;
      $product->trial = false;
      $product->period = 1;
    }

    //Cycles limit must be positive
    if(empty($product->limit_cycles_num) || !is_numeric($product->limit_cycles_num) || $product->limit_cycles_num <= 0) {
      $product->limit_cycles_num = 2;
    }

    //If price = 0.00 and period type is not lifetime, we need to disable cycles and trials
    if($product->price == 0.00 && $product->period_type != 'lifetime') {
      $product->limit_cycles = false;
      $product->trial = false;
    }

    //Handle delayed expirations on one-time payments
    if($product->period_type == 'lifetime' && $product->expire_type == 'delay') {
      if(!is_numeric($product->expire_after) || $product->expire_after < 0) {
        $product->expire_after = 1;
      }

      if(!in_array($product->expire_unit, array('days', 'weeks', 'months', 'years'))) {
        $product->expire_unit = 'days';
      }
    }

    //Handle fixed expirations on one-time payments
    if($product->period_type == 'lifetime' && $product->expire_type == 'fixed') {
      if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $product->expire_fixed, $datebit)) {
        if(!checkdate($datebit[2] , $datebit[3] , $datebit[1])) {
          $product->expire_type = 'none'; //an invalid date was set, so let's just make this a lifetime
        }
      } else {
        $product->expire_type = 'none'; //an invalid date was set, so let's just make this a lifetime
      }
    }

    return $product;
  }

  /**
  * Displays product terms for for meta boxe
  * Returns terms from gateway or admin product terms form
  * Don't use $post here, it is null on new membership - use args instead
  */
  public static function product_meta_box($post, $args) {
    $product = $args['args']['product'];
    $mepr_options = MeprOptions::fetch();
    $gateway_ids = array_keys($mepr_options->payment_methods());
    foreach ($gateway_ids as $gateway_id) {
      $gateway = $mepr_options->payment_method($gateway_id);
      if($gateway instanceof MeprBaseExclusiveRecurringGateway) {
        // Return terms from exclusive gateway
        return $gateway->display_plans_terms($product);
      }
    }

    // Render product terms form
    MeprView::render('/admin/products/form', get_defined_vars());
  }

  //Don't use $post here, it is null on new membership - use args instead
  public static function product_options_meta_box($post, $args) {
    $mepr_options = MeprOptions::fetch();
    $product = $args['args']['product'];

    MeprView::render('/admin/products/product_options_meta_box', get_defined_vars());
  }

  //Don't use $post here, it is null on new membership - use args instead
  public static function custom_page_template($post, $args) {
    $product = $args['args']['product'];

    MeprView::render('/admin/products/custom_page_template_form', get_defined_vars());
  }

  public static function display_registration_form($content, $manual = false) {
    global $user_ID;
    $mepr_options = MeprOptions::fetch();
    $current_post = MeprUtils::get_current_post();

    //This isn't a post? Just return the content then
    if($current_post === false) { return $content; }

    //WARNING the_content CAN be run more than once per page load
    //so this static var prevents stuff from happening twice
    //like cancelling a subscr or resuming etc...
    static $already_run = array();
    static $new_content = array();
    static $content_length = array();

    //Init this posts static values
    if(!isset($new_content[$current_post->ID]) || empty($new_content[$current_post->ID])) {
      $already_run[$current_post->ID] = false;
      $new_content[$current_post->ID] = '';
      $content_length[$current_post->ID] = -1;
    }

    if($already_run[$current_post->ID] && strlen($content) == $content_length[$current_post->ID] && !$manual) { //shortcode may pass
      return $new_content[$current_post->ID];
    }

    $content_length[$current_post->ID] = strlen($content);
    $already_run[$current_post->ID] = true;

    if(isset($current_post) && is_a($current_post, 'WP_Post') && $current_post->post_type == MeprProduct::$cpt) {
      if(post_password_required($current_post)) {
        //See notes above
        $new_content[$current_post->ID] = $content;
        return $new_content[$current_post->ID];
      }

      $prd = new MeprProduct($current_post->ID);

      //Short circuiting for any of the following reasons
      if( $prd->ID === null || //Bad membership for some reason
          (!$manual && $prd->manual_append_signup()) || //the_content filter and show manually is enabled
          ($manual && !$prd->manual_append_signup()) ) //do_shortcode and show manually is disabled
      {
        //See notes above
        $new_content[$current_post->ID] = $content;
        return $new_content[$current_post->ID];
      }

      // We want to render this form after processing the signup form unless
      // there were errors and when trying to process the paymet form
      if(isset($_REQUEST) and
          ((isset($_POST['mepr_process_signup_form']) and !isset($_POST['errors'])) or
            isset($_POST['mepr_process_payment_form']) or
            (isset($_GET['action']) and $_GET['action']==='checkout' and isset($_GET['txn']))))
      {
        ob_start();
        try {
          $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
          $checkout_ctrl->display_payment_form();
        }
        catch(Exception $e) {
          ?>
          <div class="mepr_error"><?php _e('There was a problem with our payment system. Please come back soon and try again.', 'memberpress'); ?></div>
          <?php
        }

        //See notes above
        $new_content[$current_post->ID] = ob_get_clean();
        return $new_content[$current_post->ID];
      }

      $res = self::get_registration_form($prd);
      if($res->enabled) {
        $content .= $res->content;
      }
      else {
        $content = $res->content;
      }
    }

    //See notes above
    $new_content[$current_post->ID] = $content;
    return $new_content[$current_post->ID];
  }

  public static function get_registration_form($prd) {
    global $user_ID;
    $mepr_options = MeprOptions::fetch();

    $product_access_str = '';
    if($user_ID && !$prd->simultaneous_subscriptions && !empty($prd->access_url) &&
       ($user = new MeprUser($user_ID)) && $user->is_already_subscribed_to($prd->ID)) {
      $product_access_str = MeprHooks::apply_filters('mepr_product_access_string', sprintf(
        __('%1$sYou have already subscribed to this item. %2$sClick here to access it%3$s', 'memberpress'),
        '<div class="mepr-product-access-url">',
        '<a href="'.stripslashes($prd->access_url).'">',
        '</a></div>'
      ), $prd);
    }

    ob_start();
    //If the user can't purchase this let's show a message
    if(!$prd->can_you_buy_me()) {
      $enabled = false;
      if(!empty($product_access_str)) {
        $cant_purchase_str = $product_access_str;
      }
      else {
        $cant_purchase_str = wpautop(do_shortcode($prd->cannot_purchase_message));
      }

      $cant_purchase_str = MeprHooks::apply_filters('mepr-product-cant-purchase-string', $cant_purchase_str, $prd); // DEPRECATED
      echo MeprHooks::apply_filters('mepr-membership-cant-purchase-string', $cant_purchase_str, $prd);
    }
    else if(isset($_GET['pmt']) &&
            isset($_GET['action']) &&
            ($pm = $mepr_options->payment_method($_GET['pmt'])) &&
            ($msgp = $pm->message_page($_GET['action']))) {
      $enabled = false;
      call_user_func(array($pm, $msgp));
    }
    else {
      $enabled = true;
      try {
        $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
        echo $product_access_str;
        $checkout_ctrl->display_signup_form($prd);
      }
      catch(Exception $e) {
        ?>
        <div class="mepr_error"><?php _e('There was a problem with our payment system. Please come back soon and try again.', 'memberpress'); ?></div>
        <?php
      }
    }

    $content = ob_get_clean();
    return (object)compact( 'enabled', 'content' );
  }

  public static function enqueue_scripts($hook) {
    global $current_screen;

    if($current_screen->post_type == MeprProduct::$cpt) {
      $wp_scripts = new WP_Scripts();
      $ui = $wp_scripts->query('jquery-ui-core');
      $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

      wp_register_style('mepr-jquery-ui-smoothness', $url);
      wp_register_style('jquery-ui-timepicker-addon', MEPR_CSS_URL.'/jquery-ui-timepicker-addon.css', array('mepr-jquery-ui-smoothness'));
      wp_enqueue_style('mepr-transactions-css', MEPR_CSS_URL.'/admin-transactions.css', array('jquery-ui-timepicker-addon'), MEPR_VERSION);
      wp_enqueue_style('mepr-emails-css', MEPR_CSS_URL.'/admin-emails.css', array(), MEPR_VERSION);
      wp_enqueue_style('mepr-products-css', MEPR_CSS_URL.'/admin-products.css', array('mepr-emails-css','mepr-settings-table-css','jquery-ui-timepicker-addon'), MEPR_VERSION);

      wp_dequeue_script('autosave'); //Disable auto-saving

      wp_register_script('mepr-timepicker-js', MEPR_JS_URL.'/jquery-ui-timepicker-addon.js', array('jquery-ui-datepicker'));
      wp_register_script('mepr-date-picker-js', MEPR_JS_URL.'/date_picker.js', array('mepr-timepicker-js'), MEPR_VERSION);
      wp_enqueue_script('mepr-products-js', MEPR_JS_URL.'/admin_products.js', array('jquery-ui-spinner','mepr-date-picker-js','jquery-ui-sortable','mepr-settings-table-js','mepr-admin-shared-js'), MEPR_VERSION);
      wp_enqueue_script('mepr-emails-js', MEPR_JS_URL.'/admin_emails.js', array('mepr-products-js'), MEPR_VERSION);

      //We need to hide the timepicker stuff here
      $date_picker_frontend = array('timeFormat' => '', 'showTime' => false);
      wp_localize_script('mepr-date-picker-js', 'MeprDatePicker', $date_picker_frontend);

      $options = array( 'removeBenefitStr' => __('Remove Benefit', 'memberpress'),
                        'register_price_action_id' => '#'.MeprProduct::$register_price_action_str,
                        'register_price_id' => '#'.MeprProduct::$register_price_str,
                        'wpnonce' => wp_create_nonce( MEPR_PLUGIN_SLUG ) );
      wp_localize_script('mepr-products-js', 'MeprProducts', $options);

      MeprHooks::do_action('mepr-product-admin-enqueue-script', $hook); // DEPRECATED
      MeprHooks::do_action('mepr-membership-admin-enqueue-script', $hook);
    }
  }

  public static function nullify_records_on_delete($id) {
    MeprTransaction::nullify_product_id_on_delete($id);
    MeprSubscription::nullify_product_id_on_delete($id);

    return $id;
  }

  public static function shortcode_product_link($atts, $content = '') {
    if(!isset($atts['id']) || !is_numeric($atts['id'])) { return $content; }

    $product = new MeprProduct($atts['id']);

    if($product->ID === null) { return $content; }

    return MeprProductsHelper::generate_product_link_html($product, $content);
  }

  public static function shortcode_registration_form($atts, $content = '') {
    if((isset($atts['product_id']) && ($prd = new MeprProduct($atts['product_id']))) ||
       (isset($atts['id']) && ($prd = new MeprProduct($atts['id'])))) {
      $res = self::get_registration_form($prd);
      return $res->content;
    }
    else {
      //No need to validate anything as the below function already
      //does all the validations. This is just a wrapper
      return self::display_registration_form('', true);
    }
  }

  public static function shortcode_if_product_was_purchased($atts, $content = '') {
    //Let's keep the protected string hidden if we have garbage input
    if( !isset($atts['id']) or
        !is_numeric($atts['id']) or
        !isset($_REQUEST['trans_num']) )
    { return ''; }

    $txn = new MeprTransaction();
    $data = MeprTransaction::get_one_by_trans_num($_REQUEST['trans_num']);
    $txn->load_data($data);

    if(!$txn->id or $txn->product_id != $atts['id']) { return ''; }

    return $content;
  }

  public static function maybe_get_thank_you_page_message() {
    if(!isset($_REQUEST['trans_num'])) { return ''; }

    $txn = new MeprTransaction();
    $data = MeprTransaction::get_one_by_trans_num($_REQUEST['trans_num']);
    $txn->load_data($data);

    if(!$txn->id || !$txn->product_id) { return ''; }

    $product = $txn->product();

    if($product->ID === null || !$product->thank_you_page_enabled || empty($product->thank_you_message)) {
      return '';
    }

    // Backwards compatibility check
    if(!empty($product->thank_you_page_type) && $product->thank_you_page_type != 'message') {
      return '';
    }

    $message = wpautop(stripslashes($product->thank_you_message));
    $message = do_shortcode($message);
    $message = MeprHooks::apply_filters('mepr_custom_thankyou_message', $message);

    MeprHooks::do_action('mepr-thank-you-page', $txn);

    return '<div id="mepr-thank-you-page-message">'.$message.'</div>';
  }

  //Just a wrapper for track_and_override_login_redirect_mepr()
  //this wrapper catches regular WP logins
  public static function track_and_override_login_redirect_wp($url, $request, $user) {
    return self::track_and_override_login_redirect_mepr($url, $user, true);
  }

  public static function track_and_override_login_redirect_mepr($url = '', $wp_user = false, $is_wp_login_page = false, $track = true) {
    static $exsubs = null;
    static $num_logins = null;

    $mepr_options = MeprOptions::fetch();

    if(empty($wp_user) || is_wp_error($wp_user)) { return $url; }

    $is_login_page = ((isset($_POST['mepr_is_login_page']) && $_POST['mepr_is_login_page'] == 'true') || $is_wp_login_page);

    //Track this login, then get the num total logins for this user
    $user = new MeprUser($wp_user->ID);

    if($track) { MeprEvent::record('login', $user); }

    // short circuit if user has expired subscriptions and is not an admin
    if(is_null($exsubs)) {
      $exsubs = $user->subscription_expirations('expired', true);
    }
    if(!empty($exsubs) && !$wp_user->has_cap('delete_users')) {
      return $mepr_options->account_page_url();
    }

    if(is_null($num_logins)) {
      $num_logins = $user->get_num_logins();
    }

    //Get users active memberships
    $products = $user->active_product_subscriptions('ids');

    //If no active memberships, send them to wherever they were already going.
    if(empty($products)) { return $url; }

    //Just grab the first membership the user is subscribed to
    $product = new MeprProduct(array_shift($products));

    if($product->custom_login_urls_enabled && (!empty($product->custom_login_urls_default) || !empty($product->custom_login_urls))) {
      if(!empty($product->custom_login_urls)) {
        foreach($product->custom_login_urls as $custom_url) {
          if(!empty($custom_url) && $custom_url->count == $num_logins) {
            return stripslashes($custom_url->url);
          }
        }
      }

      return (!empty($product->custom_login_urls_default) && $is_login_page)?$product->custom_login_urls_default:$url;
    }

    return $url;
  }

  //Get's the price string via ajax for the price box in the dashboard
  public static function get_price_str_ajax() {
    if(!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
      die(__('An unknown error has occurred', 'memberpress'));
    }

    $product = new MeprProduct($_POST['product_id']);

    if(!isset($product->ID) || (int)$product->ID <= 0) {
      die(__('Please save membership first to see the Price here.', 'memberpress'));
    }

    die(MeprAppHelper::format_price_string($product, $product->price));
  }

  public static function shortcode_access_url_link($atts = array(), $content = '') {
    if(!isset($atts['id']) || !is_numeric($atts['id'])) {
      return $content;
    }

    $product = new MeprProduct($atts['id']);

    if($product->ID === null || empty($product->access_url)) {
      return $content;
    }

    if(empty($content)) {
      $link_text = $product->post_title;
    }
    else {
      $link_text = $content;
    }

    return '<a href="'.$product->access_url.'">'.$link_text.'</a>';
  }
} //End class
