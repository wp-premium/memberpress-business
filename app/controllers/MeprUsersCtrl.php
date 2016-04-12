<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUsersCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // Admin User Profile login meta box
    add_action('add_meta_boxes', 'MeprUsersCtrl::login_page_meta_box');
    add_action('save_post', 'MeprUsersCtrl::save_postdata');

    // Admin User Profile customizations
    add_action('admin_init', 'MeprUsersCtrl::maybe_redirect_member_from_admin');
    add_action('register_post', 'MeprUsersCtrl::maybe_disable_wp_registration_form', 10, 3);
    add_action('init', 'MeprUsersCtrl::maybe_disable_admin_bar', 3);
    add_action('wp_ajax_mepr_resend_welcome_email', 'MeprUsersCtrl::resend_welcome_email_callback');
    add_action('delete_user', 'MeprUsersCtrl::nullify_records_on_delete');
    add_action('admin_enqueue_scripts', 'MeprUsersCtrl::enqueue_scripts');

    //bbPress profiles apparently pull this in on the front-end, so let's stop that
    if(is_admin()) {
      // Profile fields show/save
      add_action('show_user_profile', 'MeprUsersCtrl::extra_profile_fields');
      add_action('edit_user_profile', 'MeprUsersCtrl::extra_profile_fields');
      add_action('personal_options_update', 'MeprUsersCtrl::save_extra_profile_fields');
      add_action('edit_user_profile_update', 'MeprUsersCtrl::save_extra_profile_fields');

      // Purely for showing the errors in the users profile when saving -- it doesn't prevent the saving
      add_action('user_profile_update_errors', 'MeprUsersCtrl::validate_extra_profile_fields', 10, 3);
    }

    // User page extra columns
    add_filter('manage_users_columns', 'MeprUsersCtrl::add_extra_user_columns');
    add_filter('manage_users_sortable_columns', 'MeprUsersCtrl::sortable_extra_user_columns');
    add_filter('manage_users_custom_column', 'MeprUsersCtrl::manage_extra_user_columns', 10, 3);
    add_action('pre_user_query', 'MeprUsersCtrl::extra_user_columns_query_override');

    add_action('wp_ajax_mepr_user_search', 'MeprUsersCtrl::user_search');

    //Shortcodes
    add_shortcode('mepr-list-subscriptions', 'MeprUsersCtrl::list_users_subscriptions');
  }

  public static function display_unauthorized_page() {
    if(MeprUtils::is_user_logged_in())
      MeprView::render('/shared/member_unauthorized', get_defined_vars());
    else
      MeprView::render('/shared/unauthorized', get_defined_vars());
  }

  public static function resend_welcome_email_callback() {
    $mepr_options = MeprOptions::fetch();

    if(wp_verify_nonce($_REQUEST['_mepr_nonce'], 'mepr-resend-welcome-email')) {
      if(MeprUtils::is_logged_in_and_an_admin()) {
        $usr = new MeprUser($_REQUEST['uid']);

        // Get the most recent transaction
        $txns = MeprTransaction::get_all_complete_by_user_id( $usr->ID,
                                                             'created_at DESC', /* $order_by='' */
                                                             '1', /* $limit='' */
                                                             false, /* $count=false */
                                                             false, /* $exclude_expired=false */
                                                             true /* $include_confirmations=false */ );

        if(count($txns) <= 0)
          die(__('This user hasn\'t purchased any memberships - so no email will be sent.', 'memberpress'));

        $txn = new MeprTransaction($txns[0]->id);
        $params = MeprTransactionsHelper::get_email_params($txn);
        $usr = $txn->user();

        try {
          $uemail = MeprEmailFactory::fetch('MeprUserWelcomeEmail');
          $uemail->to = $usr->formatted_email();
          $uemail->send($params);
          die(__('Message Sent', 'memberpress'));
        }
        catch( Exception $e ) {
          die(__('There was an issue sending the email', 'memberpress'));
        }
      }
      die(__('Why you creepin\'?', 'memberpress'));
    }
    die(__('Cannot resend message', 'memberpress'));
  }

  public static function nullify_records_on_delete($id) {
    MeprTransaction::nullify_user_id_on_delete($id);
    MeprSubscription::nullify_user_id_on_delete($id);

    return $id;
  }

  public static function email_users_with_expiring_transactions() {
    return MeprUser::email_users_with_expiring_transactions();
  }

  //public static function unschedule_email_users_with_expiring_transactions()
  //{
  //  if($t = wp_next_scheduled('mepr_schedule_renew_emails'))
  //    wp_unschedule_event($t, 'mepr_schedule_renew_emails');
  //}

  public static function enqueue_scripts($hook) {
    $wp_scripts = new WP_Scripts();
    $ui = $wp_scripts->query('jquery-ui-core');
    $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

    if($hook == 'user-edit.php' || $hook == 'profile.php') {
      wp_enqueue_style('mepr-jquery-ui-smoothness', $url);
      wp_enqueue_script('mepr-date-picker-js', MEPR_JS_URL.'/date_picker.js', array('jquery-ui-datepicker'), MEPR_VERSION);
      wp_enqueue_script('jquery-clippy', MEPR_JS_URL.'/jquery.clippy.js', array('jquery'));
      wp_enqueue_script('mp-i18n', MEPR_JS_URL.'/i18n.js', array('jquery'));
      wp_localize_script('mp-i18n', 'MeprI18n', array('states' => MeprUtils::states()));
      wp_enqueue_script('mp-edit-user', MEPR_JS_URL.'/admin_profile.js', array('jquery', 'jquery-clippy', 'suggest', 'mp-i18n'), MEPR_VERSION);
      wp_localize_script('mp-edit-user', 'clippy', array('url' => MEPR_JS_URL.'/clippy.swf'));
    }
  }

  public static function extra_profile_fields($wpuser) {
    $mepr_options = MeprOptions::fetch();
    $user = new MeprUser($wpuser->ID);

    MeprView::render("/admin/users/extra_profile_fields", get_defined_vars());
  }

  public static function save_extra_profile_fields($user_id, $validated=false, $product=false, $is_signup=false) {
    $mepr_options = MeprOptions::fetch();
    $errors = array();
    $user = new MeprUser($user_id);

    if(isset($_POST[MeprUser::$user_message_str])) {
      update_user_meta($user_id, MeprUser::$user_message_str, (string)$_POST[MeprUser::$user_message_str]);
    }

    //Get the right custom fields
    if(is_admin() && MeprUtils::is_mepr_admin()) { //An admin is editing the user's profile, so let's save all fields
      $custom_fields = $mepr_options->custom_fields;
    }
    elseif($product !== false) {
      if($product->customize_profile_fields) {
        $custom_fields = $product->custom_profile_fields();
      }
      else {
        $custom_fields = $mepr_options->custom_fields;
      }
    }
    else {
      $custom_fields = $user->custom_profile_fields();
    }

    //Since we use user_* for these, we need to artifically set the $_POST keys correctly for this to work
    if(!isset($_POST['first_name']) || empty($_POST['first_name'])) {
      $_POST['first_name'] = (isset($_POST['user_first_name']))?stripslashes($_POST['user_first_name']):'';
    }

    if(!isset($_POST['last_name']) || empty($_POST['last_name'])) {
      $_POST['last_name'] = (isset($_POST['user_last_name']))?stripslashes($_POST['user_last_name']):'';
    }

    $custom_fields[] = (object)array('field_key' => 'first_name', 'field_type' => 'text');
    $custom_fields[] = (object)array('field_key' => 'last_name', 'field_type' => 'text');

    if($mepr_options->show_address_fields) {
      $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
    }

    // Even though the validate_extra_profile_fields function will show an error on the
    // dashboard profile. It doesn't prevent the profile from saved because
    // user_profile_update_errors is called after the account has been saved which is really lame
    // So let's take care of that here. $validated should ALWAYS be true, except in this one case
    if(!$validated) { $errors = self::validate_extra_profile_fields(); }

    if(empty($errors)) {
      // TODO: move this somewhere it makes more sense
      if(isset($_POST['mepr-geo-country'])) {
        update_user_meta($user_id, 'mepr-geo-country', $_POST['mepr-geo-country']);
      }

      foreach($custom_fields as $line) {
        //Don't do anything if this field isn't shown during signup, and this is a signup
        if($is_signup && isset($line->show_on_signup) && !$line->show_on_signup) { continue; }

        if(isset($_POST[$line->field_key]) && !empty($_POST[$line->field_key])) {
          update_user_meta($user_id, $line->field_key, $_POST[$line->field_key]);
        }
        else {
          if($line->field_type === 'checkbox') {
            update_user_meta($user_id, $line->field_key, false);
          }
          elseif(in_array($line->field_type, array('checkboxes', 'multiselect'))) {
            update_user_meta($user_id, $line->field_key, array());
          }
          else {
            update_user_meta($user_id, $line->field_key, '');
          }
        }
      }

      if(!$is_signup) {
        MeprEvent::record('member-account-updated', $user);
      }

      MeprHooks::do_action('mepr_user_account_saved', $user);

      return true;
    }

    return false;
  }

  //Should be moved to the Model eventually
  //This should be run before MeprUsersCtrl::save_extra_profile_fields is run
  public static function validate_extra_profile_fields( $errors = null,
                                                        $update = null,
                                                        $user  = null,
                                                        $is_signup = false,
                                                        $product = false ) {
    $mepr_options = MeprOptions::fetch();
    $errs = array();

    // Prevent checking when adding a new user via WP's New User system
    // or if an admin is editing the profile in the dashboard
    if($update === false || ($update !== false && MeprUtils::is_mepr_admin())) { return $errs; }

    //Get the right custom fields
    if($is_signup && $product !== false) {
      if($product->customize_profile_fields) {
        $custom_fields = $product->custom_profile_fields();
      }
      else {
        $custom_fields = $mepr_options->custom_fields;
      }
    }
    elseif(!is_null($user)) {
      $mepr_user = new MeprUser($user->ID);
      $custom_fields = $mepr_user->custom_profile_fields();
    }
    else {
      $custom_fields = $mepr_options->custom_fields;
    }

    if($mepr_options->show_address_fields && (!$product || !$product->disable_address_fields)) {
      $custom_fields = array_merge($mepr_options->address_fields, $custom_fields);
    }

    foreach($custom_fields as $line) {
      // If we're processing a signup and the custom field is not set
      // to show on signup we need to make sure it isn't required
      if($is_signup && $line->required && !$line->show_on_signup) {
        $line->required = false;
      }

      if((!isset($_POST[$line->field_key]) || empty($_POST[$line->field_key])) && $line->required) {
        $errs[] = sprintf(__('%s is required.', 'memberpress'), stripslashes($line->field_name));

        //This allows us to run this on dashboard profile fields as well as front end
        if(is_object($errors)) {
          $errors->add($line->field_key, sprintf(__('%s is required.', 'memberpress'), stripslashes($line->field_name)));
        }
      }
    }

    return $errs;
  }

  public static function user_search() {
    if(!MeprUtils::is_mepr_admin()) {
      die('-1');
    }

    $s = $_GET['q']; // is this slashed already?
    $s = trim($s);

    if(strlen($s) < 2) {
      die; // require 2 chars for matching
    }

    $users = get_users(array('search' => "*$s*"));

    MeprView::render('/admin/users/search', get_defined_vars());
    die();
  }

  //Add extra columns to the Users list table
  public static function add_extra_user_columns($columns) {
    $columns['mepr_products'] = __('Active Memberships', 'memberpress');
    $columns['mepr_registered'] = __('Registered', 'memberpress');
    $columns['mepr_last_login'] = __('Last Login', 'memberpress');
    $columns['mepr_num_logins'] = __('# Logins', 'memberpress');

    return $columns;
  }

  //Tells WP which columns should be sortable
  public static function sortable_extra_user_columns($cols) {
    $cols['mepr_registered'] = 'user_registered';
    $cols['mepr_last_login'] = 'last_login';
    $cols['mepr_num_logins'] = 'num_logins';

    return $cols;
  }

  //This allows us to sort the column properly behind the scenes
  public static function extra_user_columns_query_override($query) {
    global $wpdb;
    $vars = $query->query_vars;
    $mepr_db = new MeprDb();

    if(isset($vars['orderby']) && $vars['orderby'] == 'last_login') {
      $query->query_fields .= ", (SELECT e.created_at FROM {$mepr_db->events} AS e WHERE {$wpdb->users}.ID = e.evt_id AND e.evt_id_type='" . MeprEvent::$users_str . "' AND e.event = '" . MeprEvent::$login_event_str . "' ORDER BY e.created_at DESC LIMIT 1) AS last_login";
      $query->query_orderby = "ORDER BY last_login {$vars['order']}";
    }

    if(isset($vars['orderby']) && $vars['orderby'] == 'num_logins') {
      $query->query_fields .= ", (SELECT count(*) FROM {$mepr_db->events} AS e WHERE {$wpdb->users}.ID = e.evt_id AND e.evt_id_type='" . MeprEvent::$users_str . "' AND e.event = '" . MeprEvent::$login_event_str . "') AS num_logins";
      $query->query_orderby = "ORDER BY num_logins {$vars['order']}";
    }
  }

  //This actually shows the content in the table HTML output
  public static function manage_extra_user_columns($value, $column_name, $user_id) {
    $user = new MeprUser($user_id);

    if($column_name == 'mepr_registered') {
      $registered = strtotime($user->user_registered);
      return date_i18n('M j, Y', $registered) . '<br/>' . date_i18n('g:i A', $registered);
    }

    if($column_name == 'mepr_products') {
      $titles = $user->get_active_subscription_titles("<br/>");

      if(!empty($titles)) {
        return $titles;
      }
      else {
        return __('None', 'memberpress');
      }
    }

    if($column_name == 'mepr_last_login') {
      $login = $user->get_last_login_data();

      if(!empty($login)) {
        return date_i18n('M j, Y', strtotime($login->created_at)) . '<br/>' . date_i18n('g:i A', strtotime($login->created_at));
      }
      else {
        return __('Never', 'memberpress');
      }
    }

    if($column_name == 'mepr_num_logins') { return (int)$user->get_num_logins(); }

    return $value;
  }

  public static function maybe_redirect_member_from_admin() {
    $mepr_options = MeprOptions::fetch();

    if(defined('DOING_AJAX')) { return; }

    if($mepr_options->lock_wp_admin && !current_user_can('delete_posts')) {
      if(isset($mepr_options->login_redirect_url) && !empty($mepr_options->login_redirect_url)) {
        MeprUtils::wp_redirect($mepr_options->login_redirect_url);
      }
      else {
        MeprUtils::wp_redirect(home_url());
      }
    }
  }

  public static function maybe_disable_wp_registration_form($login, $email, $errors) {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->disable_wp_registration_form) {
      $message = __('You cannot register with this form. Please use the registration page found on the website instead.', 'memberpress');
      $errors->add('mepr_disabled_error', $message);
    }
  }

  public static function maybe_disable_admin_bar() {
    $mepr_options = MeprOptions::fetch();

    if(!current_user_can('delete_posts') && $mepr_options->disable_wp_admin_bar)
      show_admin_bar(false);
  }

  public static function login_page_meta_box() {
    global $post;

    $mepr_options = MeprOptions::fetch();

    if(isset($post) && $post instanceof WP_Post && $post->ID == $mepr_options->login_page_id) {
      add_meta_box('mepr_login_page_meta_box', __('MemberPress Settings', 'memberpress'), 'MeprUsersCtrl::show_login_page_meta_box', 'page', 'normal', 'high');
    }
  }

  public static function show_login_page_meta_box() {
    global $post;

    $mepr_options = MeprOptions::fetch();

    if(isset($post) && $post->ID) {
      $manual_login_form = get_post_meta($post->ID, '_mepr_manual_login_form', true);

      MeprView::render('/admin/users/login_page_meta_box', get_defined_vars());
    }
  }

  public static function save_postdata($post_id) {
    $post = get_post($post_id);
    $mepr_options = MeprOptions::fetch();

    if(!wp_verify_nonce((isset($_POST[MeprUser::$nonce_str]))?$_POST[MeprUser::$nonce_str]:'', MeprUser::$nonce_str.wp_salt())) {
      return $post_id; //Nonce prevents meta data from being wiped on move to trash
    }

    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return $post_id;
    }

    if(defined('DOING_AJAX')) {
      return;
    }

    if(!empty($post) && $post->ID == $mepr_options->login_page_id) {
      $manual_login_form = (isset($_POST['_mepr_manual_login_form']));
      update_post_meta($post->ID, '_mepr_manual_login_form', $manual_login_form);
    }
  }

  public static function list_users_subscriptions($atts, $content = '') {
    $user = MeprUtils::get_currentuserinfo();
    $active_rows = array();
    $inactive_rows = array();
    $alt_row = 'mp_users_subscriptions_list_alt';

    if(!$user) { return ''; }

    $all_ids    = $user->current_and_prior_subscriptions(); //returns an array of Product ID's the user has ever been subscribed to
    $active_ids = $user->active_product_subscriptions('ids');

    foreach($all_ids as $id) {
      $prd = new MeprProduct($id);

      if(in_array($id, $active_ids)) {
        $expiring_txn = MeprUser::get_user_product_expires_at_date($user->ID, $id, true);
        $renewal_link = '';
        $expires_at   = _x('Unknown', 'ui', 'memberpress');

        if($expiring_txn instanceof MeprTransaction) {
          $renewal_link = $user->renewal_link($expiring_txn->id);
          $expires_at = MeprAppHelper::format_date($expiring_txn->expires_at, _x('Never', 'ui', 'memberpress'));
        }

        $active_rows[] = (object)array('membership' => $prd->post_title, 'expires' => $expires_at, 'renewal_link' => $renewal_link);
      }
      else {
        $inactive_rows[] = (object)array('membership' => $prd->post_title, 'purchase_link' => $prd->url());
      }
    }

    ob_start();
    MeprView::render('/shortcodes/list_users_subscriptions', get_defined_vars());
    return ob_get_clean();
  }
} //End class
