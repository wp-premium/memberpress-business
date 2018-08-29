<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTransactionsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('wp_ajax_mepr_edit_status',        array($this, 'edit_trans_status'));
    add_action('wp_ajax_mepr_delete_transaction', array($this, 'delete_transaction'));
    add_action('wp_ajax_mepr_refund_transaction', array($this, 'refund_transaction'));
    add_action('wp_ajax_mepr_resend_txn_email',   array($this, 'resend_txn_email'));
    add_action('wp_ajax_mepr_default_expiration', array($this, 'default_expiration'));
    add_action('admin_enqueue_scripts',           array($this, 'enqueue_scripts'));
    add_action('wp_ajax_mepr_transactions',       array($this, 'csv'));
    add_action('mepr_control_table_footer',       array($this, 'export_footer_link'), 10, 3);

    add_action('wp_ajax_mepr_refund_txn_and_cancel_sub', array($this, 'refund_txn_and_cancel_sub'));

    // Screen Options
    $hook = 'memberpress_page_memberpress-trans';
    add_action( "load-{$hook}", array($this,'add_screen_options') );
    add_filter( 'set-screen-option', array($this,'setup_screen_options'), 10, 3 );
    add_filter( "manage_{$hook}_columns", array($this, 'get_columns'), 0 );

    add_filter('cron_schedules', array($this,'intervals'));

    if(!defined('MEMBERPRESS_DISABLE_TXN_EXPIRE_EVENTS') || false == MEMBERPRESS_DISABLE_TXN_EXPIRE_EVENTS) {
      // Set a wp-cron
      add_action('mepr_send_txn_expire_events', array($this,'send_expired_txn_events'));

      if(!wp_next_scheduled('mepr_send_txn_expire_events')) {
        wp_schedule_event(time(), 'mepr_send_txn_expire_events_interval', 'mepr_send_txn_expire_events');
      }
    }
    else if(($timestamp = wp_next_scheduled('mepr_send_txn_expire_events'))) {
      wp_unschedule_event($timestamp, 'mepr_send_txn_expire_events');
    }

    add_action('mepr_table_controls_search', array($this, 'table_search_box'));

    //Capture complete and refunded events for offline gateway
    add_action('mepr-txn-store', 'MeprArtificialGateway::capture_txn_status_for_events');
  }

  public function intervals( $schedules ) {
    $schedules['mepr_send_txn_expire_events_interval'] = array(
      'interval' => MeprUtils::hours(1), // Every hour
      'display' => __('MemberPress Send Transaction Expire Events', 'memberpress')
    );

    return $schedules;
  }

  public static function unschedule_events() {
    $timestamp = wp_next_scheduled( 'mepr_send_txn_expire_events' );
    wp_unschedule_event( $timestamp, 'mepr_send_txn_expire_events' );
  }

  public function listing() {
    $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action']))?$_REQUEST['action']:false;
    if($action == 'new') {
      $this->new_trans();
    }
    else if($action == 'edit') {
      $this->edit_trans();
    }
    else {
      $this->display_list();
    }
  }

  public function new_trans($errors = array()) {
    $mepr_options = MeprOptions::fetch();
    $txn = new MeprTransaction();
    $user_login = $subscr_num = '';

    if(empty($errors) && MeprUtils::is_post_request()) {
      $this->create_trans($txn);
    }
    else {
      if(isset($_REQUEST['user']) && !empty($_REQUEST['user'])) {
        $user_login = stripslashes($_REQUEST['user']);
      }

      if(isset($_REQUEST['subscription']) && is_numeric($_REQUEST['subscription'])) {
        $sub = new MeprSubscription($_REQUEST['subscription']);
        $usr = $sub->user();
        $prd = $sub->product();
        $user_login = $usr->user_login;
        $subscr_num = $sub->subscr_id;
        $txn->product_id = $sub->product_id;
      }

      MeprView::render('/admin/transactions/new_trans', get_defined_vars());
    }
  }

  public function edit_trans() {
    $mepr_options = MeprOptions::fetch();
    $subscr_num = '';

    if(isset($_REQUEST['id'])) {
      $txn = new MeprTransaction($_REQUEST['id']);
      $usr = $txn->user();
      $user_login = $usr->user_login;

      if($sub = $txn->subscription()) {
        $subscr_num = $sub->subscr_id;
      }

      if(MeprUtils::is_post_request()) {
        $this->update_trans($txn);
      }
      else {
        MeprView::render('/admin/transactions/edit_trans', get_defined_vars());
      }
    }
    else {
      $this->new_trans();
    }
  }

  public function create_trans($txn) {
    check_admin_referer( 'mepr_create_or_update_transaction', 'mepr_transactions_nonce' );

    $mepr_options = MeprOptions::fetch();

    $errors = $this->validate_trans();

    $usr = new MeprUser();
    $usr->load_user_data_by_login($_POST['user_login']);
    $user_login = $usr->user_login;
    $subscr_num = '';

    $txn->trans_num  = (isset($_POST['trans_num']) && !empty($_POST['trans_num']))?sanitize_file_name(wp_unslash($_POST['trans_num'])):uniqid();
    $txn->user_id    = $usr->ID;
    $txn->product_id = sanitize_key($_POST['product_id']);
    // $txn->set_subtotal($_POST['amount']); //Don't do this, it doesn't work right on existing txns
    $txn->amount     = (float)$_POST['amount'];
    $txn->tax_amount = (float)$_POST['tax_amount'];
    $txn->total      = ((float)$_POST['amount'] + (float)$_POST['tax_amount']);
    $txn->tax_rate   = (float)$_POST['tax_rate'];
    $txn->status     = sanitize_text_field($_POST['status']);
    $txn->gateway    = sanitize_text_field($_POST['gateway']);

    if(isset($_POST['subscr_num']) && !empty($_POST['subscr_num'])) {
      if($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_num'])) {
        $txn->subscription_id = $sub->id;
        $subscr_num = $sub->subscr_id;
        $sub->store();
      }
    }

    if(isset($_POST['created_at']) && ($_POST['created_at'] == '' || is_null($_POST['created_at']))) {
      $txn->created_at = MeprUtils::ts_to_mysql_date(time()); // This crap is due to mysql craziness
    }
    else {
      $txn->created_at = MeprUtils::ts_to_mysql_date(strtotime($_POST['created_at']));
    }

    if(isset($_POST['expires_at']) && ($_POST['expires_at'] == '' || is_null($_POST['expires_at']))) {
      $txn->expires_at = MeprUtils::db_lifetime(); // This crap is due to mysql craziness
    }
    else {
      $txn->expires_at = MeprUtils::ts_to_mysql_date(strtotime($_POST['expires_at']), 'Y-m-d 23:59:59');
    }

    // Only save to the database if there aren't any errors
    if(empty($errors)) {
      $txn->store();

      if($txn->status==MeprTransaction::$complete_str) {
        MeprEvent::record('transaction-completed', $txn);

        // This is a recurring payment
        if(($sub = $txn->subscription()) && $sub->txn_count > 1) {
          MeprEvent::record('recurring-transaction-completed', $txn);
        }
        elseif(!$sub) {
          MeprEvent::record('non-recurring-transaction-completed', $txn);
        }
      }

      $message = __('A transaction was created successfully.', 'memberpress');

      $_REQUEST['action'] = 'edit';
      $txn = new MeprTransaction($txn->id); // refresh the txn obj to get all generated fields
      MeprView::render('/admin/transactions/edit_trans', get_defined_vars());
    }
    else {
      $this->new_trans($errors);
    }
  }

  public function update_trans($txn) {
    check_admin_referer( 'mepr_create_or_update_transaction', 'mepr_transactions_nonce' );

    $mepr_options = MeprOptions::fetch();

    $errors = $this->validate_trans();

    $usr = new MeprUser();
    $usr->load_user_data_by_login($_POST['user_login']);
    $user_login = $usr->user_login;
    $subscr_num = '';

    $txn->trans_num  = sanitize_file_name(wp_unslash($_POST['trans_num']));
    $txn->user_id    = $usr->ID;
    $txn->product_id = sanitize_key($_POST['product_id']);
    // $txn->set_subtotal($_POST['amount']); //Don't do this, it doesn't work right on existing txns
    $txn->amount     = (float)$_POST['amount'];
    $txn->tax_amount = (float)$_POST['tax_amount'];
    $txn->total      = ((float)$_POST['amount'] + (float)$_POST['tax_amount']);
    $txn->tax_rate   = (float)$_POST['tax_rate'];
    $txn->status     = sanitize_text_field($_POST['status']);
    $txn->gateway    = sanitize_text_field($_POST['gateway']);

    if(isset($_POST['subscr_num']) && !empty($_POST['subscr_num'])) {
      if($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_num'])) {
        $txn->subscription_id = $sub->id;
        $subscr_num = $sub->subscr_id;
        $sub->store();
      }
    }

    if(isset($_POST['created_at']) && ($_POST['created_at'] == '' || is_null($_POST['created_at']))) {
      $txn->created_at = MeprUtils::ts_to_mysql_date(time()); // This crap is due to mysql craziness
    }
    else {
      $txn->created_at = MeprUtils::ts_to_mysql_date(strtotime($_POST['created_at']));
    }

    if(isset($_POST['expires_at']) && ($_POST['expires_at'] == '' || is_null($_POST['expires_at']))) {
      $txn->expires_at = MeprUtils::db_lifetime(); // This crap is due to mysql craziness
    }
    else {
      $txn->expires_at = MeprUtils::ts_to_mysql_date(strtotime($_POST['expires_at']));
    }

    // Only save to the database if there aren't any errors
    if(empty($errors)) {
      $txn->store();
      $message = __("The transaction was successfully updated.", 'memberpress');
    }

    MeprView::render('/admin/transactions/edit_trans', get_defined_vars());
  }

  public function validate_trans() {
    $errors = array();
    $usr = new MeprUser();

    if(!isset($_POST['user_login']) || empty($_POST['user_login'])) {
      $errors[] = __("The username must be set.", 'memberpress');
    }
    elseif(is_email($_POST['user_login']) && !username_exists($_POST['user_login'])) {
      $usr->load_user_data_by_email($_POST['user_login']);

      if(!$usr->ID) {
        $errors[] = __("You must enter a valid username or email address", 'memberpress');
      }
      else { //For use downstream in create and update transaction methods
        $_POST['user_login'] = $usr->user_login;
      }
    }
    else {
      $usr->load_user_data_by_login($_POST['user_login']);

      if(!$usr->ID) {
        $errors[] = __("You must enter a valid username or email address", 'memberpress');
      }
    }

    // Simple validation here
    if(!isset($_POST['amount']) || empty($_POST['amount'])) {
      $errors[] = __("The amount must be set.", 'memberpress');
    }

    if(!is_numeric($_POST['amount'])) {
      $errors[] = __("The amount must be a number.", 'memberpress');
    }

    if(isset($_POST['subscr_num']) && !empty($_POST['subscr_num'])) {
      if($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_num'])) {
        if($sub->product_id != $_POST['product_id']) {
          $prd = new MeprProduct($_POST['product_id']);
          $sub_prd = $sub->product();
          $errors[] = sprintf( __( "This is not a subscription for membership '%s' but for '%s'" , 'memberpress'), $prd->post_title, $sub_prd->post_title );
        }

        /*
        ** $usr object is already set above
        ** $usr = new MeprUser();
        ** $usr->load_user_data_by_login($_POST['user_login']);
        */
        $sub_usr = $sub->user();

        if($usr->ID != $sub_usr->ID) {
          $errors[] = sprintf( __( "This is not a subscription for user '%s' but for '%s'" , 'memberpress'), $usr->user_login, $sub_usr->user_login );
        }

        /* don't enforce this for now
        **
        if($sub->gateway != $_POST['gateway']) {
          if( $sub->gateway == MeprTransaction::$free_gateway_str ||
              $sub->gateway == MeprTransaction::$manual_gateway_str ) {
            $sub_gateway = $sub->gateway;
          }
          else {
            $pm = $sub->payment_method();
            $sub_gateway = sprintf( __( '%s (%s)' ), $pm->label, $pm->name );
          }

          $errors[] = sprintf( __( "This subscription is using a different payment gateway: %s" ), $sub_gateway );
        }
        */
      }
      else {
        $errors[] = __("This subscription was not found.", 'memberpress');
      }
    }

    if(empty($_POST['trans_num']) || preg_match("#[^a-zA-z0-9_\-]#", $_POST['trans_num'])) {
      $errors[] = __("The Transaction Number is required, and must contain only letters, numbers, underscores and hyphens.", 'memberpress');
    }

    return $errors;
  }

  public function enqueue_scripts($hook) {
    $wp_scripts = new WP_Scripts();
    $ui = $wp_scripts->query('jquery-ui-core');
    $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

    if($hook == 'memberpress_page_memberpress-trans' || $hook == 'memberpress_page_memberpress-new-trans') {
      $l10n = array(
        'del_txn' => __('Deleting Transactions could cause the associated member to lose access to protected content. Are you sure you want to delete this Transaction?', 'memberpress'),
        'del_txn_error' => __('The Transaction could not be deleted. Please try again later.', 'memberpress'),
        'refund_txn' => __('This will refund the transaction at the gateway level. This action is not reversable. Are you sure you want to refund this Transaction?', 'memberpress'),
        'refund_txn_and_cancel_sub' => __('This will refund the transaction and cancel the subscription associated with this transaction at the gateway level. This action is not reversable. Are you sure you want to refund this Transaction and cancel it\'s Subscription?', 'memberpress'),
        'refunded_text' => __('refunded','memberpress'),
        'refund_txn_success' => __('Your transaction was successfully refunded.','memberpress'),
        'refund_txn_error' => __('The Transaction could not be refunded. Please issue the refund by logging into your gateway\'s virtual terminal','memberpress'),
        'refund_txn_and_cancel_sub_success' => __('Your transaction was refunded and subscription was cancelled successfully.','memberpress'),
        'refund_txn_and_cancel_sub_error' => __('The Transaction could not be refunded and/or Subscription could not be cancelled. Please issue the refund by logging into your gateway\'s virtual terminal','memberpress'),
        'delete_transaction_nonce' => wp_create_nonce('delete_transaction')
      );

      wp_register_style('mepr-jquery-ui-smoothness', $url);
      wp_register_style('jquery-ui-timepicker-addon', MEPR_CSS_URL.'/jquery-ui-timepicker-addon.css', array('mepr-jquery-ui-smoothness'));
      wp_enqueue_style('mepr-transactions-css', MEPR_CSS_URL.'/admin-transactions.css', array('jquery-ui-timepicker-addon'), MEPR_VERSION);

      wp_register_script('mepr-table-controls-js', MEPR_JS_URL.'/table_controls.js', array('jquery'), MEPR_VERSION);
      wp_register_script('mepr-timepicker-js', MEPR_JS_URL.'/jquery-ui-timepicker-addon.js', array('jquery-ui-datepicker'));
      wp_register_script('mepr-date-picker-js', MEPR_JS_URL.'/date_picker.js', array('mepr-timepicker-js'), MEPR_VERSION);
      wp_register_script('mphelpers', MEPR_JS_URL.'/mphelpers.js', array('suggest'), MEPR_VERSION);
      wp_enqueue_script(
        'mepr-transactions-js',
        MEPR_JS_URL.'/admin_transactions.js',
        array(
          'jquery',
          'mphelpers',
          'mepr-table-controls-js',
          'mepr-date-picker-js'
        ),
        MEPR_VERSION
      );
      wp_localize_script('mepr-transactions-js', 'MeprTxn', $l10n);
    }
  }

  public function edit_trans_status() {
    global $wpdb;

    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !isset($_POST['value']) || empty($_POST['value'])) {
      die(__('Save Failed', 'memberpress'));
    }

    $id = sanitize_key($_POST['id']);
    $value = sanitize_key($_POST['value']);
    $tdata = MeprTransaction::get_one($id, ARRAY_A);

    if(!empty($tdata)) {
      $txn = new MeprTransaction();
      $txn->load_data($tdata);
      $txn->status = esc_sql($value); //escape the input this way since $wpdb->escape() is depracated
      $txn->store();
      die($txn->status);
    }
    else {
      die(__('Save Failed', 'memberpress'));
    }
  }

  public function refund_transaction() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not refund transaction', 'memberpress'));
    }

    $txn = new MeprTransaction($_POST['id']);

    try {
      $txn->refund();
    }
    catch( Exception $e ) {
      die($e->getMessage());
    }

    die('true'); //don't localize this string
  }

  public function refund_txn_and_cancel_sub() {
    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not refund transaction', 'memberpress'));
    }

    $txn = new MeprTransaction($_POST['id']);

    try {
      $txn->refund();

      if(($sub = $txn->subscription())) {
        $sub->cancel();
      }
    }
    catch( Exception $e ) {
      die($e->getMessage());
    }

    die('true'); //don't localize this string
  }

  public function delete_transaction() {
    check_ajax_referer('delete_transaction','mepr_transactions_nonce');

    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not delete transaction', 'memberpress'));
    }

    $txn = new MeprTransaction($_POST['id']);
    $txn->destroy();

    die('true'); //don't localize this string
  }

  /* This is here to use wherever we want. */
  public function get_columns() {
    return array(
      'col_id' => __('Id', 'memberpress'),
      'col_trans_num' => __('Transaction', 'memberpress'),
      'col_subscr_id' => __('Subscription', 'memberpress'),
      'col_status' => __('Status', 'memberpress'),
      'col_product' => __('Membership', 'memberpress'),
      'col_net' => __('Net', 'memberpress'),
      'col_tax' => __('Tax', 'memberpress'),
      'col_total' => __('Total', 'memberpress'),
      'col_propername' => __('Name', 'memberpress'),
      'col_user_login' => __('User', 'memberpress'),
      'col_payment_system' => __('Gateway', 'memberpress'),
      'col_created_at' => __('Created On', 'memberpress'),
      'col_expires_at' => __('Expires On', 'memberpress')
    );
  }

  public function display_list() {
    $screen = get_current_screen();
    $list_table = new MeprTransactionsTable( $screen, $this->get_columns() );

    $list_table->prepare_items();

    MeprView::render('/admin/transactions/list', get_defined_vars());
  }

  public function resend_txn_email() {
    $mepr_options = MeprOptions::fetch();

    if(!MeprUtils::is_mepr_admin()) {
      die(__('You do not have access.', 'memberpress'));
    }

    if(!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
      die(__('Could not send email. Please try again later.', 'memberpress'));
    }

    $txn = new MeprTransaction($_POST['id']);
    $params = MeprTransactionsHelper::get_email_params($txn);
    $usr = $txn->user();

    try {
      $uemail = MeprEmailFactory::fetch('MeprUserReceiptEmail');
      $uemail->to = $usr->formatted_email();
      $uemail->send($params);
      die(__('Email sent', 'memberpress'));
    }
    catch( Exception $e ) {
      die(__('There was an issue sending the email', 'memberpress'));
    }
  }

  public function default_expiration() {
    if( isset($_REQUEST['product_id']) && isset($_REQUEST['created_at']) &&
        ($prd = MeprProduct::get_one($_REQUEST['product_id'])) &&
        // !$prd->is_one_time_payment() && //Breaking one-offs which have an expiration date
        ( preg_match('/\d\d\d\d-\d\d-\d\d/', $_REQUEST['created_at']) ||
          preg_match('/\d\d\d\d-\d\d-\d\d \d\d-\d\d-\d\d/', $_REQUEST['created_at']) ||
          empty($_REQUEST['created_at']) ) ) {
      $expires_at_ts = $prd->get_expires_at(strtotime($_REQUEST['created_at']));

      if(!is_null($expires_at_ts)) {
        echo date('Y-m-d H:i:s', (int)$expires_at_ts);
      }
    }

    die;
  }

  public function csv() {
    check_ajax_referer('export_transactions', 'mepr_transactions_nonce');

    $filename = 'transactions-'.time();

    // Since we're running WP_List_Table headless we need to do this
    $GLOBALS['hook_suffix'] = false;

    $screen = get_current_screen();
    $tab = new MeprTransactionsTable($screen,$this->get_columns());

    if(isset($_REQUEST['all']) && !empty($_REQUEST['all'])) {
      $search  = isset($_REQUEST["search"]) && !empty($_REQUEST["search"]) ? esc_sql($_REQUEST["search"])  : '';
      $search_field = isset($_REQUEST["search"]) && !empty($_REQUEST["search-field"])  ? esc_sql($_REQUEST["search-field"])  : 'any';
      $search_field = isset($tab->db_search_cols[$search_field]) ? $tab->db_search_cols[$search_field] : 'any';

      $all = MeprTransaction::list_table(
        /* $order_by */     'tr.created_at',
        /* $order */        'ASC',
        /* $paged */        '',
        /* $search */       $search,
        /* $search_field */ $search_field,
        /* $perpage */      '',
        /* $params */       $_REQUEST
      );

      MeprUtils::render_csv($all['results'], $filename);
    }
    else {
      $tab->prepare_items();
      MeprUtils::render_csv( $tab->get_items(), $filename );
    }
  }

  public function export_footer_link($action, $totalitems, $itemcount) {
    if($action=='mepr_transactions') {
      MeprAppHelper::export_table_link($action, 'export_transactions', 'mepr_transactions_nonce', $itemcount);
      ?> | <?php
      MeprAppHelper::export_table_link($action, 'export_transactions', 'mepr_transactions_nonce', $totalitems, true);
    }
  }

  public function add_screen_options() {
    add_screen_option( 'layout_columns' );

    $option = 'per_page';

    $args = array(
      'label' => __('Transactions', 'memberpress'),
      'default' => 10,
      'option' => 'mp_transactions_perpage'
    );

    add_screen_option( $option, $args );
  }

  public function setup_screen_options($status, $option, $value) {
    if ( 'mp_transactions_perpage' === $option ) { return $value; }
    return $status;
  }

  public function send_expired_txn_events() {
    $start_time = time();
    $max_time = MeprUtils::minutes(10);

    $res = MeprTransaction::get_expired_txns();

    foreach( $res as $row ) {
      $run_time = time() - $start_time;
      if( $run_time >= $max_time ) { return; }
      $txn = new MeprTransaction($row->id);
      MeprEvent::record('transaction-expired', $txn);

      if($txn->subscription()) {
        MeprEvent::record('recurring-transaction-expired', $txn);
      }
      else {
        MeprEvent::record('non-recurring-transaction-expired', $txn);
      }

      MeprHooks::do_action('mepr-txn-expired', $txn, $row->sub_status); // DEPRECATED
      MeprHooks::do_action('mepr-transaction-expired', $txn, $row->sub_status);
    }
  }

  public function table_search_box() {
    if(isset($_REQUEST['page']) && $_REQUEST['page']=='memberpress-trans') {
      $mepr_options = MeprOptions::fetch();

      $membership = (isset($_REQUEST['membership'])?$_REQUEST['membership']:false);
      $status = (isset($_REQUEST['status'])?$_REQUEST['status']:'all');
      $gateway = (isset($_REQUEST['gateway'])?$_REQUEST['gateway']:'all');

      $prds = MeprCptModel::all('MeprProduct');
      $gateways = $mepr_options->payment_methods();

      MeprView::render('/admin/transactions/search_box', compact('membership','status','prds','gateways','gateway'));
    }
  }
} //End class
