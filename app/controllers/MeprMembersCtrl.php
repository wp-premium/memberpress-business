<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprMembersCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // Screen Options
    $hook = 'memberpress_page_memberpress-members';
    add_action("load-{$hook}", array($this,'add_screen_options'));
    add_filter('set-screen-option', array($this,'setup_screen_options'), 10, 3);
    add_filter("manage_{$hook}_columns", array($this, 'get_columns'), 0);
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

    // Update listing meta
    add_action('mepr_txn_store', array($this, 'update_txn_meta'));
    add_action('mepr_txn_destroy', array($this, 'update_txn_meta'));
    add_action('mepr_event_store', array($this, 'update_event_meta'));
    add_action('mepr_event_destroy', array($this, 'update_event_meta'));
    add_action('user_register', array($this, 'update_member_meta'));
    add_action('profile_update', array($this, 'update_member_meta'));
    add_action('delete_user', array($this, 'delete_member_meta'));
    add_action('mepr_table_controls_search', array($this, 'table_search_box'));
    add_action('mepr_subscription_deleted', array($this, 'update_member_data_from_subscription'));
    add_action('mepr_subscription_status_cancelled', array($this, 'update_member_data_from_subscription'));
    add_action('mepr_subscription_status_suspended', array($this, 'update_member_data_from_subscription'));
    add_action('mepr-transaction-expired', array($this, 'update_txn_meta'));

    if(is_multisite()) {
      add_action('add_user_to_blog', array($this, 'update_member_meta'));
      add_action('remove_user_from_blog', array($this, 'delete_member_meta'));
    }

    // Export members
    add_action('wp_ajax_mepr_members', array($this, 'csv'));
    add_action('mepr_control_table_footer', array($this, 'export_footer_link'), 10, 3);

    // Keeping members up to date

    add_filter( 'cron_schedules', array($this,'intervals') );
    add_action( 'mepr_member_data_updater_worker', array($this,'updater') );

    if(!($member_data_timestamp = wp_next_scheduled('mepr_member_data_updater_worker'))) {
       wp_schedule_event( time(), 'mepr_member_data_updater_interval', 'mepr_member_data_updater_worker' );
    }
    //else {
    //  wp_unschedule_event($member_data_timestamp, 'mepr_member_data_updater_worker');
    //}
  }

  public function intervals( $schedules ) {
    $schedules['mepr_member_data_updater_interval'] = array(
      'interval' => MeprUtils::hours(6), // Run four times a day
      'display' => __('MemberPress Member Data Update Interval', 'memberpress'),
    );

    return $schedules;
  }

  public function updater() {
    MeprUtils::debug_log('Start Updating Missing Members');
    MeprUser::update_all_member_data(true,100);
    MeprUtils::debug_log('End Updating Missing Members');

    //MeprUtils::debug_log('Start Updating Existing Member Data');
    //MeprUser::update_existing_member_data(100);
    //MeprUtils::debug_log('End Updating Existing Member Data');
  }

  public function enqueue_scripts($hook) {
    $wp_scripts = new WP_Scripts();
    $ui = $wp_scripts->query('jquery-ui-core');
    $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

    if($hook == 'memberpress_page_memberpress-members' || $hook == 'memberpress_page_memberpress-new-member') {
      wp_register_script('mepr-table-controls-js', MEPR_JS_URL.'/table_controls.js', array('jquery'), MEPR_VERSION);
      wp_register_script('mepr-timepicker-js', MEPR_JS_URL.'/jquery-ui-timepicker-addon.js', array('jquery-ui-datepicker'));
      wp_register_script('mepr-date-picker-js', MEPR_JS_URL.'/date_picker.js', array('mepr-timepicker-js'), MEPR_VERSION);
      wp_register_script('mphelpers', MEPR_JS_URL.'/mphelpers.js', array('suggest'), MEPR_VERSION);
      wp_enqueue_script(
        'mepr-members-js',
        MEPR_JS_URL.'/admin_members.js',
        array('mepr-table-controls-js','jquery','mphelpers','mepr-date-picker-js','mepr-settings-table-js'),
        MEPR_VERSION
      );

      wp_register_style('mepr-jquery-ui-smoothness', $url);
      wp_register_style('jquery-ui-timepicker-addon', MEPR_CSS_URL.'/jquery-ui-timepicker-addon.css', array('mepr-jquery-ui-smoothness'));
      wp_enqueue_style('mepr-members-css', MEPR_CSS_URL.'/admin-members.css', array('mepr-settings-table-css','jquery-ui-timepicker-addon'), MEPR_VERSION);
    }
  }

  public function listing() {
    $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action']))?$_REQUEST['action']:false;
    if($action == 'new') {
      $this->new_member();
    }
    else if(MeprUtils::is_post_request() && $action == 'create') {
      $this->create_member();
    }
    else {
      $this->display_list();
    }
  }

  /* This is here to use wherever we want. */
  public function get_columns() {
    $cols = array(
      'col_id' => __('Id', 'memberpress'),
      //'col_photo' => __('Photo'),
      'col_username' => __('Username', 'memberpress'),
      'col_email' => __('Email', 'memberpress'),
      'col_status' => __('Status', 'memberpress'),
      'col_name' => __('Name', 'memberpress'),
      'col_sub_info' => __('Subscriptions', 'memberpress'),
      'col_txn_info' => __('Transactions', 'memberpress'),
      // 'col_info' => __('Info', 'memberpress'),
      // 'col_txn_count' => __('Transactions', 'memberpress'),
      //'col_expired_txn_count' => __('Expired Transactions'),
      //'col_active_txn_count' => __('Active Transactions'),
      //'col_sub_count' => __('Subscriptions', 'memberpress'),
      //'col_pending_sub_count' => __('Pending Subscriptions'),
      //'col_active_sub_count' => __('Enabled Subscriptions'),
      //'col_suspended_sub_count' => __('Paused Subscriptions'),
      //'col_cancelled_sub_count' => __('Stopped Subscriptions'),
      'col_memberships' => __('Memberships', 'memberpress'),
      'col_last_login_date' => __('Last Login', 'memberpress'),
      'col_login_count' => __('Logins', 'memberpress'),
      'col_total_spent' => __('Value', 'memberpress'),
      'col_registered' => __('Registered', 'memberpress')
    );

    return MeprHooks::apply_filters('mepr-admin-members-cols', $cols);
  }

  public function display_list($message='', $errors=array()) {
    $screen = get_current_screen();

    $list_table = new MeprMembersTable( $screen, $this->get_columns() );
    $list_table->prepare_items();

    MeprView::render('/admin/members/list', compact('message','list_table'));
  }

  public function new_member($member=null,$transaction=null,$errors='',$message='') {
    $mepr_options = MeprOptions::fetch();

    if(empty($member)) {
      $member = new MeprUser();
      $member->send_notification = true;
      $member->password = wp_generate_password(24);
    }

    if(empty($transaction)) {
      $transaction = new MeprTransaction();
      $transaction->status = MeprTransaction::$complete_str; // Default this to complete in this case
      $transaction->send_welcome = true;
    }

    MeprView::render('/admin/members/new_member', compact('mepr_options','member','transaction','errors','message'));
  }

  public function create_member() {
    check_admin_referer('mepr_create_member', 'mepr_members_nonce');

    $mepr_options = MeprOptions::fetch();
    $errors = $this->validate_new_member();
    $message = '';

    $member = new MeprUser();
    $member->load_from_array($_POST['member']);
    $member->send_notification = isset($_POST['member']['send_notification']);

    // Just here in case things fail so we can show the same password when the new_member page is re-displayed
    $member->password = $_POST['member']['user_pass'];

    $transaction = new MeprTransaction();
    $transaction->load_from_array($_POST['transaction']);
    $transaction->send_welcome = isset($_POST['transaction']['send_welcome']);
    $transaction->total = $_POST['transaction']['amount']; //Don't forget this, or the members page and emails will have $0.00 for amounts

    if(count($errors) <= 0) {
      try {
        $member->set_password($_POST['member']['user_pass']);
        $member->store();

        if($member->send_notification) {
          MeprUtils::wp_new_user_notification($member->ID, $member->password);
        }

        $transaction->user_id = $member->ID;
        $transaction->store();

        //Trigger the right events here yo
        MeprEvent::record('transaction-completed', $transaction);
        MeprEvent::record('non-recurring-transaction-completed', $transaction);

        //Run the signup hooks
        MeprHooks::do_action("mepr-non-recurring-signup", $transaction);
        MeprHooks::do_action('mepr-signup', $transaction);

        if($transaction->send_welcome) {
          MeprUtils::send_signup_notices($transaction, true);
        }
        else { //Trigger the event for this yo, as it's normally triggered in send_signup_notices
          MeprEvent::record('member-signup-completed', $member, (object)$transaction->rec); //have to use ->rec here for some reason
        }

        $message = __('Your new member was created successfully.', 'memberpress');

        return $this->display_list($message);
      }
      catch(Exception $e) {
        $errors[] = $e->getMessage();
      }
    }

    $this->new_member($member,$transaction,$errors,$message);
  }

  public function add_screen_options() {
    add_screen_option('layout_columns');

    $option = 'per_page';

    $args = array(
      'label' => __('Members', 'memberpress'),
      'default' => 10,
      'option' => 'mp_members_perpage'
    );

    add_screen_option( $option, $args );
  }

  public function setup_screen_options($status, $option, $value) {
    if('mp_members_perpage' === $option) {
      return $value;
    }

    return $status;
  }

  // This is purely for performance ... we don't want to do these queries during a listing
  public function update_txn_meta($txn) {
    $u = $txn->user();
    $u->update_member_data();
  }

  public function update_event_meta($evt) {
    if($evt->evt_id_type === MeprEvent::$users_str && $evt->event === MeprEvent::$login_event_str) {
      $u = $evt->get_data();
      $u->update_member_data();
    }
  }

  public function update_member_meta($user_id) {
    $u = new MeprUser($user_id);
    $u->update_member_data();
  }

  public function update_member_data_from_subscription($subscription) {
    $member = $subscription->user();
    $member->update_member_data();
  }

  public function delete_member_meta($user_id) {
    $u = new MeprUser($user_id);
    $u->delete_member_data();
  }

  public function validate_new_member() {
    $errors = array();
    $usr = new MeprUser();

    if( !isset($_POST['member']['user_login']) || empty($_POST['member']['user_login']) ) {
      $errors[] = __('The username field can\'t be blank.', 'memberpress');
    }

    if( username_exists($_POST['member']['user_login']) ) {
      $errors[] = __('This username is already taken.', 'memberpress');
    }

    if( !validate_username($_POST['member']['user_login']) ) {
      $errors[] = __('The username must be valid.', 'memberpress');
    }

    if( !isset($_POST['member']['user_email']) || empty($_POST['member']['user_email']) ) {
      $errors[] = __('The email field can\'t be blank.', 'memberpress');
    }

    if( email_exists($_POST['member']['user_email']) ) {
      $errors[] = __('This email is already being used by another user.', 'memberpress');
    }

    if( !is_email($_POST['member']['user_email']) ) {
      $errors[] = __('A valid email must be entered.', 'memberpress');
    }

    // Simple validation here
    if(!isset($_POST['transaction']['amount']) || empty($_POST['transaction']['amount'])) {
      $errors[] = __('The transaction amount must be set.', 'memberpress');
    }

    if(!is_numeric($_POST['transaction']['amount'])) {
      $errors[] = __('The transaction amount must be a number.', 'memberpress');
    }

    if(empty($_POST['transaction']['trans_num']) || preg_match('#[^a-zA-z0-9_\-]#', $_POST['transaction']['trans_num'])) {
      $errors[] = __('The Transaction Number is required, and must contain only letters, numbers, underscores and hyphens.', 'memberpress');
    }

    return $errors;
  }

  public function table_search_box() {
    if(isset($_REQUEST['page']) && $_REQUEST['page']=='memberpress-members') {
      $membership = (isset($_REQUEST['membership'])?$_REQUEST['membership']:false);
      $status = (isset($_REQUEST['status'])?$_REQUEST['status']:'all');
      $prds = MeprCptModel::all('MeprProduct');
      MeprView::render('/admin/members/search_box', compact('membership','status','prds'));
    }
  }

  public function csv() {
    check_ajax_referer('export_members', 'mepr_members_nonce');

    $filename = 'members-'.time();

    // Since we're running WP_List_Table headless we need to do this
    $GLOBALS['hook_suffix'] = false;

    $screen = get_current_screen();
    $tab = new MeprMembersTable( $screen, $this->get_columns() );

    if(isset($_REQUEST['all']) && !empty($_REQUEST['all'])) {
      $search  = isset($_REQUEST["search"]) && !empty($_REQUEST["search"]) ? esc_sql($_REQUEST["search"])  : '';
      $search_field = isset($_REQUEST["search"]) && !empty($_REQUEST["search-field"])  ? esc_sql($_REQUEST["search-field"])  : 'any';
      $search_field = isset($tab->db_search_cols[$search_field]) ? $tab->db_search_cols[$search_field] : 'any';

      $all = MeprUser::list_table(
        /* $order_by */       'u.user_login',
        /* $order */          'ASC',
        /* $paged */          '',
        /* $search */         $search,
        /* $search_field */   $search_field,
        /* $perpage */        '',
        /* $params */         $_REQUEST,
        /* $include_fields */ true
      );

      add_filter('mepr_process_csv_cell', array($this,'process_custom_field'), 10, 2);
      MeprUtils::render_csv($all['results'], $filename);
    }
    else {
      $tab->prepare_items();
      MeprUtils::render_csv( $tab->get_items(), $filename );
    }
  }

  public function process_custom_field($field, $label) {
    $mepr_options = MeprOptions::fetch();

    // Pull out our serialized custom field values
    if(is_serialized($field)) {
      $field_settings = $mepr_options->get_custom_field($label);

      if(empty($field_settings)) {
        return $field;
      }

      if($field_settings->field_type == 'multiselect') {
        $field = unserialize($field);
        return implode(',',$field);
      }
      else if($field_settings->field_type == 'checkboxes') {
        $field = unserialize($field);
        return implode(',',array_keys($field));
      }
    }

    return $field;
  }

  public function export_footer_link($action, $totalitems, $itemcount) {
    if($action=='mepr_members') {
      MeprAppHelper::export_table_link($action, 'export_members', 'mepr_members_nonce', $itemcount);
      ?> | <?php
      MeprAppHelper::export_table_link($action, 'export_members', 'mepr_members_nonce', $totalitems, true);
    }
  }

} //End clas
