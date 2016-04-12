<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprSubscriptionsCtrl extends MeprCptCtrl
{
  public function load_hooks()
  {
    add_action('admin_enqueue_scripts',               array($this, 'enqueue_scripts'));
    add_action('wp_ajax_mepr_subscr_num_search',      array($this, 'subscr_num_search'));
    add_action('wp_ajax_mepr_subscr_edit_status',     array($this, 'edit_subscr_status'));
    add_action('wp_ajax_mepr_delete_subscription',    array($this, 'delete_subscription'));
    add_action('wp_ajax_mepr_suspend_subscription',   array($this, 'suspend_subscription'));
    add_action('wp_ajax_mepr_resume_subscription',    array($this, 'resume_subscription'));
    add_action('wp_ajax_mepr_cancel_subscription',    array($this, 'cancel_subscription'));
    add_action('wp_ajax_mepr_subscriptions',          array($this, 'csv'));
    add_action('wp_ajax_mepr_lifetime_subscriptions', array($this, 'lifetime_csv'));

    // Screen Options
    $hook = $this->get_hook();
    add_action( "load-{$hook}", array($this,'add_recurring_screen_options') );
    add_filter( "manage_{$hook}_columns", array($this, 'get_columns') );

    $hook = $this->get_hook(true);
    add_action( "load-{$hook}", array($this,'add_lifetime_screen_options') );
    add_filter( "manage_{$hook}_columns", array($this, 'get_lifetime_columns') );

    add_filter( 'set-screen-option', array($this,'setup_screen_options'), 10, 3 );
  }

  private function get_hook($lifetime=false) {
    if($lifetime) { return 'admin_page_memberpress-lifetimes'; }
    return 'memberpress_page_memberpress-subscriptions';
  }

  private function is_lifetime() {
    $screen = get_current_screen();

    if( isset($screen) && is_object($screen) ) {
      return ( $this->get_hook(true) === $screen->id );
    }
    else if( isset($_GET['page']) ) {
      return ( 'memberpress-lifetimes' === $_GET['page'] );
    }
    else
      return false;
  }

  public function register_post_type()
  {
    $this->cpt = (object)array(
                   'slug' => MeprSubscription::$cpt,
                   'config' => array(
                     'labels' => array(
                       'name' => __( 'Subscriptions' , 'memberpress'),
                       'singular_name' => __( 'Subscription' , 'memberpress'),
                       'add_new_item' => __('Add New Subscription', 'memberpress'),
                       'edit_item' => __('Edit Subscription', 'memberpress'),
                       'new_item' => __('New Subscription', 'memberpress'),
                       'view_item' => __('View Subscription', 'memberpress'),
                       'search_items' => __('Search Subscription', 'memberpress'),
                       'not_found' => __('No Subscription found', 'memberpress'),
                       'not_found_in_trash' => __('No Subscription found in Trash', 'memberpress'),
                       'parent_item_colon' => __('Parent Subscription:', 'memberpress')
                     ),
                     'public' => false,
                     'show_ui' => false,
                     'capability_type' => 'post',
                     'hierarchical' => true,
                     'supports' => array('none')
                   )
                 );
    register_post_type( $this->cpt->slug, $this->cpt->config );
  }

  public function listing()
  {
    $screen = get_current_screen();
    $lifetime = ( $screen->id === $this->get_hook(true) );
    $sub_table = new MeprSubscriptionsTable( $screen, $this->get_columns(), $lifetime );
    $sub_table->prepare_items();

    MeprView::render('/admin/subscriptions/list', get_defined_vars());
  }

  public function enqueue_scripts($hook)
  {
    if( $hook === $this->get_hook() || $hook === $this->get_hook(true) )
    {
      $l10n = array( 'del_sub' => __('A Subscription should be cancelled (at the Gateway or here) by you, or by the Member on their Account page before being removed. Deleting an Active Subscription can cause future recurring payments not to be tracked properly. Are you sure you want to delete this Subscription?', 'memberpress'),
                     'del_sub_error' => __('The Subscription could not be deleted. Please try again later.', 'memberpress'),
                     'cancel_sub' => __('This will cancel all future payments for this subscription. Are you sure you want to cancel this Subscription?', 'memberpress'),
                     'cancel_sub_error' => __('The Subscription could not be cancelled here. Please login to your gateway\'s virtual terminal to cancel it.', 'memberpress'),
                     'cancel_sub_success' => __('The Subscription was successfully cancelled.', 'memberpress'),
                     'cancelled_text' => __('Stopped', 'memberpress'),
                     'suspend_sub' => __("This will stop all payments for this subscription until the user logs into their account and resumes.\n\nAre you sure you want to pause this Subscription?", 'memberpress'),
                     'suspend_sub_error' => __('The Subscription could not be paused here. Please login to your gateway\'s virtual terminal to pause it.', 'memberpress'),
                     'suspend_sub_success' => __('The Subscription was successfully paused.', 'memberpress'),
                     'suspend_text' => __('Paused', 'memberpress'),
                     'resume_sub' => __("This will resume payments for this subscription.\n\nAre you sure you want to resume this Subscription?", 'memberpress'),
                     'resume_sub_error' => __('The Subscription could not be resumed here. Please login to your gateway\'s virtual terminal to resume it.', 'memberpress'),
                     'resume_sub_success' => __('The Subscription was successfully resumed.', 'memberpress'),
                     'resume_text' => __('Enabled', 'memberpress')
                   );

      wp_enqueue_style('mepr-subscriptions-css', MEPR_CSS_URL.'/admin-subscriptions.css', array(), MEPR_VERSION);
      wp_enqueue_script('mepr-subscriptions-js', MEPR_JS_URL.'/admin_subscriptions.js', array('jquery'), MEPR_VERSION);
      wp_enqueue_script('mepr-table-controls-js', MEPR_JS_URL.'/table_controls.js', array('jquery'), MEPR_VERSION);
      wp_localize_script('mepr-subscriptions-js', 'MeprSub', $l10n);
    }
  }

  public function edit_subscr_status()
  {
    if( !isset($_POST['id']) || empty($_POST['id']) ||
        !isset($_POST['value']) || empty($_POST['value']) )
      die(__('Save Failed', 'memberpress'));

    $id = $_POST['id'];
    $value = $_POST['value'];

    $sub = new MeprSubscription($id);
    if( empty($sub->ID) )
      die(__('Save Failed', 'memberpress'));

    $sub->status = $value;
    $sub->store();

    echo MeprAppHelper::human_readable_status( $value, 'subscription' );
    die();
  }

  public function delete_subscription() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not delete subscription', 'memberpress'));
    }

    $sub = new MeprSubscription($_POST['id']);
    $sub->destroy();

    die('true'); //don't localize this string
  }

  public function suspend_subscription() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not pause subscription', 'memberpress'));
    }

    $sub = new MeprSubscription($_POST['id']);
    $sub->suspend();

    die('true'); //don't localize this string
  }

  public function resume_subscription() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not resume subscription', 'memberpress'));
    }

    $sub = new MeprSubscription($_POST['id']);
    $sub->resume();

    die('true'); //don't localize this string
  }

  public function cancel_subscription() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not cancel subscription', 'memberpress'));
    }

    $sub = new MeprSubscription($_POST['id']);
    $sub->cancel();

    die('true'); //don't localize this string
  }

  public function subscr_num_search() {
    if(!MeprUtils::is_mepr_admin()) {
      die('-1');
    }

    $s = $_GET['q']; // is this slashed already?
    $s = trim($s);

    if(strlen($s) < 5) { die(); } // require 5 chars for matching

    $subs = MeprSubscription::search_by_subscr_id($s);

    MeprView::render('/admin/subscriptions/search', get_defined_vars());
    die();
  }

  public function csv($lifetime = false) {
    // Since we're running WP_List_Table headless we need to do this
    $GLOBALS['hook_suffix'] = false;

    $screen = get_current_screen();
    $subtab = new MeprSubscriptionsTable( $screen, $this->get_columns(), $lifetime );
    $subtab->prepare_items();
    $filename = ( $lifetime ? 'non-recurring-' : '' ) . 'subscriptions-'.time();

    MeprUtils::render_csv( $subtab->get_items(), $filename );
  }

  public function lifetime_csv() {
    $this->csv(true);
  }

  /* This is here to use wherever we want. */
  public function get_columns($lifetime=false) {
    $prefix = $lifetime ? 'col_txn_' : 'col_';
    $cols = array(
      $prefix.'id' => __('Id', 'memberpress'),
      $prefix.'subscr_id' => __('Subscr Num', 'memberpress'),
      $prefix.'active' => __('Active', 'memberpress'),
      $prefix.'status' => __('Auto Rebill', 'memberpress'),
      $prefix.'product' => __('Membership', 'memberpress'),
      $prefix.'product_meta' => __('Terms', 'memberpress'),
      $prefix.'propername' => __('Name', 'memberpress'),
      $prefix.'member' => __('User', 'memberpress'),
      $prefix.'gateway' => __('Pmt Method', 'memberpress'),
      $prefix.'txn_count' => __('Txns', 'memberpress'),
      $prefix.'ip_addr' => __('IP', 'memberpress'),
      $prefix.'created_at' => __('Created On', 'memberpress'),
      $prefix.'expires_at' => __('Expires On', 'memberpress')
    );

    if($lifetime) {
      unset($cols[$prefix.'status']);
      unset($cols[$prefix.'delete_sub']);
      unset($cols[$prefix.'txn_count']);
      $cols[$prefix.'subscr_id'] = __('Txn Num', 'memberpress');
      $cols[$prefix.'product_meta'] = __('Price', 'memberpress');
    }

    return MeprHooks::apply_filters('mepr-admin-subscriptions-cols', $cols, $prefix, $lifetime);
  }

  public function get_lifetime_columns() {
    return $this->get_columns(true);
  }

  public function add_screen_options($optname='mp_subs_perpage') {
    add_screen_option( 'layout_columns' );
    add_screen_option( 'per_page', array(
        'label' => __('Subscriptions', 'memberpress'),
        'default' => 10,
        'option' => $optname
      )
    );
  }

  public function add_recurring_screen_options() {
    $this->add_screen_options('mp_subs_perpage');
  }

  public function add_lifetime_screen_options() {
    $this->add_screen_options('mp_lifetime_subs_perpage');
  }

  public function setup_screen_options($status, $option, $value) {
    $optname = $this->is_lifetime() ? 'mp_lifetime_subs_perpage' : 'mp_subs_perpage';
    if ( $optname === $option ) { return $value; }
    return $status;
  }
} //End class

