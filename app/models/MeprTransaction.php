<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTransaction extends MeprBaseModel implements MeprProductInterface, MeprTransactionInterface {
  /** INSTANCE VARIABLES & METHODS **/
  public function __construct($id = null) {
    if(!is_null($id)) {
      $this->rec = (object)self::get_one($id);
    }
    else {
      $this->rec = (object)array(
        'id'              => 0,
        'amount'          => 0.00,
        'total'           => 0.00,
        'tax_amount'      => 0.00,
        'tax_rate'        => 0.00,
        'tax_desc'        => '',
        'tax_class'       => 'standard',
        'user_id'         => null,
        'product_id'      => null,
        'coupon_id'       => 0,
        'trans_num'       => 'mp-txn-'.uniqid(),
        'status'          => self::$pending_str,
        'txn_type'        => self::$payment_str,
        'response'        => '',
        'gateway'         => 'MeprPayPalGateway',
        'prorated'        => null,
        'ip_addr'         => $_SERVER['REMOTE_ADDR'],
        'created_at'      => null,
        'expires_at'      => null, // 0 = lifetime, null = default expiration for membership
        'subscription_id' => 0
      );
    }
  }

  public function load_from_array($tdata) {
    $this->load_data($tdata);
  }

  public function load_data($tdata) {
    $this->rec = (object)array_merge((array)$this->rec, (array)$tdata);
  }

  // Transaction Types
  public static $payment_str = "payment";
  public static $subscription_confirmation_str = "subscription_confirmation";

  // Statuses
  public static $pending_str   = "pending";
  public static $failed_str    = "failed";
  public static $complete_str  = "complete";
  public static $confirmed_str = "confirmed";
  public static $refunded_str  = "refunded";

  // Static Gateways
  public static $free_gateway_str   = "free";
  public static $manual_gateway_str = "manual";

  public function validate() {
    $mepr_options = MeprOptions::fetch();

    $statuses = array(
      self::$pending_str,
      self::$failed_str,
      self::$complete_str,
      self::$confirmed_str,
      self::$refunded_str
    );

    $gateways = array_merge(
      array_keys($mepr_options->integrations),
      array(self::$free_gateway_str,self::$manual_gateway_str)
    );

    $this->validate_is_currency($this->amount, 0.00, null, 'amount');
    $this->validate_is_numeric($this->user_id, 1, null, 'user_id');
    $this->validate_is_numeric($this->product_id, 1, null, 'product_id');
    $this->validate_is_numeric($this->coupon_id, 0, null, 'coupon_id');
    $this->validate_not_empty($this->trans_num, 'trans_num');
    $this->validate_is_in_array($this->status, $statuses, 'status');
    $this->validate_is_in_array($this->gateway, $gateways, 'gateway');
    $this->validate_is_numeric($this->subscription_id, 0, null, 'subscription_id');
  }

  /** STATIC CRUD METHODS **/
  public static function create($txn) {
    $mepr_db = new MeprDb();

    if(is_null($txn->created_at)) {
      $txn->created_at = MeprUtils::ts_to_mysql_date(time());
    }

    if(is_null($txn->expires_at)) {
      if($txn->subscription_id > 0) {
        $obj = new MeprSubscription($txn->subscription_id);
      }
      else {
        $obj = new MeprProduct($txn->product_id);
      }

      $expires_at_ts = $obj->get_expires_at(strtotime($txn->created_at));

      if(is_null($expires_at_ts)) {
        $txn->expires_at = MeprUtils::mysql_lifetime();
      }
      else {
        $txn->expires_at = MeprUtils::ts_to_mysql_date($expires_at_ts, 'Y-m-d 23:59:59');
      }
    }

    if(is_null($txn->prorated)) {
      $prd = new MeprProduct($txn->product_id);
      $txn->prorated = ( $prd->is_one_time_payment() && $prd->is_prorated() );
    }

    $args = (array)$txn->rec;
    if(empty($txn->expires_at)) { unset($args['expires_at']); }

    return MeprHooks::apply_filters('mepr_create_transaction', $mepr_db->create_record($mepr_db->transactions, $args, false), $args, $txn->user_id);
  }

  public static function update($txn) {
    $mepr_db = new MeprDb();
    $args = (array)$txn->rec;

    return MeprHooks::apply_filters('mepr_update_transaction', $mepr_db->update_record($mepr_db->transactions, $txn->id, $args), $args, $txn->user_id);
  }

  public static function update_partial($id, $args) {
    $mepr_db = new MeprDb();
    $mepr_db->update_record($mepr_db->transactions, $id, $args);
  }

  public function destroy() {
    $mepr_db = new MeprDb();
    $id = $this->id;
    $args = compact('id');
    $transaction = self::get_one($id);

    return MeprHooks::apply_filters('mepr_delete_transaction', $mepr_db->delete_records($mepr_db->transactions, $args), $args);
  }

  /*
  public function delete_by_user_id($user_id)
  {
    $mepr_db = new MeprDb();
    $args = compact('user_id');
    return MeprHooks::apply_filters('mepr_delete_transaction', $mepr_db->delete_records($mepr_db->transactions, $args), $args);
  }
  */

  public static function get_one($id, $return_type = OBJECT) {
    $mepr_db = new MeprDb();
    $args = compact('id');

    return $mepr_db->get_one_record($mepr_db->transactions, $args, $return_type);
  }

  public static function get_one_by_trans_num($trans_num) {
    $mepr_db = new MeprDb();
    $args = compact('trans_num');

    return $mepr_db->get_one_record($mepr_db->transactions, $args);
  }

  public static function get_one_by_subscription_id($subscription_id) {
    if(is_null($subscription_id) || empty($subscription_id) || !$subscription_id) {
      return false;
    }

    $mepr_db = new MeprDb();
    $args = compact('subscription_id');
    return $mepr_db->get_one_record($mepr_db->transactions, $args);
  }

  public static function get_all_by_subscription_id($subscription_id) {
    if(is_null($subscription_id) || empty($subscription_id) || !$subscription_id) {
      return false;
    }

    $mepr_db = new MeprDb();
    $args = compact('subscription_id');

    return $mepr_db->get_records($mepr_db->transactions, $args);
  }

  public static function get_first_subscr_transaction($subscription_id) {
    global $wpdb;

    $mepr_db = new MeprDb();
    $query = "SELECT * FROM {$mepr_db->transactions} WHERE subscription_id=%s ORDER BY created_at LIMIT 1";
    $query = $wpdb->prepare($query, $subscription_id);
    return $wpdb->get_row($query);
  }

  public static function get_count() {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->transactions);
  }

  public static function get_count_by_user_id($user_id) {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->transactions, compact('user_id'));
  }

  public static function get_count_by_user_and_product($user_id, $product_id, $status='complete') {
    $mepr_db = new MeprDb();
    return $mepr_db->get_count($mepr_db->transactions, compact('user_id','product_id','status'));
  }

  public static function get_all($order_by = '', $limit = '') {
    $mepr_db = new MeprDb();
    return $mepr_db->get_records($mepr_db->transactions, array(), $order_by, $limit);
  }

  public static function get_all_by_user_id($user_id, $order_by = '', $limit = '', $exclude_confirmations = false) {
    $mepr_db = new MeprDb();
    $args = array('user_id' => $user_id);

    if($exclude_confirmations) {
      $args['txn_type'] = self::$payment_str;
    }

    return $mepr_db->get_records($mepr_db->transactions, $args, $order_by, $limit);
  }

  public static function get_all_complete_by_user_id( $user_id,
                                                      $order_by = '',
                                                      $limit = '',
                                                      $count = false,
                                                      $exclude_expired = false,
                                                      $include_confirmations = false ) {
    global $wpdb;

    $mepr_db = new MeprDb();
    $fields = $count?'COUNT(*)':'t.*, p.post_title, m.meta_value AS access_url';

    if(!empty($order_by)) { $order_by = "ORDER BY {$order_by}"; }

    if(!empty($limit)) { $limit = "LIMIT {$limit}"; }

    $where = $exclude_expired?"AND (t.expires_at > '".date('c')."' OR t.expires_at = '".MeprUtils::mysql_lifetime()."' OR t.expires_at IS NULL) ":'';

    if($include_confirmations) {
      $where .= $wpdb->prepare( 'AND (( t.txn_type=%s AND t.status=%s ) OR ( t.txn_type=%s AND t.status=%s ))',
                                self::$payment_str,
                                self::$complete_str,
                                self::$subscription_confirmation_str,
                                self::$confirmed_str );
    }
    else {
      $where .= $wpdb->prepare( "AND t.txn_type = %s AND t.status = %s ",
                                self::$payment_str,
                                self::$complete_str );
    }

    $query = "SELECT {$fields}
                FROM {$mepr_db->transactions} AS t
                  JOIN {$wpdb->posts} AS p
                    ON t.product_id = p.ID
                  LEFT JOIN {$wpdb->postmeta} AS m
                    ON t.product_id = m.post_id AND m.meta_key = %s
                WHERE user_id = %d
              {$where}
              {$order_by}
              {$limit}";

    $query = $wpdb->prepare($query, MeprProduct::$access_url_str, $user_id);

    if($count) {
      return $wpdb->get_var($query);
    }
    else {
      return $wpdb->get_results($query);
    }
  }

  public static function completed_transactions_by_date_range($start_date, $end_date) {
    global $wpdb;

    $mepr_db = new MeprDb();
    $products = get_posts(array('numberposts' => -1, 'post_type' => 'memberpressproduct', 'post_status' => 'publish'));
    $selects = array();

    foreach($products as $product) {
      $selects[] = $wpdb->prepare("SELECT * FROM {$mepr_db->transactions} WHERE status='complete' AND created_at >= %s AND created_at <= %s", $start_date, $end_date);
    }

    $query = implode(" UNION ", $selects);

    return $wpdb->get_results($query);
  }

  public static function get_all_ids_by_user_id($user_id, $order_by = '', $limit = '') {
    global $wpdb;

    $mepr_db = new MeprDb();
    $query = "SELECT id FROM {$mepr_db->transactions} WHERE user_id=%d {$order_by}{$limit}";
    $query = $wpdb->prepare($query, $user_id);

    return $wpdb->get_col($query);
  }

  public static function get_all_objects_by_user_id($user_id, $order_by = '', $limit = '') {
    $all_records = self::get_all_by_user_id($user_id, $order_by, $limit);
    $my_objects = array();

    foreach($all_records as $record) {
      $my_objects[] = self::get_stored_object($record->id);
    }

    return $my_objects;
  }

  public static function get_all_objects($order_by = '', $limit = '') {
    $all_records = self::get_all($order_by, $limit);
    $my_objects = array();

    foreach ($all_records as $record) {
      $my_objects[] = self::get_stored_object($record->id);
    }

    return $my_objects;
  }

  public static function get_stored_object($id) {
    static $my_objects;

    if(!isset($my_objects)) {
      $my_objects = array();
    }

    if(!isset($my_objects[$id]) || empty($my_objects[$id]) || !is_object($my_objects[$id])) {
      $my_objects[$id] = new MeprTransaction($id);
    }

    return $my_objects[$id];
  }

  public function store() {
    $old_txn = new self($this->id);

    if(isset($this->id) && !is_null($this->id) && (int)$this->id > 0) {
      $this->id = self::update($this);
    }
    else {
      $this->id = self::create($this);
    }

    if(($this->status == self::$failed_str || $this->status == self::$refunded_str) &&
       ($sub = $this->subscription())) {
      // If we have a failure or refund before the confirmation period
      // is over then we expire the subscription confirmation transaction
      $sub->expire_confirmation_txn();
    }

    //This should happen after everything is done processing including the subscr txn_count
    MeprHooks::do_action('mepr-txn-transition-status', $old_txn->status, $this->status, $this);
    MeprHooks::do_action('mepr-txn-store', $this);
    MeprHooks::do_action('mepr-txn-status-'.$this->status, $this);

    return $this->id;
  }

  /** This method will return an array of transactions that are or have expired.  */
  public static function get_expiring_transactions() {
    global $wpdb;

    $mepr_options = MeprOptions::fetch();
    $mepr_db = new MeprDb();
    $pm_ids = array();

    $pms = $mepr_options->integrations;

    //foreach($pms as $pm)
    //  if(isset($pm['recurrence_type']) && $pm['recurrence_type']=='manual')
    //    $pm_ids[] = $pm['id'];

    $query = "SELECT txn.* FROM {$mepr_db->transactions} AS txn " .
              "WHERE txn.status='complete' AND txn.expires_at <= %s " .
                //"AND txn.gateway IN ('" . implode("','", $pm_ids) . "') " .
                "AND txn.id NOT IN ( SELECT CAST( meta.meta_value AS UNSIGNED INTEGER ) " .
                                      "FROM {$wpdb->usermeta} AS meta " .
                                     "WHERE meta.user_id=txn.user_id " .
                                       "AND meta.meta_key='mepr_renewal' )";

    $query = $wpdb->prepare($query, MeprUtils::ts_to_mysql_date(time()));

    return $wpdb->get_results($query);
  }

  public static function list_table( $order_by = '',
                                     $order = '',
                                     $paged = '',
                                     $search = '',
                                     $perpage = 10,
                                     $params = null ) {
    global $wpdb;
    $mepr_db = new MeprDb();
    if(is_null($params)) { $params=$_GET; }

    $args = array();

    $mepr_options = MeprOptions::fetch();
    $pmt_methods = $mepr_options->payment_methods();

    if(!empty($pmt_methods))
    {
      $pmt_method = '(SELECT CASE tr.gateway';

      foreach($pmt_methods as $method)
        $pmt_method .= $wpdb->prepare(" WHEN %s THEN %s", $method->id, "{$method->label} ({$method->name})");

      $pmt_method .= $wpdb->prepare(" ELSE %s END)", __('Unknown', 'memberpress'));
    }
    else
      $pmt_method = 'tr.gateway';

    $cols = array('id' => 'tr.id',
                  'created_at' => 'tr.created_at',
                  'expires_at' => 'tr.expires_at',
                  'ip_addr' => 'tr.ip_addr',
                  'user_login' => 'm.user_login',
                  'user_email' => 'm.user_email',
                  'fname' => "(SELECT um_fname.meta_value FROM {$wpdb->usermeta} AS um_fname WHERE um_fname.user_id = m.ID AND um_fname.meta_key = 'first_name' LIMIT 1)",
                  'lname' => "(SELECT um_lname.meta_value FROM {$wpdb->usermeta} AS um_lname WHERE um_lname.user_id = m.ID AND um_lname.meta_key = 'last_name' LIMIT 1)",
                  'user_id' => 'm.ID',
                  'product_id' => 'tr.product_id',
                  'product_name' => 'p.post_title',
                  'gateway' => $pmt_method,
                  'subscr_id' => $wpdb->prepare('(SELECT CASE tr.subscription_id WHEN tr.subscription_id IS NULL OR tr.subscription_id=0 THEN %s ELSE ( SELECT mepr_subscr_id_pm.meta_value FROM '.$wpdb->postmeta.' AS mepr_subscr_id_pm WHERE mepr_subscr_id_pm.post_id=tr.subscription_id AND mepr_subscr_id_pm.meta_key=%s LIMIT 1 ) END)', __('None','memberpress'), MeprSubscription::$subscr_id_str),
                  'sub_id' => 'tr.subscription_id',
                  'trans_num' => 'tr.trans_num',
                  'amount' => 'tr.amount',
                  'total' => 'tr.total',
                  'tax_amount' => 'tr.tax_amount',
                  'tax_rate' => 'tr.tax_rate',
                  'tax_class' => 'tr.tax_class',
                  'tax_desc' => 'tr.tax_desc',
                  'status' => 'tr.status'
                 );

    if(isset($params['month']) && is_numeric($params['month']))
      $args[] = $wpdb->prepare("MONTH(tr.created_at) = %s",$params['month']);

    if(isset($params['day']) && is_numeric($params['day']))
      $args[] = $wpdb->prepare("DAY(tr.created_at) = %s",$params['day']);

    if(isset($params['year']) && is_numeric($params['year']))
      $args[] = $wpdb->prepare("YEAR(tr.created_at) = %s",$params['year']);

    // if(isset($params['product']) && $params['product'] != 'all' && is_numeric($params['product']))
      // $args[] = $wpdb->prepare("tr.product_id = %d",$params['product']);

    if(isset($params['prd_id']) && $params['prd_id'] != 'all' && is_numeric($params['prd_id']))
      $args[] = $wpdb->prepare("tr.product_id = %d", $params['prd_id']);

    if(isset($params['status']) && $params['status'] != 'all')
      $args[] = $wpdb->prepare("tr.status = %s", $params['status']);

    if(isset($params['subscription']) && is_numeric($params['subscription']))
      $args[] = $wpdb->prepare("tr.subscription_id = %d",$params['subscription']);

    if(isset($params['transaction']) && is_numeric($params['transaction']))
      $args[] = $wpdb->prepare("tr.id = %d",$params['transaction']);

    if(isset($params['member']) && !empty($params['member']))
      $args[] = $wpdb->prepare("m.user_login = %s",$params['member']);

    if(isset($params['coupon_id']) && !empty($params['coupon_id']))
      $args[] = $wpdb->prepare("tr.coupon_id = %s", $params['coupon_id']);

    // Don't include any subscription confirmation transactions in the list table
    if(!isset($params['include-confirmations'])) {
      $args[] = $wpdb->prepare("tr.txn_type = %s", self::$payment_str);
      $args[] = $wpdb->prepare("tr.status <> %s", self::$confirmed_str);
    }

    if(isset($params['statuses'])) {
      $qry = array();
      foreach($params['statuses'] as $st)
        $qry[] = $wpdb->prepare('tr.status = %s', $st);
      $args[] = '('.implode(' OR ',$qry).')';
    }

    $joins = array( "/* IMPORTANT */ LEFT JOIN {$wpdb->users} AS m ON tr.user_id = m.ID",
                    "/* IMPORTANT */ LEFT JOIN {$wpdb->posts} AS p ON tr.product_id = p.ID"
                  );

    return MeprDb::list_table($cols, "{$mepr_db->transactions} AS tr", $joins, $args, $order_by, $order, $paged, $search, $perpage);
  }

  //Sets membership ID to 0 if for some reason a membership is deleted
  public static function nullify_product_id_on_delete($id) {
    global $wpdb, $post_type;
    $mepr_db = new MeprDb();

    $q = "UPDATE {$mepr_db->transactions}
            SET product_id = 0
            WHERE product_id = %d";

    if($post_type == MeprProduct::$cpt) {
      $wpdb->query($wpdb->prepare($q, $id));
    }
  }

  //Sets user id to 0 if for some reason a user is deleted
  public static function nullify_user_id_on_delete($id) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = "UPDATE {$mepr_db->transactions}
            SET user_id = 0
            WHERE user_id = %d";

    $wpdb->query($wpdb->prepare($q, $id));
  }

  public static function map_subscr_status($status) {
    switch($status) {
      case MeprSubscription::$pending_str:
        return self::$pending_str;
      case MeprSubscription::$active_str:
        return array( self::$complete_str, self::$confirmed_str );
      case MeprSubscription::$suspended_str:
      case MeprSubscription::$cancelled_str:
        return false; // These don't have an equivalent
    }
  }

  public function is_expired($offset = 0) {
    //Check for a lifetime first
    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::mysql_lifetime()) {
      return false;
    }

    $todays_ts = time() + $offset; // use the offset to check when a txn will expire
    $expires_ts = strtotime($this->expires_at);

    return ($this->status == 'complete' && $expires_ts < $todays_ts);
  }

  public function product() {
    static $prd;

    if(!isset($prd) || !($prd instanceof MeprProduct) || $prd->ID != $this->product_id) {
      $prd = new MeprProduct($this->product_id);
    }

    return $prd;
  }

  // Has one through membership
  public function group() {
    $prd = $this->product();

    return $prd->group();
  }

  public function user($force = false) {
    static $usr;

    if($force || !isset($usr) || !($usr instanceof MeprUser) || $usr->ID != $this->user_id) {
      $usr = new MeprUser($this->user_id);
    }

    return $usr;
  }

  public function subscription() {
    if(!isset($this->subscription_id) || empty($this->subscription_id)) {
      return false;
    }

    static $sub;

    if(!isset($sub) || !($sub instanceof MeprSubscription) || $sub->ID != $this->subscription_id) {
      $sub = new MeprSubscription($this->subscription_id);
    }

    //For some reason when the free gateway is invoked a subscription is temporarily created
    //then stored with the txn, then deleted, this causes issues so we need to check here
    //that the $sub actually still exists
    if(!$sub->ID) { return false; }

    return $sub;
  }

  public function coupon() {
    if(!isset($this->coupon_id) || empty($this->coupon_id)) {
      return false;
    }

    static $cpn;

    if(!isset($cpn) || !($cpn instanceof MeprCoupon) || $cpn->ID != $this->coupon_id) {
      $cpn = new MeprCoupon($this->coupon_id);
    }

    return $cpn;
  }

  public function payment_method() {
    $mepr_options = MeprOptions::fetch();
    return $mepr_options->payment_method($this->gateway);
  }

  // Where the magic happens when creating a free transaction ... this is
  // usually called when the price of the membership has been set to zero.
  public static function create_free_transaction($txn) {
    $mepr_options = MeprOptions::fetch();
    $mepr_blogname = get_option('blogname');

    // Just short circuit if the transaction has already completed
    if($txn->status == self::$complete_str)
      return;

    $product = new MeprProduct($txn->product_id);

    //Expires at is now more difficult to calculate with our new membership terms
    if($product->period_type != 'lifetime') { //A free recurring subscription? Nope - let's make it lifetime for free here folks
      $expires_at = MeprUtils::mysql_lifetime();
    }
    else {
      $product_expiration = $product->get_expires_at(strtotime($txn->created_at));

      if(is_null($product_expiration))
        $expires_at = MeprUtils::mysql_lifetime();
      else
        $expires_at = MeprUtils::ts_to_mysql_date($product_expiration, 'Y-m-d 23:59:59');
    }

    $txn->trans_num  = uniqid();
    $txn->status     = self::$complete_str;
    $txn->txn_type   = self::$payment_str;
    $txn->gateway    = self::$free_gateway_str;
    $txn->expires_at = $expires_at;

    // This will only work before maybe_cancel_old_sub is run
    $upgrade = $txn->is_upgrade();
    $downgrade = $txn->is_downgrade();

    $txn->maybe_cancel_old_sub();
    $txn->store();

    // No such thing as a free subscription in MemberPress
    // So let's clean up this mess right now
    if(!empty($txn->subscription_id) && (int)$txn->subscription_id > 0) {
      $sub = new MeprSubscription($txn->subscription_id);

      $txn->subscription_id = 0;
      $txn->store(); //Store txn here, otherwise it will get deleted during $sub->destroy()

      $sub->destroy();
    }

    $free_gateway = new MeprBaseStaticGateway(self::$free_gateway_str, __('Free', 'memberpress'), __('Free', 'memberpress'));

    if($upgrade) {
      $free_gateway->upgraded_sub($txn);
      $free_gateway->send_upgraded_txn_notices($txn);
    }
    elseif($downgrade) {
      $free_gateway->downgraded_sub($txn);
      $free_gateway->send_downgraded_txn_notices($txn);
    }

    $free_gateway->send_signup_notices($txn);
    // $free_gateway->send_transaction_receipt_notices($txn); //Maybe don't need to send a receipt for a free txn

    $sanitized_title = sanitize_title($product->post_title);
    MeprUtils::wp_redirect($mepr_options->thankyou_page_url("membership={$sanitized_title}&trans_num={$txn->trans_num}"));
  }

  public function is_upgrade() {
    return $this->is_upgrade_or_downgrade('upgrade');
  }

  public function is_downgrade() {
    return $this->is_upgrade_or_downgrade('downgrade');
  }

  public function is_upgrade_or_downgrade($type = false) {
    $prd = $this->product();
    $usr = $this->user();

    return ($prd->is_upgrade_or_downgrade($type, $usr));
  }

  public function is_one_time_payment() {
    $prd = $this->product();

    return ($prd->is_one_time_payment() || !$this->subscription());
  }

  /** Used by one-time payments **/
  public function maybe_cancel_old_sub() {
    $mepr_options = MeprOptions::fetch();

    $evt_txn = false;
    if($this->is_upgrade_or_downgrade() && $this->is_one_time_payment()) {
      $usr = $this->user();
      $grp = $this->group();

      if(($old_sub = $usr->subscription_in_group($grp->ID))) {
        $old_sub->expire_txns(); //Expire associated transactions for the old subscription
        $_REQUEST['silent'] = true; // Don't want to send cancellation notices
        $old_sub->cancel();
        $evt_txn = $old_sub->latest_txn();
      }
      elseif(($old_lifetime_txn = $usr->lifetime_subscription_in_group($grp->ID)) && $old_lifetime_txn->id != $this->id) {
        $old_lifetime_txn->expires_at = MeprUtils::ts_to_mysql_date(time() - MeprUtils::days(1));
        $old_lifetime_txn->store();
        $evt_txn = $old_lifetime_txn;
      }

      if($evt_txn !== false && $evt_txn instanceof MeprTransaction) {
        // We just catch the hooks from these events
        MeprEvent::record('subscription-changed', $evt_txn, $this->id);
      }
    }

  }

  /** Convenience method to determine what we can do
    * with the gateway associated with the transaction
    */
  public function can($cap) {
    // if the status isn't complete then the refund can't happen
    if($cap=='process-refunds' && $this->status!=MeprTransaction::$complete_str) {
      return false;
    }

    $pm = $this->payment_method();

    if(!($pm instanceof MeprBaseRealGateway)) { return false; }

    if($cap=='process-refunds' && $pm instanceof MeprAuthorizeGateway)
      return ($pm->can($cap) &&
              !empty($this->response) &&
              $res = json_decode($this->response) &&
              isset($res->authorization_code) &&
              ( ( $sub = $this->subscription() &&
                  !empty($sub->cc_last4) &&
                  !empty($sub->cc_exp_month) &&
                  !empty($sub->cc_exp_year) ) ||
                ( !empty($res->cc_last4) &&
                  !empty($res->cc_exp_month) &&
                  !empty($res->cc_exp_year) ) ) );

    return $pm->can($cap);
  }

  public function days_in_this_period() {
    $mepr_options = MeprOptions::fetch();

    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::mysql_lifetime()) {
      return 'lifetime';
    }

    $time_in_this_period = (strtotime($this->expires_at) + MeprUtils::days($mepr_options->grace_expire_days)) - strtotime($this->created_at);

    return intval(round(($time_in_this_period / MeprUtils::days(1))));
  }

  public function days_till_expiration() {
    $mepr_options = MeprOptions::fetch();
    $now = time();

    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::mysql_lifetime()) {
      return 'lifetime';
    }

    $expires_at = strtotime($this->expires_at) + MeprUtils::days($mepr_options->grace_expire_days);

    if( $expires_at <= $now ||
        !in_array($this->status,
                  array(self::$complete_str,
                        self::$confirmed_str)) )
    { return 0; }

    // round and provide an integer ... lest we screw everything up
    return intval(round((($expires_at - $now) / MeprUtils::days(1))));
  }

  public function refund() {
    if($this->can('process-refunds')) {
      try {
        $pm = $this->payment_method();
        return $pm->process_refund($this);
      }
      catch(Exception $e) {
        return false;
      }
    }

    return false;
  }

  public static function txn_exists( $trans_num ) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("SELECT COUNT(*) FROM {$mepr_db->transactions} AS tr WHERE tr.trans_num=%s", $trans_num);
    $txn_count = $wpdb->get_var($q);

    return ((int)$txn_count > 0);
  }

  // Used for txn-expired
  public static function get_expired_txns() {
    global $wpdb;
    $mepr_db = new MeprDb();

    // Expiring Transactions
    $query = $wpdb->prepare( "
      SELECT tr.id,
             IF( tr.subscription_id = 0, 'none',
                 ( SELECT pm_sub_status.meta_value
                     FROM {$wpdb->postmeta} AS pm_sub_status
                    WHERE pm_sub_status.post_id = tr.subscription_id
                      AND pm_sub_status.meta_key=%s
                    LIMIT 1 ) ) AS sub_status
        FROM {$mepr_db->transactions} AS tr
       WHERE tr.expires_at <> %s
         AND tr.status IN ( %s, %s )
         AND DATE_ADD( tr.expires_at, INTERVAL 12 HOUR ) <= %s
         AND DATE_ADD( DATE_ADD( tr.expires_at, INTERVAL 12 HOUR ), INTERVAL 2 DAY ) >= %s
         AND ( SELECT ev.id
                 FROM {$mepr_db->events} AS ev
                WHERE ev.evt_id=tr.id
                  AND ev.evt_id_type='transactions'
                  AND (ev.event='expired' OR ev.event='transaction-expired')
                LIMIT 1 ) IS NULL
      ORDER BY tr.expires_at
      ",
      MeprSubscription::$status_str,
      MeprUtils::mysql_lifetime(),
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str,
      MeprUtils::mysql_now(),
      MeprUtils::mysql_now()
    );

    $res = $wpdb->get_results($query);

    return $res;
  }

  /** This returns a count of all the transactions that are like this one */
  public function txn_count() {
    return self::get_count_by_user_and_product($this->user_id, $this->product_id, $this->status);
  }

  public function apply_tax($subtotal, $num_decimals=2) {
    $mepr_options = MeprOptions::fetch();
    $usr = $this->user();
    $prd = $this->product();
    $calculate_taxes = get_option('mepr_calculate_taxes');
    //$subtotal = MeprUtils::format_float($subtotal,$num_decimals);

    // Now try to calculate tax info from the user info
    if($calculate_taxes && !$prd->tax_exempt && $usr->ID != 0) {
      list($this->amount, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = $usr->calculate_tax($subtotal,$num_decimals);
    }
    else { // If all else fails, let's blank out the tax info
      list($this->amount, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = array($subtotal, $subtotal, 0.00, 0.00, '', 'standard');
    }
  }

  /** Sets up the transaction total, subtotal and tax based on a subtotal value.
   * This method also checks for inclusive vs exclusive tax.
   */
  public function set_subtotal($subtotal) {
    $mepr_options = MeprOptions::fetch();
    if($mepr_options->attr('tax_calc_type')=='inclusive') {
      $usr = $this->user();
      $subtotal = $usr->calculate_subtotal($subtotal);
    }

    $this->apply_tax($subtotal);
  }

  /** Sets up the transaction total, subtotal and tax based on a gross value.
   * This will never check for tax inclusion because since it's the gross
   *kit doesn't matter (since we already know the gross amount).
   */
  public function set_gross($gross) {
    $usr = $this->user();
    $subtotal = $usr->calculate_subtotal($gross);
    $this->apply_tax($subtotal);
  }

  public function checkout_url() {
    $mepr_options = MeprOptions::fetch();
    $payment_url = get_permalink($this->product_id);
    $delim = MeprAppCtrl::get_param_delimiter_char($payment_url);
    $encoded_id = urlencode(MeprUtils::base36_encode($this->id));
    $payment_url = "{$payment_url}{$delim}action=checkout&txn={$encoded_id}"; //Base64 encoding or something?

    if(($pm = $mepr_options->payment_method($this->gateway)) && $pm instanceof MeprBaseRealGateway && $pm->force_ssl()) {
      $payment_url = preg_replace('!^(https?:)?//!','https://',$payment_url);
    }

    return $payment_url;
  }

} //End class

