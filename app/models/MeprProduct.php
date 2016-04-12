<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprProduct extends MeprCptModel implements MeprProductInterface {
  public static $price_str                      = '_mepr_product_price';
  public static $period_str                     = '_mepr_product_period';
  public static $period_type_str                = '_mepr_product_period_type';
  public static $signup_button_text_str         = '_mepr_product_signup_button_text';
  public static $limit_cycles_str               = '_mepr_product_limit_cycles';
  public static $limit_cycles_num_str           = '_mepr_product_limit_cycles_num';
  public static $limit_cycles_action_str        = '_mepr_product_limit_cycles_action';
  public static $trial_str                      = '_mepr_product_trial';
  public static $trial_days_str                 = '_mepr_product_trial_days';
  public static $trial_amount_str               = '_mepr_product_trial_amount';
  public static $group_id_str                   = '_mepr_group_id'; // Only one group at a time dude
  public static $group_order_str                = '_mepr_group_order'; // Position in group
  public static $is_highlighted_str             = '_mepr_product_is_highlighted';
  public static $who_can_purchase_str           = '_mepr_product_who_can_purchase';
  public static $pricing_title_str              = '_mepr_product_pricing_title';
  public static $pricing_show_price_str         = '_mepr_product_pricing_show_price';
  public static $pricing_heading_txt_str        = '_mepr_product_pricing_heading_text';
  public static $pricing_footer_txt_str         = '_mepr_product_pricing_footer_text';
  public static $pricing_button_txt_str         = '_mepr_product_pricing_button_text';
  public static $pricing_benefits_str           = '_mepr_product_pricing_benefits';
  public static $register_price_action_str      = '_mepr_register_price_action';
  public static $register_price_str             = '_mepr_register_price';
  public static $thank_you_page_enabled_str     = '_mepr_thank_you_page_enabled';
  public static $thank_you_message_str          = '_mepr_product_thank_you_message';
  public static $simultaneous_subscriptions_str = '_mepr_allow_simultaneous_subscriptions';
  public static $use_custom_template_str        = '_mepr_use_custom_template';
  public static $custom_template_str            = '_mepr_custom_template';
  public static $customize_payment_methods_str  = '_mepr_customize_payment_methods';
  public static $custom_payment_methods_str     = '_mepr_custom_payment_methods';
  public static $customize_profile_fields_str   = '_mepr_customize_profile_fields';
  public static $custom_profile_fields_str      = '_mepr_custom_profile_fields';
  public static $custom_login_urls_enabled_str  = '_mepr_custom_login_urls_enabled';
  public static $custom_login_urls_default_str  = '_mepr_custom_login_urls_default';
  public static $custom_login_urls_str          = '_mepr_custom_login_urls';
  public static $expire_type_str                = '_mepr_expire_type';
  public static $expire_after_str               = '_mepr_expire_after';
  public static $expire_unit_str                = '_mepr_expire_unit';
  public static $expire_fixed_str               = '_mepr_expire_fixed';
  public static $tax_exempt_str                 = '_mepr_tax_exempt';
  public static $allow_renewal_str              = '_mepr_allow_renewal';
  public static $access_url_str                 = '_mepr_access_url';
  public static $emails_str                     = '_mepr_emails';
  public static $disable_address_fields_str     = '_mepr_disable_address_fields'; //For free products mostly

  public static $nonce_str                      = 'mepr_products_nonce';
  public static $last_run_str                   = 'mepr_products_db_cleanup_last_run';

  public static $cpt                            = 'memberpressproduct';

  public $period_types, $limit_cycles_actions, $expire_units,
         $register_price_actions, $expire_types;

  public function __construct($id = null) {
    $this->load_cpt(
      $id, self::$cpt,
      array(
        'price' => 0.00,
        'period' => 1,
        'period_type' => 'lifetime', //Default to lifetime to simplify new membership form
        'signup_button_text' => __('Sign Up','memberpress'),
        'limit_cycles' => false,
        'limit_cycles_num' => 2,
        'limit_cycles_action' => 'expire',
        'trial' => false,
        'trial_days' => 0,
        'trial_amount' => 0.00,
        'group_id' => 0,
        'group_order' => 0,
        'is_highlighted' => false,
        //who_can_purchase should be an array of OBJECTS
        'who_can_purchase' => array(),
        'pricing_title' => '',
        'pricing_show_price' => true,
        'pricing_heading_txt' => '',
        'pricing_footer_txt' => '',
        'pricing_button_txt' => '',
        //Pricing benefits should be an array of strings
        'pricing_benefits' => array(),
        'register_price_action' => 'default',
        'register_price' => '',
        'thank_you_page_enabled' => false,
        'thank_you_message' => '',
        'custom_login_urls_enabled' => false,
        'custom_login_urls_default' => '',
        //An array of objects ->url and ->count
        'custom_login_urls' => array(),
        'expire_type' => 'none',
        'expire_after' => 1,
        'expire_unit' => 'days',
        'expire_fixed' => '',
        'tax_exempt' => false,
        'allow_renewal' => false,
        'access_url' => '',
        'emails' => array(),
        'disable_address_fields' => false, //Mostly for free products
        'simultaneous_subscriptions' => false,
        'use_custom_template' => false,
        'custom_template' => '',
        'customize_payment_methods' => false,
        'custom_payment_methods' => array(),
        'customize_profile_fields' => false,
        'custom_profile_fields' => array()
      )
    );

    $this->period_types = array('weeks','months','years','lifetime');
    $this->limit_cycles_actions = array('expire','lifetime');
    $this->register_price_actions = array('default', 'hidden', 'custom');
    $this->expire_types = array('none','delay','fixed');
    $this->expire_units = array('days','weeks','months','years');
  }

  public function validate() {
    $this->validate_is_currency($this->price, 0.00, null, 'price');
    $this->validate_is_numeric($this->period, 1, 12, 'period');
    $this->validate_is_in_array($this->period_type, $this->period_types, 'period_type');
    $this->validate_not_empty($this->signup_button_text, 'signup_button_text');

    $this->validate_is_bool($this->limit_cycles, 'limit_cycles');
    if($this->limit_cycles) {
      $this->validate_is_numeric($this->limit_cycles_num, 1, null, 'limit_cycles_num');
      $this->validate_is_in_array($this->limit_cycles_action, $this->limit_cycles_actions, 'limit_cycles_action');
    }

    $this->validate_is_bool($this->trial, 'trial');
    if($this->trial) {
      $this->validate_is_numeric($this->trial_days, 0.00, null, 'trial_days');
      $this->validate_is_currency($this->trial_amount, 0.00, null, 'trial_amount');
    }

    $this->validate_is_numeric($this->group_id, 0, null, 'group_id');
    $this->validate_is_numeric($this->group_order, 0, null, 'group_order');

    $this->validate_is_bool($this->is_highlighted, 'is_highlighted');
    $this->validate_is_array($this->who_can_purchase, 'who_can_purchase');
    $this->validate_is_bool($this->pricing_show_price, 'pricing_show_price');

    // No need to validate these at this time
    //'pricing_title' => '',
    //'pricing_heading_txt' => '',
    //'pricing_footer_txt' => '',
    //'pricing_button_txt' => '',
    //$this->validate_is_array($this->pricing_benefits, 'pricing_benefits');

    $this->validate_is_in_array($this->register_price_action, $this->register_price_actions, 'register_price_action');
    //if($this->register_price_action) { $this->validate_not_empty($this->register_price, 'register_price'); }
    $this->validate_is_bool($this->thank_you_page_enabled, 'thank_you_page_enabled');

    // No need to validate
    // 'thank_you_message' => '',

    $this->validate_is_bool($this->custom_login_urls_enabled, 'custom_login_urls_enabled');

    // No need to validate
    // 'custom_login_urls_default' => '',

    if($this->custom_login_urls_enabled) { $this->validate_is_array($this->custom_login_urls,'custom_login_urls'); }

    $this->validate_is_in_array($this->expire_type, $this->expire_types, 'expire_type');
    if($this->expire_type=='delay') {
      $this->validate_is_numeric($this->expire_after, 1, null, 'expire_after');
      $this->validate_is_in_array($this->expire_unit, $this->expire_units, 'expire_unit');
    }
    else if($this->expire_type=='fixed') {
      $this->validate_is_date($this->expire_fixed, 'expire_fixed');
    }

    $this->validate_is_bool($this->tax_exempt, 'tax_exempt');
    $this->validate_is_bool($this->allow_renewal, 'allow_renewal');

    if(!empty($this->access_url)) { $this->validate_is_url($this->access_url, 'access_url'); }

    $this->validate_is_array($this->emails, 'emails');

    $this->validate_is_bool($this->simultaneous_subscriptions, 'simultaneous_subscriptions');

    $this->validate_is_bool($this->use_custom_template, 'use_custom_template');
    if($this->use_custom_template) { $this->validate_not_empty($this->custom_template, 'custom_template'); }

    $this->validate_is_bool($this->customize_payment_methods, 'customize_payment_methods');
    if($this->customize_payment_methods) { $this->validate_is_array($this->custom_payment_methods, 'custom_payment_methods'); }

    $this->validate_is_bool($this->customize_profile_fields, 'customize_profile_fields');
    if($this->customize_profile_fields) { $this->validate_is_array($this->custom_profile_fields, 'custom_profile_fields'); }
  }

  public function store_meta() {
    $id = $this->ID;

    update_post_meta($id, self::$price_str, MeprUtils::format_float($this->price));
    update_post_meta($id, self::$period_str, $this->period);
    update_post_meta($id, self::$period_type_str, $this->period_type);
    update_post_meta($id, self::$signup_button_text_str, $this->signup_button_text);
    update_post_meta($id, self::$limit_cycles_str, $this->limit_cycles);
    update_post_meta($id, self::$limit_cycles_num_str, $this->limit_cycles_num);
    update_post_meta($id, self::$limit_cycles_action_str, $this->limit_cycles_action);
    update_post_meta($id, self::$trial_str, $this->trial);
    update_post_meta($id, self::$trial_days_str, $this->trial_days);
    update_post_meta($id, self::$trial_amount_str, $this->trial_amount);
    update_post_meta($id, self::$group_id_str, $this->group_id);
    update_post_meta($id, self::$group_order_str, $this->group_order);
    update_post_meta($id, self::$who_can_purchase_str, $this->who_can_purchase);
    update_post_meta($id, self::$is_highlighted_str, $this->is_highlighted);
    update_post_meta($id, self::$pricing_title_str, $this->pricing_title);
    update_post_meta($id, self::$pricing_show_price_str, $this->pricing_show_price);
    update_post_meta($id, self::$pricing_heading_txt_str, $this->pricing_heading_txt);
    update_post_meta($id, self::$pricing_footer_txt_str, $this->pricing_footer_txt);
    update_post_meta($id, self::$pricing_button_txt_str, $this->pricing_button_txt);
    update_post_meta($id, self::$pricing_benefits_str, $this->pricing_benefits);
    update_post_meta($id, self::$register_price_action_str, $this->register_price_action);
    update_post_meta($id, self::$register_price_str, $this->register_price);
    update_post_meta($id, self::$thank_you_page_enabled_str, $this->thank_you_page_enabled);
    update_post_meta($id, self::$thank_you_message_str, $this->thank_you_message);
    update_post_meta($id, self::$custom_login_urls_enabled_str, $this->custom_login_urls_enabled);
    update_post_meta($id, self::$custom_login_urls_default_str, $this->custom_login_urls_default);
    update_post_meta($id, self::$custom_login_urls_str, $this->custom_login_urls);
    update_post_meta($id, self::$expire_type_str, $this->expire_type);
    update_post_meta($id, self::$expire_after_str, $this->expire_after);
    update_post_meta($id, self::$expire_unit_str, $this->expire_unit);
    update_post_meta($id, self::$expire_fixed_str, $this->expire_fixed);
    update_post_meta($id, self::$tax_exempt_str, $this->tax_exempt);
    update_post_meta($id, self::$allow_renewal_str, $this->allow_renewal);
    update_post_meta($id, self::$access_url_str, $this->access_url);
    update_post_meta($id, self::$emails_str, $this->emails);
    update_post_meta($id, self::$disable_address_fields_str, $this->disable_address_fields); //mostly for free products
    update_post_meta($id, self::$simultaneous_subscriptions_str, $this->simultaneous_subscriptions);
    update_post_meta($id, self::$use_custom_template_str, $this->use_custom_template);
    update_post_meta($id, self::$custom_template_str, $this->custom_template);
    update_post_meta($id, self::$customize_payment_methods_str, $this->customize_payment_methods);
    update_post_meta($id, self::$customize_profile_fields_str, $this->customize_profile_fields);

    if($this->customize_payment_methods)
      update_post_meta($id, self::$custom_payment_methods_str, $this->custom_payment_methods);
    else
      delete_post_meta($id, self::$custom_payment_methods_str);

    if($this->customize_profile_fields)
      update_post_meta($id, self::$custom_profile_fields_str, $this->custom_profile_fields);
    else
      delete_post_meta($id, self::$custom_profile_fields_str);
  }

  public function is_prorated() {
    $mepr_options = MeprOptions::fetch();
    return($mepr_options->pro_rated_upgrades and $this->is_upgrade_or_downgrade());
  }

  public static function get_one($id) {
    $post = get_post($id);

    if(is_null($post))
      return false;
    else
      return new MeprProduct($post->ID);
  }

  /** This presents the price as a float, based on the information contained in
    * $this, the user_id and $coupon_code passed to it.
    *
    * If a user_id and a coupon code is present just adjust the price based on
    * the user first (if any) and then apply the coupon to the remaining price.
    *
    * Coupon code needs to be validated using MeprCoupon::is_valid_coupon_code()
    * before passing a code to this method
    */
  public function adjusted_price($coupon_code = null) {
    global $current_user;
    MeprUtils::get_currentuserinfo();

    $product_price = $this->price;
    $mepr_options = MeprOptions::fetch();

    if($this->is_one_time_payment() && $this->is_prorated()) {
      $grp = $this->group();
      $usr = new MeprUser($current_user->ID);

      if($old_sub = $usr->subscription_in_group($grp->ID)) {
        $lt = $old_sub->latest_txn();
        $r = MeprUtils::calculate_proration($lt->amount,
                                            $product_price,
                                            $old_sub->days_in_this_period(),
                                            'lifetime',
                                            $old_sub->days_till_expiration(),
                                            $old_sub);

        //Don't update this below if the latest payment was for 0.00
        if(MeprUtils::format_float($lt->amount) > 0.00) {
          $product_price = $r->proration;
        }
      }
      //Don't update this below if the latest payment was for 0.00
      elseif(($txn = $usr->lifetime_subscription_in_group($grp->ID)) && MeprUtils::format_float($txn->amount) > 0.00) {
        $r = MeprUtils::calculate_proration($txn->amount, $product_price);
        $product_price = $r->proration;
      }
    }

    //Note to future self, we do not want to validate the coupon
    //here as it causes major issues if the coupon has expired
    //or has reached its usage count max. See notes above this method.
    if(!empty($coupon_code)) {
      $coupon = MeprCoupon::get_one_from_code($coupon_code);

      if($coupon !== false)
        $product_price = $coupon->apply_discount($product_price);
    }

    return MeprUtils::format_float($product_price);
  }

  /** Gets the value for 'expires_at' for the given created_at time for this membership. */
  public function get_expires_at($created_at = null)
  {
    $mepr_options = MeprOptions::fetch();

    if(is_null($created_at)) { $created_at = time(); }

    $expires_at = $created_at;
    $period = $this->period;

    switch($this->period_type)
    {
      case 'days':
          $expires_at += MeprUtils::days($period) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'weeks':
          $expires_at += MeprUtils::weeks($period) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'months':
          $expires_at += MeprUtils::months($period, $created_at) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'years':
          $expires_at += MeprUtils::years($period, $created_at) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      default: // one-time payment
          if($this->expire_type=='delay') {
            if(MeprUtils::is_user_logged_in()) {
              $user = MeprUtils::get_currentuserinfo();

              //Handle renewals
              if($this->is_renewal() && ($last_txn = $this->get_last_active_txn($user->ID))) {
                $expires_at = $created_at = strtotime($last_txn->expires_at);
              }
            }

            switch($this->expire_unit) {
              case 'days':
                $expires_at += MeprUtils::days($this->expire_after);
                break;
              case 'weeks':
                $expires_at += MeprUtils::weeks($this->expire_after);
                break;
              case 'months':
                $expires_at += MeprUtils::months($this->expire_after, $created_at);
                break;
              case 'years':
                $expires_at += MeprUtils::years($this->expire_after, $created_at);
            }
          }
          else if($this->expire_type=='fixed') {
            $expires_at = strtotime( $this->expire_fixed );
          }
          else { // lifetime
            $expires_at = null;
          }
    }

    return $expires_at;
  }

  public static function get_pricing_page_product_ids()
  {
    global $wpdb;

    $q = "SELECT p.ID, p.menu_order
            FROM {$wpdb->postmeta} AS m INNER JOIN {$wpdb->posts} AS p
              ON p.ID = m.post_id
            WHERE m.meta_key = %s
              AND m.meta_value = 1
          ORDER BY p.menu_order, p.ID";

    return $wpdb->get_col($wpdb->prepare($q, self::$show_on_pricing_str));
  }

  public function is_one_time_payment() {
    return ($this->period_type == 'lifetime' || $this->price == 0.00);
  }

  public function is_renewal() {
    global $user_ID;

    if(MeprUtils::is_user_logged_in()) {
      $user = new MeprUser($user_ID);
    }

    return (MeprUtils::is_user_logged_in() &&
            $user->is_already_subscribed_to($this->ID) &&
            $this->expire_type=='delay' &&
            $this->allow_renewal);
  }

  public function can_you_buy_me() {
    global $user_ID;

    if(MeprUtils::is_user_logged_in()) {
      $user = new MeprUser($user_ID);
    }

    //Make sure user hasn't already subscribed to this membership first
    if(MeprUtils::is_user_logged_in() &&
       $user->is_already_subscribed_to($this->ID) &&
       !$this->simultaneous_subscriptions &&
       !$this->allow_renewal) {
      return false;
    }

    if(empty($this->who_can_purchase)) {
      return true; //No rules exist so everyone can purchase
    }

    foreach($this->who_can_purchase as $who) {
      if($who->user_type == 'disabled') {
        return false;
      }

      if($who->user_type == 'everyone') {
        return true;
      }

      if($who->user_type == 'guests' && !MeprUtils::is_user_logged_in()) {
        return true; //If not a logged in member they can purchase
      }

      if($who->user_type == 'members' && MeprUtils::is_user_logged_in()) {
        if($user->can_user_purchase($who)) {
          return true;
        }
      }
    }

    return false; //If we make it here, nothing applied so let's return false
  }

  public function get_last_active_txn($user_id) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = "SELECT tr.id AS id
            FROM {$mepr_db->transactions} AS tr
           WHERE tr.user_id=%d
             AND tr.product_id=%d
             AND tr.status=%s
             AND tr.expires_at > %s
           ORDER BY tr.created_at DESC
           LIMIT 1";

    $lq = "SELECT tr.id AS id
            FROM {$mepr_db->transactions} AS tr
           WHERE tr.user_id=%d
             AND tr.product_id=%d
             AND tr.status=%s
             AND tr.expires_at=%s
           ORDER BY tr.created_at DESC
           LIMIT 1";

    $q = $wpdb->prepare($q, $user_id, $this->ID, MeprTransaction::$complete_str, MeprUtils::mysql_now());
    $lq = $wpdb->prepare($lq, $user_id, $this->ID, MeprTransaction::$complete_str, MeprUtils::mysql_lifetime());

    if(($txn_id = $wpdb->get_var($lq))) { // Try for lifetimes
      return new MeprTransaction($txn_id);
    }
    else if(($txn_id = $wpdb->get_var($q))) { // Try for expiring
      return new MeprTransaction($txn_id);
    }
    else {
      // TODO: Maybe throw an exception here at some point
      return false;
    }
  }

  public function group()
  {
    if(!isset($this->group_id) or empty($this->group_id))
      return false;

    static $grp;

    if(!isset($grp) or !($grp instanceof MeprGroup) or $grp->ID != $this->group_id)
      $grp = new MeprGroup($this->group_id);

    return $grp;
  }

  public function group_url() {
    if( $grp = $this->group() and
        !$grp->pricing_page_disabled ) {
      return $grp->url();
    }
    else {
      return $this->url();
    }
  }

  // Determines if this is a membership upgrade
  public function is_upgrade()
  {
    return $this->is_upgrade_or_downgrade('upgrade');
  }

  // Determines if this is a membership downgrade
  public function is_downgrade() {
    return $this->is_upgrade_or_downgrade('downgrade');
  }

  // Determines if this is a membership upgrade for a certain user
  public function is_upgrade_for($user_id) {
    return $this->is_upgrade_or_downgrade_for($user_id, 'upgrade');
  }

  // Determines if this is a membership downgrade for a certain user
  public function is_downgrade_for($user_id) {
    return $this->is_upgrade_or_downgrade_for($user_id, 'downgrade');
  }

  public function is_upgrade_or_downgrade($type=false, $usr=false) {
    if($usr === false) {
      $usr = MeprUtils::get_currentuserinfo();
    }
    return ($usr && $this->is_upgrade_or_downgrade_for($usr->ID, $type)); // Must be an upgrade/downgrade for the user
  }

  // Determines if this is a membership upgrade
  public function is_upgrade_or_downgrade_for($user_id, $type = false) {
    $usr = new MeprUser($user_id);
    $grp = $this->group();

    // not part of a group ... not an upgrade
    if(!$grp) { return false; }

    // no upgrade path here ... not an upgrade
    if(!$grp->is_upgrade_path) { return false; }

    $prds = $usr->active_product_subscriptions('products', true);

    if(!empty($prds)) {
      foreach($prds as $p) {
        if( $g = $p->group() and $g instanceof MeprGroup and
            $g->ID == $grp->ID and $this->ID != $p->ID ) {
          if( $type===false )
            return true;
          else if( $type == 'upgrade' )
            return $this->group_order > $p->group_order;
          else if( $type == 'downgrade' )
            return $this->group_order < $p->group_order;
        }
      }
    }

    return false;
  }

  public static function cleanup_db()
  {
    global $wpdb;
    $date = time();
    $last_run = get_option(self::$last_run_str, 0); //Prevents all this code from executing on every page load

    if(($date - $last_run) > 86400) //Runs once at most once a day
    {
      $sq1 = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '".self::$cpt."' AND
                      post_status = 'auto-draft'";
      $q1 = "DELETE
                FROM {$wpdb->postmeta}
                WHERE post_id IN ({$sq1})";
      $q2 = "DELETE
                FROM {$wpdb->posts}
                WHERE post_type = '".self::$cpt."' AND
                      post_status = 'auto-draft'";

      $wpdb->query($q1);
      $wpdb->query($q2);
      update_option(self::$last_run_str, $date);
    }
  }

  public function get_page_template()
  {
    if($this->use_custom_template)
      return locate_template($this->custom_template);
    else
      return locate_template(self::template_search_path());
  }

  public static function template_search_path()
  {
    return array( 'page_memberpressproduct.php',
                  'single-memberpressproduct.php',
                  'page.php',
                  'custom_template.php',
                  'single.php',
                  'index.php' );
  }

  public function payment_methods() {
    $mepr_options = MeprOptions::fetch();

    $pms = $mepr_options->payment_methods();

    unset($pms['free']);
    unset($pms['manual']);

    $pmkeys = array_keys($pms);

    if( isset($this->custom_payment_methods) and
        !is_null($this->custom_payment_methods) and
        is_array($this->custom_payment_methods) ) {
      return array_intersect($this->custom_payment_methods, $pmkeys);
    }

    return $pmkeys;
  }

  public function edit_url($args = '') {
    if(isset($this->ID) && $this->post_type==self::$cpt) {
      return get_edit_post_link($this->ID);
    }
    else {
      return '';
    }
  }

  public function url($args = '', $modify_if_https = false) {
    if(isset($this->ID)) {
      $url = MeprUtils::get_permalink($this->ID).$args;

      if(MeprUtils::is_ssl() && $modify_if_https) {
        $url = preg_replace('!^http:!','https:',$url);
      }

      return $url;
    }
    else {
      return '';
    }
  }

  public function manual_append_signup() {
    return preg_match('~\[\s*mepr-(product|membership)-registration-form\s*\]~',$this->post_content);
  }

  public function custom_profile_fields() {
    $mepr_options = MeprOptions::fetch();
    $fields = array();

    if(!$this->customize_profile_fields)
      return $mepr_options->custom_fields;

    foreach($mepr_options->custom_fields as $row)
      if(in_array($row->field_key, $this->custom_profile_fields))
        $fields[] = $row;

    return $fields;
  }

  public static function is_product_page($post) {
    if( is_object($post) &&
        ( ( $post->post_type == MeprProduct::$cpt &&
            $prd = new MeprProduct($post->ID) ) ||
          ( preg_match( '~\[mepr-(product|membership)-registration-form\s+product_id=[\"\\\'](\d+)[\"\\\']~',
                        $post->post_content, $m ) &&
            isset($m[1]) &&
            $prd = new MeprProduct( $m[1] ) ) ) ) {
      return $prd;
    }

    return false;
  }
} //End class
