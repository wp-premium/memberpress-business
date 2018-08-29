<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUser extends MeprBaseModel {
  public static $id_str           = 'ID';
  public static $first_name_str   = 'first_name';
  public static $last_name_str    = 'last_name';
  public static $username_str     = 'user_login';
  public static $email_str        = 'user_email';
  public static $password_str     = 'user_pass';
  public static $user_message_str = 'mepr_user_message';
  public static $uuid_str         = 'uuid';
  public static $nonce_str        = 'mepr_users_nonce';

  // Used to prevent welcome notification from sending multiple times
  public static $signup_notice_sent_str = 'signup_notice_sent';

  /** Defaults to loading by id **/
  public function __construct($id = null) {
    $this->attrs = array();
    $this->initialize_new_user(); //A bit redundant I know - But this prevents a nasty error when Standards = STRICT in PHP
    $this->load_user_data_by_id($id);
  }

  public static function all($type='objects', $args=array(), $order_by='', $limit='') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    if($type=='objects') {
      $records = $mepr_db->get_records($wpdb->users, $args, $order_by, $limit);

      $users = array();
      foreach($records as $record) {
        $users[] = new MeprUser($record->ID);
      }
    }
    else if($type=='ids') {
      $users = $mepr_db->get_col($wpdb->users, 'id', $args, $order_by, $limit);
    }

    return $users;
  }

  public function validate() {
    $this->validate_is_email($this->user_email,'user_email');
    $this->validate_not_empty($this->user_login,'user_login');
  }

  public function load_user_data_by_id($id = null) {
    if(empty($id) or !is_numeric($id)) {
      $this->initialize_new_user();
    }
    else {
      $wp_user_obj = MeprUtils::get_user_by('id', $id);
      if($wp_user_obj instanceof WP_User) {
        $this->load_wp_user($wp_user_obj);
        $this->load_meta();
      }
      else {
        $this->initialize_new_user();
      }
    }

    // This must be here to ensure that we don't pull an encrypted
    // password, encrypt it a second time and store it
    unset($this->user_pass);
  }

  public function load_user_data_by_login($login = null) {
    if(empty($login)) {
      $this->initialize_new_user();
    }
    else {
      $wp_user_obj = MeprUtils::get_user_by('login', $login);
      if($wp_user_obj instanceof WP_User) {
        $this->load_wp_user($wp_user_obj);
        $this->load_meta($wp_user_obj);
      }
      else {
        $this->initialize_new_user();
      }
    }

    // This must be here to ensure that we don't pull an encrypted
    // password, encrypt it a second time and store it
    unset($this->user_pass);
  }

  public function load_user_data_by_email($email = null) {
    if(empty($email)) {
      $this->initialize_new_user();
    }
    else {
      $wp_user_obj = MeprUtils::get_user_by('email', $email);
      if($wp_user_obj instanceof WP_User) {
        $this->load_wp_user($wp_user_obj);
        $this->load_meta($wp_user_obj);
      }
      else {
        $this->initialize_new_user();
      }
    }

    // This must be here to ensure that we don't pull an encrypted
    // password, encrypt it a second time and store it
    unset($this->user_pass);
  }

  public function load_user_data_by_uuid($uuid = null) {
    global $wpdb;

    $query = "SELECT * FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1";
    $query = $wpdb->prepare($query, self::$uuid_str, $uuid);
    $row = $wpdb->get_row($query);

    if($row and isset($row->user_id) and is_numeric($row->user_id)) {
      return $this->load_user_data_by_id($row->user_id);
    }
    else {
      return false;
    }
  }

  protected function initialize_new_user() {
    if(!isset($this->attrs) or !is_array($this->attrs)) { $this->attrs = array(); }

    $u = array(
      'ID'                  => null,
      'first_name'          => null,
      'last_name'           => null,
      'user_login'          => null,
      'user_nicename'       => null,
      'user_email'          => null,
      'user_url'            => null,
      'user_pass'           => null,
      'user_message'        => null,
      'user_registered'     => null,
      'user_activation_key' => null,
      'user_status'         => null,
      'signup_notice_sent'  => null,
      'display_name'        => null
    );

    // Initialize user_meta variables
    foreach($this->attrs as $var) {
      $u[$var] = null;
    }

    $this->rec = (object)$u;

    return $this->rec;
  }

  public function load_wp_user($wp_user_obj) {
    $this->rec->ID = $wp_user_obj->ID;
    $this->rec->user_login = $wp_user_obj->user_login;
    $this->rec->user_nicename = (isset($wp_user_obj->user_nicename))?$wp_user_obj->user_nicename:'';
    $this->rec->user_email = $wp_user_obj->user_email;
    $this->rec->user_url = (isset($wp_user_obj->user_url))?$wp_user_obj->user_url:'';
    $this->rec->user_pass = $wp_user_obj->user_pass;
    $this->rec->user_message = stripslashes($wp_user_obj->user_message);
    $this->rec->user_registered = $wp_user_obj->user_registered;
    $this->rec->user_activation_key = (isset($wp_user_obj->user_activation_key))?$wp_user_obj->user_activation_key:'';
    $this->rec->user_status = (isset($wp_user_obj->user_status))?$wp_user_obj->user_status:'';
    // We don't need this, and as of WP 3.9 -- this causes wp_update_user() to wipe users role/caps!!!
    // $this->rec->role = (isset($wp_user_obj->role))?$wp_user_obj->role:'';
    $this->rec->display_name = (isset($wp_user_obj->display_name))?$wp_user_obj->display_name:'';
  }

  public function load_meta() {
    $this->rec->first_name = get_user_meta($this->ID, self::$first_name_str, true);
    $this->rec->last_name = get_user_meta($this->ID, self::$last_name_str, true);
    $this->rec->signup_notice_sent = get_user_meta($this->ID, self::$signup_notice_sent_str, true);
    $this->rec->user_pass = get_user_meta($this->ID, self::$password_str, true);
    $this->rec->user_message = get_user_meta($this->ID, self::$user_message_str, true);
    $this->rec->uuid = $this->load_uuid();
  }

  /** Retrieve or generate the uuid depending on whether its in the database or not */
  public function load_uuid($force = false) {
    $uuid = get_user_meta($this->ID, self::$uuid_str, true);

    if($force or empty($uuid)) {
      $uuid = md5(base64_encode(uniqid()));
      update_user_meta($this->ID, self::$uuid_str, $uuid);
    }

    return $uuid;
  }

  public function is_active() {
    $subscriptions = $this->active_product_subscriptions('ids', true);
    return !empty($subscriptions);
  }

  public function has_expired() {
    $subscriptions = $this->active_product_subscriptions('ids', true, false);
    return !empty($subscriptions);
  }

  // Determines if a user is already subscribed to a membership
  public function is_already_subscribed_to($product_id) {
    return in_array($product_id, $this->active_product_subscriptions('ids', true));
  }

  // Convenience method for checking the activity of a user on a given membership
  public function is_active_on_membership($obj) {
    $id = 0;

    if($obj instanceof MeprProduct) {
      $id = $obj->ID;
    }
    elseif($obj instanceof MeprTransaction || $obj instanceof MeprSubscription) {
      $id = $obj->product_id;
    }
    elseif(is_numeric($obj) && $obj > 0) {
      $id = $obj;
    }
    else {
      return false;
    }

    return $this->is_already_subscribed_to($id);
  }

  /**
  * Used to check if a condition exists for user or the users memberships
  * Returns: true/false
  */
  public function has_access_from_rule($rule_id) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $where_clause = $wpdb->prepare("
      WHERE rule_id=%d
      AND ((access_type='member' AND access_operator='is' AND access_condition=%s)
      ",
      $rule_id,
      $this->user_login
    );
    $active_subscriptions = $this->active_product_subscriptions();
    if(!empty($active_subscriptions)) {
      $where_clause .= $wpdb->prepare(" OR (access_type='membership' AND access_operator='is' AND access_condition IN (".implode(',', array_fill(0, count($active_subscriptions), '%d'))."))", $active_subscriptions);
    }
    $where_clause .= ')';

    $query = "
      SELECT 1
      FROM {$mepr_db->rule_access_conditions}
      {$where_clause}
      LIMIT 1
    ";

    return (1 === $wpdb->query($query));
  }

  // Retrieves the current subscription within a group (with upgrade paths enabled)
  public function subscription_in_group($group_id, $look_for_lapsed = false) {
    if($group_id instanceof MeprGroup && isset($group_id->ID) && $group_id->ID) {
      $group_id = $group_id->ID;
    }

    $subs = MeprSubscription::get_all_active_by_user_id($this->ID, "", "", false, $look_for_lapsed);

    if(empty($subs)) { return false; }

    foreach($subs as $sub_data) {
      $sub = new MeprSubscription($sub_data->id);
      $prd = $sub->product();
      if($prd->group_id==$group_id) { return $sub; }
    }

    return false;
  }

  public function lifetime_subscription_in_group($group_id) {
    if($group_id instanceof MeprGroup && isset($group_id->ID) && $group_id->ID) {
      $group_id = $group_id->ID;
    }

    $txns = $this->active_product_subscriptions('transactions');

    if(empty($txns)) { return false; }

    foreach($txns as $txn) {
      $p = $txn->product();
      if((int)$txn->subscription_id == 0 && $p->group_id == $group_id) { return $txn; }
    }

    return false;
  }

  public function is_logged_in_and_current_user() {
    return MeprUtils::is_logged_in_and_current_user($this->ID);
  }

  public function is_logged_in() {
    return MeprUtils::is_logged_in($this->ID);
  }

  public function active_product_subscriptions($return_type = 'ids', $force = false, $exclude_expired = true) {
    static $items; //Prevents a butt load of queries on the front end

    $user_id = $this->ID;

    // Setup caching array
    if(!isset($items) || !is_array($items)) { $items = array(); }

    // Setup caching array for this user
    if(!isset($items[$user_id]) || !is_array($items[$user_id])) { $items[$user_id] = array(); }

    //I'm assuming we may run into instances where we need to force the query to run
    //so $force should allow that
    if($force || !isset($items[$user_id][$return_type]) || !is_array($items[$user_id][$return_type])) {
      $txns = MeprTransaction::get_all_complete_by_user_id(
        $user_id, // user_id
        'product_id, created_at DESC', // order_by
        '', // limit
        false, // count
        $exclude_expired, // exclude_expired
        true, // include_confirmations
        true // allow custom where clause override
      );

      $items[$user_id][$return_type] = array();

      foreach($txns as $txn) {
        if($return_type == 'ids') {
          $items[$user_id][$return_type][] = $txn->product_id;
        }
        else if($return_type == 'products' || $return_type === true) {
          $items[$user_id][$return_type][] = new MeprProduct($txn->product_id);
        }
        else if($return_type == 'transactions') {
          $items[$user_id][$return_type][] = new MeprTransaction($txn->id);
        }
      }
    }

    return $items[$user_id][$return_type];
  }

  public function get_active_subscription_titles($sep = ', ') {
    $formatted_titles = '';
    $res = $this->active_product_subscriptions();

    if(!empty($res)) {
      // don't list the same name multiple times
      $products = array_values(array_unique( $res ));
      $titles = array();

      for($i = 0; $i < count($products); $i++) {
        $titles[] = get_the_title($products[$i]);
      }

      sort($titles);

      $formatted_titles = implode( $sep, $titles );
    }

    return $formatted_titles;
  }

  //Gets the product_id's of the subscriptions this user has which are marked as "Enabled"
  //This does NOT mean they are active, just that they are recurring and marked as "Enabled"
  public function get_enabled_product_ids($prd_id = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("SELECT product_id FROM {$mepr_db->subscriptions} WHERE status = %s AND user_id = %d", MeprSubscription::$active_str, $this->ID);

    if(isset($prd_id) && $prd_id) {
      $q .= " " . $wpdb->prepare("AND product_id = %d", $prd_id);
    }

    $res = $wpdb->get_col($q);

    return array_unique($res);
  }

  // $who should be 1 (row) object in the $product->who_can_purchase array.
  public function can_user_purchase($who, $curr_prd_id = 0) {
    $current_subscriptions  = $this->active_product_subscriptions('ids');
    $all_subscriptions      = $this->active_product_subscriptions('ids', true, false); //We need to force here, and we do not want to exclude expired
    $expired_subscriptions  = array_diff($all_subscriptions, $current_subscriptions); //return values from $all_subscriptions which are NOT also present in $current_subscriptions

    //User is not currently subscribed to something
    if($who->product_id == 'nothing') { return empty($current_subscriptions); }

    //user is currently subscribed to anything
    if($who->product_id == 'anything') { return !empty($current_subscriptions); }

    //user has previously purchased this membership level
    if($who->product_id == 'subscribed-before') { return in_array($curr_prd_id, $all_subscriptions); }

    //user has NOT previously purchased this membership level before
    if($who->product_id == 'not-subscribed-before') { return !in_array($curr_prd_id, $all_subscriptions); }

    //Now let's check if the actual membership ID is in the user's active subscriptions or not
    return in_array($who->product_id, $current_subscriptions);
  }

  public function fallback_txn($product_id) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = $wpdb->prepare("
      SELECT id FROM {$mepr_db->transactions}
      WHERE user_id = %d
        AND product_id = %d
        AND gateway = %s
      ",
      $this->ID,
      $product_id,
      MeprTransaction::$fallback_gateway_str
    );

    $result =  $wpdb->get_var($query);
    if($result) {
      return new MeprTransaction($result);
    }
    else {
      return false;
    }
  }

  public function get_full_name() {
    return $this->full_name();
  }

  public function full_name() {
    $name = "";

    if(!empty($this->first_name)) {
      $name = $this->first_name;
    }

    if(!empty($this->last_name)) {
      if(empty($name)) {
        $name = $this->last_name;
      }
      else {
        $name .= " {$this->last_name}";
      }
    }

    return $name;
  }

  //Should make sure user is logged in before calling this function
  public static function get_current_user_registration_date() {
    global $user_ID;

    return self::get_user_registration_date($user_ID);
  }

  public static function get_user_registration_date($user_id) {
    global $wpdb;

    $q = "SELECT `user_registered` FROM {$wpdb->users} WHERE ID=%d";

    $result = $wpdb->get_var($wpdb->prepare($q, $user_id));

    return (empty($result)?time('c'):$result);
  }

  /** This used to be called "get_ts_of_product_signup" */
  public static function get_user_product_signup_date($user_id, $product_id) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();
    $prd = new MeprProduct($product_id);

    //If this is a renewal type product, we should grab the first txn instead of the last
    $order = ($prd->is_one_time_payment() && $prd->allow_renewal) ? "ASC" : "DESC";
    $order = MeprHooks::apply_filters('mepr-user-membership-signup-date-txn-order', $order, $user_id, $product_id);

    //Grab  complete payment OR confirmed confirmation type for this user
    $q = "SELECT id
            FROM {$mepr_db->transactions}
            WHERE product_id = %d
              AND user_id = %d
              AND ( (txn_type IN (%s,%s) AND status = %s) OR (txn_type = %s AND status = %s) )
          ORDER BY id {$order}
          LIMIT 1";
    $q =  $wpdb->prepare( $q,
                          $product_id,
                          $user_id,
                          MeprTransaction::$payment_str,
                          MeprTransaction::$sub_account_str,
                          MeprTransaction::$complete_str,
                          MeprTransaction::$subscription_confirmation_str,
                          MeprTransaction::$confirmed_str
          );
    $txn_id = $wpdb->get_var($q);

    //No txn for this user for this product
    if(empty($txn_id) || $txn_id <= 0) { return false; }

    //Load up the txn object duh!
    $txn = new MeprTransaction($txn_id);

    //This isn't a subscription, so this should be the only txn for this product
    $sub = $txn->subscription();
    if(empty($sub)) { return $txn->created_at; }

    //Get the first real payment txn in this $sub unless it had a free trial - if free trial then we want the drips to start when the free trial started, not when the first payment happened
    if(!$sub->trial || ($sub->trial && $sub->trial_amount > 0.00)) {
      $_REQUEST['mepr_get_real_payment'] = true; //Try to get a real payment instead of a confirmation txn
    }

    $first_txn = $sub->first_txn();

    if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
      return false;
    }
    else {
      return $first_txn->created_at;
    }
  }

  public static function get_user_product_expires_at_date($user_id, $product_id, $return_txn = false) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $select = ($return_txn)?"id":"expires_at";

    $q = "SELECT {$select} FROM {$mepr_db->transactions} WHERE status IN(%s, %s) AND product_id = %d AND user_id = %d ORDER BY expires_at DESC LIMIT 1";
    $q = $wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$confirmed_str, $product_id, $user_id);

    $result = $wpdb->get_var($q);

    if($result && $return_txn) {
      return new MeprTransaction((int)$result);
    }

    return ($result)?$result:false;
  }

  public function store() {
    if(isset($this->ID) and !is_null($this->ID)) {
      $id = wp_update_user((array)$this->rec);
    }
    else {
      $id = wp_insert_user((array)$this->rec);
    }

    if(is_wp_error($id)) {
      throw new MeprCreateException(sprintf(__('The user was unable to be saved: %s', 'memberpress'), $id->get_error_message()));
    }
    else {
      $this->rec->ID = $id;
    }

    $this->store_meta();

    return $id;
  }

  // alias of store
  public function save() {
    return $this->store();
  }

  public function store_meta() {
    update_user_meta($this->ID, self::$first_name_str, $this->first_name);
    update_user_meta($this->ID, self::$last_name_str,  $this->last_name);
    update_user_meta($this->ID, self::$signup_notice_sent_str, $this->signup_notice_sent);
  }

  // alias of store_meta
  public function save_meta() {
    return $this->store_meta();
  }

  public function destroy() {
    $res = wp_delete_user($this->ID);

    if(false===$res) {
      throw new MeprCreateException(sprintf(__( 'This user was unable to be deleted.', 'memberpress')));
    }

    return $this;
  }

  public function reset_form_key_is_valid($key) {
    $stored_key = get_user_meta( $this->ID, 'mepr_reset_password_key', true);
    return (!empty($stored_key) and ($key == $stored_key));
  }

  public function send_reset_password_requested_notification($force_send = false) {
    static $already_sent;

    //prevent dup emails
    if(!$force_send && $already_sent === true) {
      return;
    }

    $already_sent = true;

    $mepr_options = MeprOptions::fetch();
    $mepr_blogname = get_option('blogname');
    $mepr_blogurl = home_url();

    $reset_password_link = $this->reset_password_link();
    $recipient = $this->formatted_email();

    /* translators: In this string, %s is the Blog Name/Title */
    $subject = sprintf( __("[%s] Password Reset", 'memberpress'), $mepr_blogname);

    ob_start();
    ?>
      <p><?php echo sprintf(_x("Someone requested to reset your password for %1\$s on %2\$s at %3\$s", 'ui', 'memberpress'), $this->user_login, $mepr_blogname, $mepr_blogurl); ?></p>
      <p><?php echo _x("To reset your password visit the following address, otherwise just ignore this email and nothing will happen.", 'ui', 'memberpress'); ?></p>
      <p><a href="<?php echo $reset_password_link; ?>"><?php echo $reset_password_link; ?></a></p>
    <?php
    $message = ob_get_clean();

    MeprUtils::wp_mail($recipient, $subject, $message, array("Content-Type: text/html"));
  }

  public function set_password_and_send_notifications($key, $password) {
    static $already_sent;

    //prevent dup emails
    if($already_sent === true) {
      return;
    }

    $already_sent = true;


    $mepr_options = MeprOptions::fetch();
    $mepr_blogname = get_option('blogname');
    $mepr_blogurl = home_url();

    if($this->reset_form_key_is_valid($key)) {
      delete_user_meta($this->ID, 'mepr_reset_password_key');
      add_filter('send_password_change_email', '__return_false'); //DISABLE WP'S PW CHANGE NOTIFICATION

      $this->rec->user_pass = $password;
      $this->store();

      /* translators: In this string, %s is the Blog Name/Title */
      $subject = sprintf(__("[%s] Password Lost/Changed", 'memberpress'), $mepr_blogname);

      /* translators: In this string, %1$s is the user's username */
      $message = sprintf(__("Password Lost and Changed for user: %1\$s", 'memberpress'), $this->user_login);

      MeprUtils::wp_mail_to_admin($subject, $message);

      $login_link = $mepr_options->login_page_url();

      // Send password email to new user
      $recipient = $this->formatted_email();

      /* translators: In this string, %s is the Blog Name/Title */
      $subject = sprintf(_x("[%s] Your new Password", 'ui', 'memberpress'), $mepr_blogname);
      $password_message = _x('', 'ui', 'memberpress');

      ob_start();
      ?>
        <p>
          <?php echo (empty($this->first_name)?$this->user_login:$this->first_name); ?>,
          <br/>
          <?php echo sprintf(_x("Your password was successfully reset on %1\$s!", 'ui', 'memberpress'), $mepr_blogname); ?>
        </p>
        <p>
          <?php echo sprintf(_x("Username: %1\$s", 'ui', 'memberpress'), $this->user_login); ?>
          <br/>
          <?php echo _x("Password: *** Successfully Reset ***", 'ui', 'memberpress'); ?>
        </p>
        <p><?php echo sprintf(_x("You can now login here: %1\$s", 'ui', 'memberpress'), $login_link); ?></p>
      <?php
      $message = ob_get_clean();

      MeprUtils::wp_mail($recipient, $subject, $message, array("Content-Type: text/html"));

      return true;
    }

    return false;
  }

  public static function validate_account($params, $errors = array()) {
    $mepr_options = MeprOptions::fetch();

    extract($params);

    if($mepr_options->require_fname_lname && (empty($user_first_name) || empty($user_last_name))) {
      $errors[] = __('You must enter both your First and Last name', 'memberpress');
    }

    if(empty($user_email) || !is_email(stripslashes($user_email))) {
      $errors[] = __('You must enter a valid email address', 'memberpress');
    }

    //Old email is not the same as the new, so let's make sure no else has it
    $user = MeprUtils::get_currentuserinfo(); //Old user info is here since we haven't stored the new stuff yet
    if($user !== false && $user->user_email != stripslashes($user_email) && email_exists(stripslashes($user_email))) {
      $errors[] = __('This email is already in use by another member', 'memberpress');
    }

    return $errors;
  }

  public static function validate_signup($params, $errors) {
    $mepr_options = MeprOptions::fetch();
    $custom_fields_errors = array();

    extract($params);

    if(!MeprUtils::is_user_logged_in()) {
      //Set user_login to user_email if that option is enabled.
      if($mepr_options->username_is_email)
        $user_login = (isset($user_email) && is_email($user_email))?$user_email:'placeholderToPreventEmptyUsernameErrors';

      if(empty($user_login))
        $errors[] = __('Username must not be blank','memberpress');

      if(!$mepr_options->username_is_email && !preg_match('#^[a-zA-Z0-9_@\.\-\+]+$#', $user_login)) //emails can have a few more characters - so let's not block an email here
        $errors[] = __('Username must only contain letters, numbers and/or underscores', 'memberpress');

      if(username_exists($user_login)) {
        $current_url = urlencode(esc_url($_SERVER['REQUEST_URI']));
        $login_url = $mepr_options->login_page_url("redirect_to={$current_url}");

        $errors[] = sprintf(__('This username has already been taken. If you are an existing user, please %sLogin%s first. You will be redirected back here to complete your sign-up afterwards.', 'memberpress'), "<a href=\"{$login_url}\"><strong>", "</strong></a>");
      }

      if(!is_email(stripslashes($user_email)))
        $errors[] = __('Email must be a real and properly formatted email address', 'memberpress');

      if(email_exists($user_email)) {
        $current_url = urlencode(esc_url($_SERVER['REQUEST_URI']));
        $login_url = $mepr_options->login_page_url("redirect_to={$current_url}");

        $errors[] = sprintf(__('This email address has already been used. If you are an existing user, please %sLogin%s to complete your purchase. You will be redirected back here to complete your sign-up afterwards.', 'memberpress'), "<a href=\"{$login_url}\"><strong>", "</strong></a>");
      }

      if(empty($mepr_user_password))
        $errors[] = __('You must enter a Password.','memberpress');

      if(empty($mepr_user_password_confirm))
        $errors[] = __('You must enter a Password Confirmation.', 'memberpress');

      if($mepr_user_password != $mepr_user_password_confirm)
        $errors[] = __('Your Password and Password Confirmation don\'t match.', 'memberpress');

      //Honeypot
      if((isset($mepr_no_val) && !empty($mepr_no_val)))
        $errors[] = __('Only humans are allowed to register.', 'memberpress');
    }

    if(($mepr_options->show_fname_lname and $mepr_options->require_fname_lname) &&
       (empty($user_first_name) || empty($user_last_name)))
      $errors[] = __('You must enter both your First and Last name', 'memberpress');

    if(isset($mepr_coupon_code) && !empty($mepr_coupon_code) && !MeprCoupon::is_valid_coupon_code($mepr_coupon_code, $mepr_product_id))
      $errors[] = __('Your coupon code is invalid.', 'memberpress');

    if($mepr_options->require_tos && !isset($mepr_agree_to_tos) && !isset($logged_in_purchase)) //Make sure not logged in purchase
      $errors[] = __('You must agree to the Terms of Service', 'memberpress');

    if($mepr_options->require_privacy_policy && !isset($mepr_agree_to_privacy_policy)) //Make sure not logged in purchase
      $errors[] = __('You must agree to the Privacy Policy', 'memberpress');

    $product = new MeprProduct($mepr_product_id);
    $product_coupon_code = isset($mepr_coupon_code) ? $mepr_coupon_code : null;
    $product_price = $product->adjusted_price($product_coupon_code);
    $pms = $mepr_options->payment_methods();

    // Don't allow free payment method on non-free transactions
    // Don't allow manual payment method on the signup form
    unset($pms['free']); unset($pms['manual']);

    $pms = array_keys($pms);

    if((!isset($mepr_payment_method) or empty($mepr_payment_method)) and $product_price > 0.00) {
      $errors[] = __('There are no active Payment Methods right now ... please contact the system administrator for help.', 'memberpress');
    }

    // We only care what the payment_method is if the membership isn't free
    // Don't allow payment methods not included in mepr option's pm's
    // Don't allow payment methods not included in custom pm's if we're customizing pm's

    if( isset($mepr_payment_method) and
        !empty($mepr_payment_method) and
        $product_price > 0.00 and
        ( !in_array( strtolower($mepr_payment_method), $pms ) or
          ( $product->customize_payment_methods and
            isset($product->custom_payment_methods) and
            is_array($product->custom_payment_methods) and
            !in_array( strtolower($mepr_payment_method),
                       $product->custom_payment_methods ) ) ) ) {
      $errors[] = __('Invalid Payment Method', 'memberpress');
    }

    //Make sure this isn't the logged in purchases form
    if(!isset($logged_in_purchase) || (isset($logged_in_purchase) && $mepr_options->show_fields_logged_in_purchases)) {
      $custom_fields_errors = MeprUsersCtrl::validate_extra_profile_fields(null, null, null, true, $product);
    }

    return array_merge($errors, $custom_fields_errors);
  }



  public static function validate_login($params, $errors) {
    extract($params);

    if(empty($log)) {
      $errors[] = __('Username must not be blank', 'memberpress');
    }

    if(is_email($log)) {
      $user = get_user_by('email', $log);

      //Try one more thing before giving up in case their username is an email address but doesn't match their current email address (user_email != user_login)
      if($user === false) {
        $user = get_user_by('login', $log);
      }
    }
    else {
      $user = get_user_by('login', $log);
    }

    if($user === false) {
      $errors[] = __('Your username or password was incorrect', 'memberpress');
    }

    //If no errors at this point, let's check their password
    if(empty($errors)) {
      if(!MeprUtils::wp_check_password($user, $pwd)) {
        $errors[] = __('Your username or password was incorrect', 'memberpress');
      }
    }

    return $errors;
  }

  public static function validate_forgot_password($params, $errors) {
    extract($params);

    if(empty($mepr_user_or_email))
      $errors[] = __('You must enter a Username or Email', 'memberpress');
    else {
      $is_email = (is_email($mepr_user_or_email) and email_exists($mepr_user_or_email));
      $is_username = username_exists($mepr_user_or_email);

      if(!$is_email and !$is_username)
        $errors[] = __("That Username or Email wasn't found.", 'memberpress');
    }

    return $errors;
  }

  public static function validate_reset_password($params, $errors) {
    $mepr_options = MeprOptions::fetch();
    extract($params);

    if($mepr_options->enforce_strong_password && isset($_POST['mp-pass-strength']) && (int)$_POST['mp-pass-strength'] < MeprZxcvbnCtrl::get_required_int()) {
      $errors[] = __('Your password must meet the minimum strength requirement.', 'memberpress');
    }

    if(empty($mepr_user_password))
      $errors[] = __('You must enter a Password.', 'memberpress');

    if(empty($mepr_user_password_confirm))
      $errors[] = __('You must enter a Password Confirmation.', 'memberpress');

    if($mepr_user_password != $mepr_user_password_confirm)
      $errors[] = __("Your Password and Password Confirmation don't match.", 'memberpress');

    return $errors;
  }

  public function sent_renewal($txn_id) {
    return add_user_meta($this->ID, 'mepr_renewal', $txn_id);
  }

  public function get_renewals() {
    return get_user_meta($this->ID, 'mepr_renewal', false);
  }

  public function renewal_already_sent($txn_id) {
    $renewals = $this->get_renewals();
    return (!empty($renewals) and in_array($txn_id, $renewals));
  }

  public function subscriptions() {
    $table = MeprSubscription::account_subscr_table(
      'created_at', 'DESC',
      '', '', 'any', '', false,
      array(
        'member' => $this->rec->user_login,
        'statuses' => array(
          MeprSubscription::$active_str,
          MeprSubscription::$suspended_str,
          MeprSubscription::$cancelled_str
        )
      ),
      MeprHooks::apply_filters('mepr_user_subscriptions_query_cols', array('id','created_at'))
    );

    $subscriptions = array();
    foreach($table['results'] as $row) {
      if($row->sub_type == 'subscription') {
        $sub = new MeprSubscription($row->id);
      }
      else if($row->sub_type == 'transaction') {
        $sub = new MeprTransaction($row->id);
      }

      $subscriptions[] = MeprHooks::apply_filters('mepr_user_subscriptions_customize_subscription', $sub, $row, $this);
    }

    return $subscriptions;
  }

  public function transactions($where = null, $order = 'created_at', $sort = 'DESC') {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $conditions = $wpdb->prepare("WHERE user_id=%d", $this->ID);

    if(!is_null($where)) {
      $conditions = "{$conditions} AND {$where}";
    }

    $q = "
      SELECT *
        FROM {$mepr_db->transactions}
       {$conditions}
       ORDER BY {$order} {$sort}
    ";

    return $wpdb->get_results($q);
  }

  //Does NOT get sub confirmation txns
  //For right now this is only used for Reminders, but could be used in other places
  public function transactions_for_product($product_id, $expired = false, $non_expired = false) {
    global $wpdb;

    $operator = ($expired) ? '<=' : '>=';
    $db_lifetime = ($expired) ? '' : $wpdb->prepare('OR expires_at IS NULL OR expires_at = %s', MeprUtils::db_lifetime());
    $where = $wpdb->prepare(
      "product_id = %d AND (expires_at {$operator} %s {$db_lifetime}) AND txn_type = %s AND status = %s",
      $product_id,
      gmdate('c'),
      MeprTransaction::$payment_str,
      MeprTransaction::$complete_str
    );

    return $this->transactions($where);
  }

  public function recent_transactions($limit=5) {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT id
          FROM {$mepr_db->transactions}
         WHERE user_id=%d
         ORDER BY id DESC
         LIMIT %d
      ",
      $this->ID,
      $limit
    );

    $txn_ids = $wpdb->get_col($q);

    if(empty($txn_ids)) { return array(); }

    $txns = array();
    foreach($txn_ids as $txn_id) {
      $txns[] = new MeprTransaction($txn_id);
    }

    return $txns;
  }

  public function recent_subscriptions($limit=5) {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT sub.id
          FROM {$mepr_db->subscriptions} AS sub
         WHERE sub.user_id = %d
         ORDER BY id DESC
         LIMIT %d
      ",
      $this->ID,
      $limit
    );

    $sub_ids = $wpdb->get_col($q);

    if(empty($sub_ids)) { return array(); }

    $subs = array();
    foreach($sub_ids as $sub_id) {
      $subs[] = new MeprSubscription($sub_id);
    }

    return $subs;
  }

  public static function email_users_with_expiring_transactions() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->user_renew_email == true)
    {
      $transactions = MeprTransaction::get_expiring_transactions();
      if(!empty($transactions) and is_array($transactions))
      {
        foreach($transactions as $transaction)
        {
          $user = new MeprUser($transaction->user_id);
          $product = new MeprProduct($transaction->product_id);

          $params = new stdClass();
          $params->user_first_name = $user->first_name;
          $params->user_last_name  = $user->last_name;
          $params->user_email      = $user->user_email;
          $params->to_email        = $user->user_email;
          $params->to_name         = "{$user->first_name} {$user->last_name}";
          $params->membership_type = $product->post_title;
          $params->business_name   = $mepr_options->attr('biz_name');
          $params->blog_name       = get_option('blogname');
          $params->renewal_link    = $user->renewal_link($transaction->id);

          if(MeprUtils::send_user_renew_notification((array)$params))
            $user->sent_renewal($transaction->id);
        }
      }
    }
  }

  public function renewal_link($txn_id) {
    $txn = new MeprTransaction($txn_id);
    $product = new MeprProduct($txn->product_id);

    if($product->allow_renewal) {
      return $product->url("?renew=true&uid={$this->uuid}&tid={$txn_id}");
    }

    return '';
  }

  public function reset_password_link() {
    $mepr_options = MeprOptions::fetch();

    $key = md5(time().$this->ID);
    update_user_meta($this->ID, 'mepr_reset_password_key', $key);

    $permalink = $mepr_options->login_page_url();
    $delim = MeprAppCtrl::get_param_delimiter_char($permalink);

    return "{$permalink}{$delim}action=reset_password&mkey={$key}&u=".urlencode($this->user_login);
  }

  //Returns a list of product ids that the user has or is currently subscribed to
  public function current_and_prior_subscriptions() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = "SELECT DISTINCT(product_id)
            FROM {$mepr_db->transactions}
          WHERE user_id = %d
            AND ( (txn_type IN (%s,%s,%s) AND status = %s) OR ((txn_type = %s AND status = %s)) )";

    $q = $wpdb->prepare($q, $this->ID,
                            MeprTransaction::$payment_str,
                            MeprTransaction::$sub_account_str,
                            MeprTransaction::$fallback_str,
                            MeprTransaction::$complete_str,
                            MeprTransaction::$subscription_confirmation_str,
                            MeprTransaction::$confirmed_str);

    return $wpdb->get_col($q);
  }

  public function subscription_expirations($type='all', $exclude_stopped=false) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $exp_op = (($type=='expired')?'<=':'>');

    // Get all recurring subscriptions that
    // are expired but still have an active status
    $query = "SELECT sub.id as id, tr.expires_at AS expires_at " .
               "FROM {$mepr_db->subscriptions} AS sub " .
               "JOIN {$mepr_db->transactions} AS tr " .
                 "ON tr.id = ( CASE " .
                              // When 1 or more lifetime txns exist for this sub
                              "WHEN ( SELECT COUNT(*) " .
                                       "FROM {$mepr_db->transactions} AS etc " .
                                      "WHERE etc.subscription_id=sub.id " .
                                        "AND etc.status IN (%s,%s) " .
                                        "AND etc.expires_at=%s ) > 0 " .
                              // Use the latest lifetime txn for expiring_txn
                              "THEN ( SELECT max(etl.id) " .
                                       "FROM {$mepr_db->transactions} AS etl " .
                                      "WHERE etl.subscription_id=sub.id " .
                                        "AND etl.status IN (%s,%s) " .
                                        "AND etl.expires_at=%s ) " .
                              // Otherwise use the latest complete txn for expiring_txn
                              "ELSE ( SELECT etr.id " .
                                       "FROM {$mepr_db->transactions} AS etr " .
                                      "WHERE etr.subscription_id=sub.id " .
                                        "AND etr.status IN (%s,%s) " .
                                      "ORDER BY etr.expires_at DESC " .
                                      "LIMIT 1 ) " .
                              "END ) " .
              "WHERE sub.user_id=%d " .
                "AND tr.expires_at IS NOT NULL " .
                "AND tr.expires_at > %s " .
                "AND DATE_ADD(tr.created_at, INTERVAL 1 DAY) <= %s " . //At least a day old here
                "AND tr.expires_at {$exp_op} %s";

    $query = $wpdb->prepare(
      $query,
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      $this->ID, // User ID
      MeprUtils::db_lifetime(),
      MeprUtils::db_now(),
      MeprUtils::db_now()
    );

    if( $exclude_stopped ) {
      $query .= $wpdb->prepare( " AND sub.status = %s", MeprSubscription::$active_str );
    }

    $res = $wpdb->get_results( $query );

    return $res;
  }

  public function get_num_logins()
  {
    $mepr_db = MeprDb::fetch();
    $args = array( 'evt_id_type' => MeprEvent::$users_str,
                   'evt_id' => $this->ID,
                   'event' => MeprEvent::$login_event_str );
    return $mepr_db->get_count( $mepr_db->events, $args );
  }

  public function get_last_login_data() {
    $mepr_db = MeprDb::fetch();
    $args = array( 'evt_id_type' => MeprEvent::$users_str,
                   'evt_id' => $this->ID,
                   'event' => MeprEvent::$login_event_str );
    $rec = $mepr_db->get_records( $mepr_db->events, $args, '`created_at` DESC', 1 );
    return ( empty($rec) ? false : $rec[0] );
  }

  public function set_address($params) {
    update_user_meta($this->ID, 'mepr-address-one',     sanitize_text_field(wp_unslash($params['mepr-address-one'])));
    update_user_meta($this->ID, 'mepr-address-two',     sanitize_text_field(wp_unslash($params['mepr-address-two'])));
    update_user_meta($this->ID, 'mepr-address-city',    sanitize_text_field(wp_unslash($params['mepr-address-city'])));
    update_user_meta($this->ID, 'mepr-address-state',   sanitize_text_field(wp_unslash($params['mepr-address-state'])));
    update_user_meta($this->ID, 'mepr-address-zip',     sanitize_text_field(wp_unslash($params['mepr-address-zip'])));
    update_user_meta($this->ID, 'mepr-address-country', sanitize_text_field(wp_unslash($params['mepr-address-country'])));
  }

  public function full_address($fallback_to_biz_addr=true) {
    return array(
      'mepr-address-one'     => $this->address('one',     $fallback_to_biz_addr),
      'mepr-address-two'     => $this->address('two',     $fallback_to_biz_addr),
      'mepr-address-city'    => $this->address('city',    $fallback_to_biz_addr),
      'mepr-address-state'   => $this->address('state',   $fallback_to_biz_addr),
      'mepr-address-zip'     => $this->address('zip',     $fallback_to_biz_addr),
      'mepr-address-country' => $this->address('country', $fallback_to_biz_addr)
    );
  }

  public function address($field, $fallback_to_biz_addr=true) {
    if($this->address_is_set()) {
      return get_user_meta( $this->ID, "mepr-address-{$field}", true );
    }
    else if($fallback_to_biz_addr) {
      $mepr_options = MeprOptions::fetch();

      if($mepr_options->attr('tax_default_address')=='none') {
        return get_user_meta( $this->ID, "mepr-address-{$field}", true );
      }
      else {
        switch($field) {
          case 'one': return $mepr_options->attr('biz_address1');
          case 'two': return $mepr_options->attr('biz_address2');
          case 'city': return $mepr_options->attr('biz_city');
          case 'state': return $mepr_options->attr('biz_state');
          case 'zip': return $mepr_options->attr('biz_postcode');
          case 'country': return $mepr_options->attr('biz_country');
          default: return get_user_meta( $this->ID, "mepr-address-{$field}", true );
        }
      }
    }
    else {
      return '';
    }
  }

  public function address_is_set() {
    $one      = get_user_meta( $this->ID, 'mepr-address-one', true);
    //$two      = get_user_meta( $this->ID, 'mepr-address-two', true);
    $city     = get_user_meta( $this->ID, 'mepr-address-city', true);
    $state    = get_user_meta( $this->ID, 'mepr-address-state', true);
    $country  = get_user_meta( $this->ID, 'mepr-address-country', true);
    $postcode = get_user_meta( $this->ID, 'mepr-address-zip', true);

    return (!empty($country) && !empty($postcode) && !empty($state) && !empty($city) && !empty($one));
  }

  /**
  * Check if address fields are required for active products
  * @return boolean
  */
  public function show_address_fields() {
    $active_products = $this->active_product_subscriptions('products');
    foreach($active_products as $product) {
      if(!$product->disable_address_fields) {
        return true;
      }
    }
    return empty($active_products); //If the user has no memberships, let's just show the address fields
  }

  public function tax_rate() {
    $mepr_options = MeprOptions::fetch();

    //No taxes enabled?
    if(!get_option('mepr_calculate_taxes', false)) {
      return new MeprTaxRate();
    }

    $country  = $mepr_options->attr('biz_country');
    $state    = $mepr_options->attr('biz_state');
    $postcode = $mepr_options->attr('biz_postcode');
    $city     = $mepr_options->attr('biz_city');
    $street   = sprintf('%s %s', $mepr_options->attr('biz_address1'), $mepr_options->attr('biz_address2'));

    if($this->address_is_set()) {
      if($mepr_options->attr('tax_calc_location')=='customer' ||
         MeprHooks::apply_filters('mepr-tax-rate-use-customer-address', false, $this)) {
        $country  = $this->address('country');
        $state    = $this->address('state');
        $postcode = $this->address('zip');
        $city     = $this->address('city');
        $street   = sprintf('%s %s', $this->address('one'), $this->address('two'));
      }
    }
    elseif($mepr_options->attr('tax_default_address')=='none') {
      return new MeprTaxRate();
    }

    $user = $this;

    return MeprTaxRate::find_rate( compact('street','country','state','postcode','city', 'user') );
  }

  public function calculate_tax($subtotal, $num_decimals=2) {
    $mepr_options = MeprOptions::fetch();
    $rate = $this->tax_rate();

    // We assume that we're dealing with the subtotal
    $tax_amount = MeprUtils::format_float(($subtotal*($rate->tax_rate/100.00)), $num_decimals);
    $total = MeprUtils::format_float(($subtotal + $tax_amount), $num_decimals);

    return array(MeprUtils::format_float($total - $tax_amount), $total, $rate->tax_rate, $tax_amount, $rate->tax_desc, $rate->tax_class);
  }

  public function calculate_subtotal($total, $percent=null, $num_decimals=2) {
    if(is_null($percent)) {
      $rate = $this->tax_rate();
      $percent = $rate->tax_rate;
    }

    return ($total/(1+($percent/100)));
  }

  public function formatted_address() {
    $addr1   = get_user_meta( $this->ID, 'mepr-address-one',     true );
    $addr2   = get_user_meta( $this->ID, 'mepr-address-two',     true );
    $city    = get_user_meta( $this->ID, 'mepr-address-city',    true );
    $state   = get_user_meta( $this->ID, 'mepr-address-state',   true );
    $zip     = get_user_meta( $this->ID, 'mepr-address-zip',     true );
    $country = get_user_meta( $this->ID, 'mepr-address-country', true );

    if( empty($addr1) or empty($city) or
        empty($state) or empty($zip) ) {
      return '';
    }

    $addr = $addr1;

    if($addr2 and !empty($addr2)) { $addr .= "<br/>{$addr2}"; }
    if($country and !empty($country)) { $country = "<br/>{$country}"; } else { $country = ''; }

    $addr = sprintf( __('<br/>%1$s<br/>%2$s, %3$s %4$s%5$s<br/>', 'memberpress'), $addr, $city, $state, $zip, $country );

    return MeprHooks::apply_filters( 'mepr-user-formatted-address', $addr, $this );
  }

  public function formatted_email() {
    return $this->full_name() . " <{$this->user_email}>";
  }

  public static function manually_place_account_form($post) {
    return ($post instanceof WP_Post && preg_match('~\[mepr-account-form~', $post->post_content));
  }

  public static function is_account_page($post) {
    $mepr_options = MeprOptions::fetch();

    $is_account_page = (
        ($post instanceof WP_Post && $post->ID == $mepr_options->account_page_id) ||
        self::manually_place_account_form($post)
    );

    return MeprHooks::apply_filters( 'mepr_is_account_page', $is_account_page, $post);
  }

  public static function is_login_page($post) {
    $mepr_options = MeprOptions::fetch();
    return ($post instanceof WP_Post &&
            ($post->ID == $mepr_options->login_page_id ||
             preg_match('~\[mepr-login-form~', $post->post_content)));
  }

  public function custom_profile_values() {
    $fields = $this->custom_profile_fields();
    $values = array();

    foreach($fields as $field) {
      $values[$field->field_key] = get_user_meta($this->ID, $field->field_key, true);
    }

    return $values;
  }

  public function custom_profile_fields() {
    global $wpdb;
    $mepr_options = MeprOptions::fetch();
    $slugs = $rows = array();

    //If there's no custom fields why are we here?
    if(empty($mepr_options->custom_fields)) { return array(); }

    // How many memberships have field customizations
    $q = "
      SELECT COUNT(*)
        FROM {$wpdb->postmeta} AS pm
       WHERE pm.meta_key = %s
         AND pm.meta_value = %s
         AND pm.post_id IN (
               SELECT p.ID
                 FROM {$wpdb->posts} AS p
                WHERE p.post_status = %s
                  AND p.post_type = %s
             )
    ";

    $q = $wpdb->prepare($q, MeprProduct::$customize_profile_fields_str, '1', 'publish', MeprProduct::$cpt);

    $count = $wpdb->get_var($q);

    //If no memberships have customized fields, just return the MeprOptions->custom_fields
    if(empty($count)) { return $mepr_options->custom_fields; }

    //If the user hasn't purchased anything, and at least one membership has customized fields just show all fields
    $prods = $this->active_product_subscriptions('products');
    $prods = array_unique($prods);

    if(empty($prods)) { return array(); }

    //Loop through the memberships and get a unique array of slugs
    foreach($prods as $p) {
      if($p->customize_profile_fields) {
        $slugs = array_merge($slugs, $p->custom_profile_fields);
      }
      else {
        // If at least one membership has customized fields disabled, just return all the fields
        return $mepr_options->custom_fields;
      }
    }

    //Not sure why this would happen, but if it does let's return an empty array
    if(empty($slugs)) { return array(); }

    //array unique the slugs for fun (SORT_STRING requires php 5.2.9+
    $slugs = array_unique($slugs, SORT_STRING);

    // Pull in the fields that are actually called out in the slugs array
    foreach($mepr_options->custom_fields as $row) {
      if(in_array($row->field_key, $slugs)) { $rows[] = $row; }
    }

    return $rows;
  }

  // We have to bypass the magic attribute for this since it's a special property
  public function set_password($password) {
    $this->rec->user_pass = $password;
  }

  public function update_txn_meta() {
    $latest_txn = $this->latest_txn;
    if($latest_txn != false && $latest_txn instanceof MeprTransaction && $latest_txn->id > 0) {
      update_user_meta($this->ID, 'mepr_latest_txn_date', $latest_txn->created_at);
    }
    else {
      update_user_meta($this->ID, 'mepr_latest_txn_date', false);
    }

    update_user_meta($this->ID, 'mepr_txn_count', $this->txn_count);
    update_user_meta($this->ID, 'mepr_active_txn_count', $this->active_txn_count);
    update_user_meta($this->ID, 'mepr_expired_txn_count', $this->expired_txn_count);
    update_user_meta($this->ID, 'mepr_total_spent', $this->total_spent);
    update_user_meta($this->ID, 'mepr_memberships', $this->memberships);
  }

  public static function list_table( $order_by = '',
                                     $order = '',
                                     $paged = '',
                                     $search = '',
                                     $search_field = 'any',
                                     $perpage = 10,
                                     $params = null,
                                     $include_fields = false ) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    if(is_null($params)) { $params=$_GET; }

    $mepr_options = MeprOptions::fetch();

    if(empty($order_by)) {
      $order_by = 'registered';
      $order = 'DESC';
    }

    $cols = array(
      'ID' => 'u.ID',
      'username' => 'u.user_login',
      'email' => 'u.user_email',
      'name' => 'CONCAT(pm_last_name.meta_value, ", ", pm_first_name.meta_value)',
      'first_name' => 'pm_first_name.meta_value',
      'last_name' => 'pm_last_name.meta_value',
      'txn_count' => 'IFNULL(m.txn_count,0)',
      'active_txn_count' => 'IFNULL(m.active_txn_count,0)',
      'expired_txn_count' => 'IFNULL(m.expired_txn_count,0)',
      'trial_txn_count' => 'IFNULL(m.trial_txn_count,0)',
      'sub_count' => 'IFNULL(m.sub_count,0)',
      'active_sub_count' => 'IFNULL(m.active_sub_count,0)',
      'pending_sub_count' => 'IFNULL(m.pending_sub_count,0)',
      'suspended_sub_count' => 'IFNULL(m.suspended_sub_count,0)',
      'cancelled_sub_count' => 'IFNULL(m.cancelled_sub_count,0)',
      'latest_txn_date' => 'IFNULL(latest_txn.created_at,NULL)',
      'first_txn_date' => 'IFNULL(first_txn.created_at,NULL)',
      'status' => 'CASE WHEN active_txn_count>0 THEN "active" WHEN trial_txn_count>0 THEN "active" WHEN expired_txn_count>0 THEN "expired" ELSE "none" END',
      'memberships' => 'IFNULL(m.memberships,"")',
      'last_login_date' => 'IFNULL(last_login.created_at, NULL)',
      'login_count' => 'IFNULL(m.login_count,0)',
      'total_spent' => 'IFNULL(m.total_spent,0.00)',
      'registered' => 'u.user_registered',
    );

    $args = array();

    if(is_multisite()) {
      //$blog_id = get_current_blog_id();
      //$blog_user_ids = get_users(array('blog_id'=>$blog_id,'fields'=>'ID'));
      //$args[] = 'u.ID IN (' . implode(',',$blog_user_ids) . ')';

      $args[] = $wpdb->prepare("
          (SELECT COUNT(*)
             FROM {$wpdb->usermeta} AS um_cap
            WHERE um_cap.user_id=u.ID
              AND um_cap.meta_key=%s) > 0
        ",
        $wpdb->get_blog_prefix() . 'user_level'
      );
    }

    if(isset($params['month']) && is_numeric($params['month'])) {
      $args[] = $wpdb->prepare("MONTH(u.user_registered) = %s",$params['month']);
    }

    if(isset($params['day']) && is_numeric($params['day'])) {
      $args[] = $wpdb->prepare("DAY(u.user_registered) = %s",$params['day']);
    }

    if(isset($params['year']) && is_numeric($params['year'])) {
      $args[] = $wpdb->prepare("YEAR(u.user_registered) = %s",$params['year']);
    }

    if(isset($params['status']) && $params['status'] != 'all') {
      if($params['status']=='active') {
        $args[] = '(m.active_txn_count > 0 OR m.trial_txn_count > 0)';
      }
      else if($params['status']=='expired') {
        $args[] = 'm.active_txn_count <= 0';
        $args[] = 'm.expired_txn_count > 0';
        $args[] = 'm.trial_txn_count <= 0';
      }
      else if($params['status']=='none') {
        $args[] = 'm.active_txn_count <= 0';
        $args[] = 'm.expired_txn_count <= 0';
        $args[] = 'm.trial_txn_count <= 0';
      }
    }

    if(isset($params['membership']) && !empty($params['membership']) && is_numeric($params['membership'])) {
      // $args[] = $wpdb->prepare("%s IN (m.memberships)",$params['membership']);
      $args[] = $wpdb->prepare("m.memberships RLIKE '(^|,)%d(,|$)'", $params['membership']);
    }

    if(isset($params['prd_id']) && !empty($params['prd_id']) && is_numeric($params['prd_id'])) {
      // $args[] = $wpdb->prepare("%s IN (m.memberships)",$params['prd_id']);
      $args[] = $wpdb->prepare("m.memberships RLIKE '(^|,)%d(,|$)'", $params['prd_id']);
    }

    $joins = array(
      "LEFT JOIN {$wpdb->usermeta} AS pm_first_name ON pm_first_name.user_id = u.ID AND pm_first_name.meta_key='first_name'",
      "LEFT JOIN {$wpdb->usermeta} AS pm_last_name ON pm_last_name.user_id = u.ID AND pm_last_name.meta_key='last_name'",
      "/* IMPORTANT */ JOIN {$mepr_db->members} AS m ON m.user_id=u.ID",
      "LEFT JOIN {$mepr_db->transactions} AS first_txn ON m.first_txn_id=first_txn.id",
      "LEFT JOIN {$mepr_db->transactions} AS latest_txn ON m.latest_txn_id=latest_txn.id",
      "LEFT JOIN {$mepr_db->events} AS last_login ON m.last_login_id=last_login.id",
    );

    // Include custom fields in results?
    if($include_fields) {
      $custom_fields = array_merge($mepr_options->address_fields, $mepr_options->custom_fields);
      foreach($custom_fields as $i => $field) {
        $col = "pm_col_{$i}";
        $cols[$field->field_key] = $wpdb->prepare("
            IFNULL(
              (
                SELECT GROUP_CONCAT({$col}.meta_value)
                  FROM {$wpdb->usermeta} AS {$col}
                 WHERE {$col}.meta_key=%s
                   AND {$col}.user_id=u.ID
                 GROUP BY {$col}.user_id
              ),
              ''
            )
          ",
          $field->field_key
        );
      }
    }

    return MeprDb::list_table($cols, "{$wpdb->users} AS u", $joins, $args, $order_by, $order, $paged, $search, $search_field, $perpage); //, false, true);
  }

  /***** MAGIC METHOD HANDLERS *****/
  protected function mgm_first_txn($mgm, $val = '') {
    global $wpdb;
    $mepr_db = MeprDb::fetch();
    $where = '';

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s, %s)
             ORDER BY t.created_at ASC
             LIMIT 1
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str
        );

        $id = $wpdb->get_var($q);
        return empty($id) ? false : new MeprTransaction($id);
      default:
        return false;
    }
  }

  protected function mgm_latest_txn($mgm, $val = '') {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s, %s)
             ORDER BY t.created_at DESC
             LIMIT 1
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str
        );

        $id = $wpdb->get_var($q);
        return empty($id) ? false : new MeprTransaction($id);
      default:
        return false;
    }
  }

  protected function mgm_txn_count($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id=%d
               AND t.status IN (%s,%s)
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str
        );

        return $wpdb->get_var($q);
      default:
        return false;
    }
  }

  protected function mgm_active_txn_count($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id=%d
               AND t.status IN (%s,%s)
               AND (
                 t.expires_at IS NULL
                 OR t.expires_at = %s
                 OR t.expires_at > %s
               )
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str,
          MeprUtils::db_lifetime(),
          MeprUtils::db_now()
        );

        return $wpdb->get_var($q);
      default:
        return false;
    }
  }

  protected function mgm_expired_txn_count($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s,%s)
               AND ( (
                   t.expires_at IS NOT NULL
                   AND t.expires_at <> %s
                   AND t.expires_at < %s
                 )
               )
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str,
          MeprUtils::db_lifetime(),
          MeprUtils::db_now()
        );

        return $wpdb->get_var($q);
      default:
        return false;
    }
  }

  protected function mgm_total_spent($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT sum(t.total)
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status IN (%s,%s)
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str
        );

        return MeprUtils::format_float($wpdb->get_var($q));
      default:
        return false;
    }
  }

  protected function mgm_confirmations($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
          $this->rec->ID,
          MeprTransaction::$confirmed_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_payments($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
               AND t.amount > 0
          ",
          $this->rec->ID,
          MeprTransaction::$complete_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_transactions($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
          ",
          $this->rec->ID
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_refunds($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
          $this->rec->ID,
          MeprTransaction::$refunded_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_pending_payments($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
          $this->rec->ID,
          MeprTransaction::$pending_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_failed_payments($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND t.status = %s
          ",
          $this->rec->ID,
          MeprTransaction::$failed_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $txns = array();
        foreach($ids as $id) {
          $txns[] = new MeprTransaction($id);
        }

        return $txns;
      default:
        return false;
    }
  }

  protected function mgm_memberships($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT DISTINCT t.product_id
              FROM {$mepr_db->transactions} AS t
             WHERE t.user_id = %d
               AND (
                 t.expires_at IS NULL
                 OR t.expires_at = %s
                 OR t.expires_at > %s
               )
               AND ( (
                   t.txn_type IN (%s,%s,%s)
                   AND t.status=%s
                 ) OR (
                   t.txn_type=%s
                   AND t.status=%s
                 )
               )
          ",
          $this->rec->ID,
          MeprUtils::db_lifetime(),
          MeprUtils::db_now(),
          MeprTransaction::$payment_str,
          MeprTransaction::$sub_account_str,
          MeprTransaction::$fallback_str,
          MeprTransaction::$complete_str,
          MeprTransaction::$subscription_confirmation_str,
          MeprTransaction::$confirmed_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $memberships = array();
        foreach($ids as $id) {
          $memberships[] = new MeprProduct($id);
        }

        return $memberships;
      default:
        return false;
    }
  }

  protected function mgm_logins($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT e.id
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
          ",
          $this->rec->ID,
          MeprEvent::$users_str,
          MeprEvent::$login_event_str
        );

        $ids = $wpdb->get_col($q);

        if(empty($ids)) {
          return false;
        }

        $logins = array();
        foreach($ids as $id) {
          $logins[] = new MeprEvent($id);
        }

        return $logins;
      default:
        return false;
    }
  }

  protected function mgm_last_login($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT e.id
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
             ORDER BY e.created_at DESC
             LIMIT 1
          ",
          $this->rec->ID,
          MeprEvent::$users_str,
          MeprEvent::$login_event_str
        );

        $eid = $wpdb->get_var($q);
        return !empty($eid) ? new MeprEvent($eid) : false;
      default:
        return false;
    }
  }

  protected function mgm_login_count($mgm, $val = '') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT COUNT(*)
              FROM {$mepr_db->events} AS e
             WHERE e.evt_id=%d
               AND e.evt_id_type=%s
               AND e.event=%s
          ",
          $this->rec->ID,
          MeprEvent::$users_str,
          MeprEvent::$login_event_str
        );

        return $wpdb->get_var($q);
      default:
        return false;
    }
  }

  // MEMBER DATA METHODS

  /*

  Member Data is statically stored, dynamic data which is acquired by utilizing the member_data static
  method. This will run some moderately expensive queries which will be cached in the members table
  so that the expensive queries can be run once, at the point when individual members are updated.
  Utilizing this approach reduces the strain on the server and increases performance because these
  queries are only run once when a user is updated and are usually only run for one member at a time.

  */
  public static function member_data($u=null,Array $cols=array()) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $select_cols = array();

    // empty cols indicates we're getting all columns
    if(empty($cols) || in_array('first_txn_id',$cols))        { $select_cols['first_txn_id']        = self::member_col_first_txn_id(); }
    if(empty($cols) || in_array('latest_txn_id',$cols))       { $select_cols['latest_txn_id']       = self::member_col_latest_txn_id(); }
    if(empty($cols) || in_array('txn_count',$cols))           { $select_cols['txn_count']           = self::member_col_txn_count(); }
    if(empty($cols) || in_array('expired_txn_count',$cols))   { $select_cols['expired_txn_count']   = self::member_col_expired_txn_count(); }
    if(empty($cols) || in_array('active_txn_count',$cols))    { $select_cols['active_txn_count']    = self::member_col_active_txn_count(); }
    if(empty($cols) || in_array('trial_txn_count',$cols))     { $select_cols['trial_txn_count']     = self::member_col_trial_txn_count(); }
    if(empty($cols) || in_array('sub_count',$cols))           { $select_cols['sub_count']           = self::member_col_sub_count(); }
    if(empty($cols) || in_array('pending_sub_count',$cols))   { $select_cols['pending_sub_count']   = self::member_col_sub_count(MeprSubscription::$pending_str); }
    if(empty($cols) || in_array('active_sub_count',$cols))    { $select_cols['active_sub_count']    = self::member_col_sub_count(MeprSubscription::$active_str); }
    if(empty($cols) || in_array('suspended_sub_count',$cols)) { $select_cols['suspended_sub_count'] = self::member_col_sub_count(MeprSubscription::$suspended_str); }
    if(empty($cols) || in_array('cancelled_sub_count',$cols)) { $select_cols['cancelled_sub_count'] = self::member_col_sub_count(MeprSubscription::$cancelled_str); }
    if(empty($cols) || in_array('memberships',$cols))         { $select_cols['memberships']         = self::member_col_memberships(); }
    if(empty($cols) || in_array('last_login_id',$cols))       { $select_cols['last_login_id']       = self::member_col_last_login_id(); }
    if(empty($cols) || in_array('login_count',$cols))         { $select_cols['login_count']         = self::member_col_login_count(); }
    if(empty($cols) || in_array('total_spent',$cols))         { $select_cols['total_spent']         = self::member_col_total_spent(); }

    $selects = '';
    foreach($select_cols as $col_name => $col_query) {
      $selects .= "\n{$col_query} AS {$col_name},";
    }
    //$selects = rtrim($selects, ',');

    $where = self::get_member_where($u);

    $q = $wpdb->prepare("
        SELECT
          u.ID AS user_id,
          {$selects}
          %s AS updated_at
        FROM {$wpdb->users} AS u
        {$where}
      ",
      MeprUtils::db_now()
    );

    if(!is_null($u)) {
      $q .= "
        LIMIT 1
      ";

      return $wpdb->get_row($q);
    }

    return $wpdb->get_results($q);
  }

/*** SQL FOR MEMBER COLUMNS ***/

  private static function member_col_first_txn_id() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT t.id
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
         ORDER BY t.created_at ASC
         LIMIT 1
      )",
      MeprTransaction::$complete_str
    );
  }

  private static function member_col_latest_txn_id() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT t.id
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
         ORDER BY t.created_at DESC
         LIMIT 1
      )",
      MeprTransaction::$complete_str
    );
  }

  private static function member_col_txn_count() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.txn_type IN (%s, %s)
      )",
      MeprTransaction::$payment_str,
      MeprTransaction::$sub_account_str
    );
  }

  private static function member_col_expired_txn_count() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND t.status = %s
           AND t.txn_type IN (%s, %s)
           AND ( (
               t.expires_at IS NOT NULL
               AND t.expires_at <> %s
               AND t.expires_at < %s
             )
           )
      )",
      MeprTransaction::$complete_str,
      MeprTransaction::$payment_str,
      MeprTransaction::$sub_account_str,
      MeprUtils::db_lifetime(),
      MeprUtils::db_now()
    );
  }

  private static function member_col_active_txn_count() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.status = %s
           AND t.txn_type IN (%s, %s)
           AND (
             t.expires_at IS NULL
             OR t.expires_at = %s
             OR t.expires_at > %s
           )
      )",
      MeprTransaction::$complete_str,
      MeprTransaction::$payment_str,
      MeprTransaction::$sub_account_str,
      MeprUtils::db_lifetime(),
      MeprUtils::db_now()
    );
  }

  private static function member_col_trial_txn_count() {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT COUNT(*)
          FROM {$mepr_db->transactions} AS t
          JOIN {$mepr_db->subscriptions} AS sub
            ON t.subscription_id = sub.id
         WHERE t.user_id=u.ID
           AND t.txn_type = %s
           AND (
             t.expires_at IS NULL
             OR t.expires_at = %s
             OR t.expires_at > %s
           )
           AND sub.trial IS TRUE
           AND sub.trial_amount = 0.00
      )",
      MeprTransaction::$subscription_confirmation_str,
      MeprUtils::db_lifetime(),
      MeprUtils::db_now()
    );
  }

  private static function member_col_sub_count($status=null) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $where_status = (empty($status) ? '' : $wpdb->prepare('AND s.status=%s', $status));

    return "(
      SELECT COUNT(*)
        FROM {$mepr_db->subscriptions} AS s
       WHERE s.user_id=u.ID
       {$where_status}
    )";
  }

  private static function member_col_memberships() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT GROUP_CONCAT(
                 DISTINCT t.product_id
                 ORDER BY t.product_id
                 SEPARATOR ','
               )
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id = u.ID
           AND (
             t.expires_at > %s
             OR t.expires_at = %s
             OR t.expires_at IS NULL
           )
           AND ( (
                t.txn_type IN (%s,%s,%s)
                AND t.status=%s
             ) OR (
                t.txn_type=%s
                AND t.status=%s
             )
           )
      )",
      MeprUtils::db_now(),
      MeprUtils::db_lifetime(),
      MeprTransaction::$payment_str,
      MeprTransaction::$sub_account_str,
      MeprTransaction::$fallback_str,
      MeprTransaction::$complete_str,
      MeprTransaction::$subscription_confirmation_str,
      MeprTransaction::$confirmed_str
    );
  }

  private static function member_col_last_login_id() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT e.id
          FROM {$mepr_db->events} AS e
         WHERE e.evt_id=u.ID
           AND e.evt_id_type=%s
           AND e.event=%s
         ORDER BY e.created_at DESC
         LIMIT 1
      )",
      MeprEvent::$users_str,
      MeprEvent::$login_event_str
    );
  }

  private static function member_col_login_count() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT COUNT(*)
          FROM {$mepr_db->events} AS e
         WHERE e.evt_id=u.ID
           AND e.evt_id_type=%s
           AND e.event=%s
      )",
      MeprEvent::$users_str,
      MeprEvent::$login_event_str
    );
  }

  private static function member_col_total_spent() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    return $wpdb->prepare("(
        SELECT sum(t.total)
          FROM {$mepr_db->transactions} AS t
         WHERE t.user_id=u.ID
           AND t.status IN (%s,%s)
      )",
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str
    );
  }

  public function update_member_data($cols=array()) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    // Does the table even exist? (fix for Multisite)
    if(!$mepr_db->table_exists($mepr_db->members)) { return; }

    if(!isset($this->ID) || empty($this->ID)) {
      MeprUtils::debug_log("UPDATE_MEMBER_DATA: \$this->ID is either unset or empty or zero");
      return false;
    }

    $data = self::member_data($this->ID,$cols);

    MeprUtils::debug_log("Member Data for {$this->ID}");
    MeprUtils::debug_log("MEMBER DATA: ".MeprUtils::object_to_string($data));

    if(!empty($data) && is_object($data) && isset($data->user_id)) {
      $user_id = array('user_id' => $data->user_id);
      $member = $mepr_db->get_one_record($mepr_db->members, $user_id);

      if(empty($member)) {
        MeprUtils::debug_log("EMPTY MEMBER?!");
        return $mepr_db->create_record($mepr_db->members, $data);
      }
      else {
        MeprUtils::debug_log("MEMBER RECORD: ".MeprUtils::object_to_string($member));
        return $mepr_db->update_record($mepr_db->members, $member->id, $data);
      }
    }
    else {
      MeprUtils::debug_log("PROBLEM WITH MEMBER DATA?!");
    }

    return false;
  }

  // Update all member data that has already been updated or not yet
  public static function update_all_member_data($exclude_already_updated=false, $limit='', $cols=array()) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $where = '';
    if(is_multisite()) {
      //$blog_id = get_current_blog_id();
      //$blog_user_ids = get_users(array('blog_id'=>$blog_id,'fields'=>'ID'));
      //$where = 'WHERE ID IN (' . implode(',',$blog_user_ids) . ')';

      $where = $wpdb->prepare("
        WHERE (SELECT COUNT(*)
                 FROM {$wpdb->usermeta} AS um_cap
                WHERE um_cap.user_id=ID
                  AND um_cap.meta_key=%s) > 0
        ",
        $wpdb->get_blog_prefix() . 'user_level'
      );
    }

    if($exclude_already_updated) {
      if(empty($where)) {
        $where = 'WHERE';
      }
      else {
        $where = "{$where} AND ";
      }

      $where = "{$where} ID NOT IN (SELECT user_id FROM {$mepr_db->members})";
    }
    // No longer a need to do this
    //else {
    //  self::delete_all_member_data();
    //}

    if(!empty($limit)) {
      $limit = "LIMIT {$limit}";
    }

    $q = "SELECT ID FROM {$wpdb->users} {$where} {$limit}";

    $uids = $wpdb->get_col($q);

    foreach($uids as $uid) {
      $u = new MeprUser();

      // We just set the ID here to avoid looking up the ID and
      // it's the only thing we care about in updat_member_data
      $u->ID = $uid;
      $u->update_member_data($cols);
    }
  }

  // Update all member data that was updated at least 1 day ago
  public static function update_existing_member_data($limit) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $interval = '2 DAY';

    $ms_where = '';
    if(is_multisite()) {
      //$blog_id = get_current_blog_id();
      //$blog_user_ids = get_users(array('blog_id'=>$blog_id,'fields'=>'ID'));
      //$ms_where = 'm.user_id IN (' . implode(',',$blog_user_ids) . ') AND';

      $ms_where = $wpdb->prepare("
          (SELECT COUNT(*)
             FROM {$wpdb->usermeta} AS um_cap
            WHERE um_cap.user_id=m.user_id
              AND um_cap.meta_key=%s) > 0
        ",
        $wpdb->get_blog_prefix() . 'user_level'
      );
    }

    $q = $wpdb->prepare("
        SELECT m.user_id
          FROM {$mepr_db->members} AS m
         WHERE {$ms_where} (
                 m.updated_at IS NULL
                 OR m.updated_at = %s
                 OR m.updated_at <= DATE_SUB(%s,INTERVAL {$interval})
               )
         ORDER BY m.updated_at
         LIMIT %d
      ",
      MeprUtils::db_lifetime(),
      MeprUtils::db_now(),
      $limit
    );

    $uids = $wpdb->get_col($q);

    foreach($uids as $uid) {
      $u = new MeprUser();
      $u->ID = $uid;
      $u->update_member_data();
    }
  }

  public function delete_member_data() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();
    $q = $wpdb->prepare("DELETE FROM {$mepr_db->members} WHERE user_id=%s", $this->ID);
    return $wpdb->query($q);
  }

  public static function delete_all_member_data() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();
    $q = "DELETE FROM {$mepr_db->members}";
    return $wpdb->query($q);
  }

  private static function get_member_where($u=null, $id_col='u.ID') {
    global $wpdb;

    $where = '';

    if(!is_null($u)) {
      if(is_array($u)) {
        $uids = implode(',',$u);
        $where = "
          WHERE {$id_col} IN ({$uids})
        ";
      }
      else {
        $where = $wpdb->prepare("
            WHERE {$id_col} = %d
          ",
          $u
        );
      }
    }

    return $where;
  }
} //End class
