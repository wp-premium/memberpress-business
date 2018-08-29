<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTransaction extends MeprBaseModel implements MeprProductInterface, MeprTransactionInterface {
  /** INSTANCE VARIABLES & METHODS **/
  public function __construct($obj = null) {
    $this->initialize(
      array(
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
        'trans_num'       => MeprTransaction::generate_trans_num(),
        'status'          => self::$pending_str,
        'txn_type'        => self::$payment_str,
        'gateway'         => 'manual',
        'prorated'        => null,
        'created_at'      => null,
        'expires_at'      => null, // 0 = lifetime, null = default expiration for membership
        'subscription_id' => 0,
        'corporate_account_id' => 0,
        'parent_transaction_id' => 0,
      ),
      $obj
    );
  }

  // Transaction Types
  public static $payment_str                   = 'payment';
  public static $subscription_confirmation_str = 'subscription_confirmation';
  public static $sub_account_str               = 'sub_account';
  public static $fallback_str                  = 'fallback';

  // Statuses
  public static $pending_str   = 'pending';
  public static $failed_str    = 'failed';
  public static $complete_str  = 'complete';
  public static $confirmed_str = 'confirmed';
  public static $refunded_str  = 'refunded';

  // Static Gateways
  public static $free_gateway_str     = 'free';
  public static $manual_gateway_str   = 'manual';
  public static $fallback_gateway_str = 'fallback';

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

    if(is_null($txn->created_at) || empty($txn->created_at)) {
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

      if(is_null($expires_at_ts) || empty($expires_at_ts)) {
        $txn->expires_at = MeprUtils::db_lifetime();
      }
      else {
        $txn->expires_at = MeprUtils::ts_to_mysql_date($expires_at_ts, 'Y-m-d 23:59:59');
      }
    }

    if(is_null($txn->prorated)) {
      $prd = new MeprProduct($txn->product_id);
      $txn->prorated = ( $prd->is_one_time_payment() && $prd->is_prorated() );
    }

    $args = (array)$txn->get_values();
    if(empty($txn->expires_at)) { unset($args['expires_at']); } // Let the DB default these to 0000-00-00 00:00:00

    return MeprHooks::apply_filters('mepr_create_transaction', $mepr_db->create_record($mepr_db->transactions, $args, false), $args, $txn->user_id);
  }

  public static function update($txn) {
    $mepr_db = new MeprDb();
    $args = (array)$txn->get_values();

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

    MeprHooks::do_action('mepr_txn_destroy', $this);
    MeprHooks::do_action('mepr_pre_delete_transaction', $this);

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
                                                      $include_confirmations = false,
                                                      $include_custom_where = false ) {
    global $wpdb;

    $mepr_db = new MeprDb();
    $fields = $count?'COUNT(*)':'t.*, p.post_title, m.meta_value AS access_url';

    if(!empty($order_by)) { $order_by = "ORDER BY {$order_by}"; }

    if(!empty($limit)) { $limit = "LIMIT {$limit}"; }

    $where = $exclude_expired?"AND (t.expires_at > '".date('c')."' OR t.expires_at = '".MeprUtils::db_lifetime()."' OR t.expires_at IS NULL) ":'';

    if($include_confirmations) {
      // Also include sub_account transactions if there are any
      $where .= $wpdb->prepare(
        'AND (( t.txn_type IN (%s,%s,%s) AND t.status=%s ) OR ( t.txn_type=%s AND t.status=%s )) ',
        self::$payment_str,
        self::$sub_account_str,
        self::$fallback_str,
        self::$complete_str,
        self::$subscription_confirmation_str,
        self::$confirmed_str
      );
    }
    else {
      $where .= $wpdb->prepare(
        'AND t.txn_type = %s AND t.status = %s ',
        self::$payment_str,
        self::$complete_str
      );
    }

    if($include_custom_where) {
      $where .= MeprHooks::apply_filters('mepr_transaction_get_complete_by_user_id_custom_where', $where, $user_id);
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

    $query = $wpdb->prepare("SELECT * FROM {$mepr_db->transactions} WHERE status='complete' AND created_at >= %s AND created_at <= %s", $start_date, $end_date);

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

    //TODO - Add real validation here
    if((int)$this->user_id <= 0) { return $this->id; }

    //Force 23:59:59 to help cover some overlaps
    if(isset($this->expires_at) && !empty($this->expires_at) && $this->expires_at != MeprUtils::db_lifetime()) {
      $this->expires_at = MeprUtils::ts_to_mysql_date(strtotime($this->expires_at), 'Y-m-d 23:59:59');
    }

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
    MeprHooks::do_action('mepr-txn-store', $this, $old_txn); // 2018-03-10 BW: now including old_txn to allow for comparisons
    MeprHooks::do_action('mepr-txn-status-'.$this->status, $this);

    return $this->id;
  }

  /** This method will return an array of transactions that are or have expired.  */
  public static function get_expiring_transactions() {
    global $wpdb;

    $mepr_options = MeprOptions::fetch();
    $mepr_db = new MeprDb();

    // TODO: Modify this function and query to work for expiring trials as well.
    $query = $wpdb->prepare("
      SELECT txn.*
      FROM {$mepr_db->transactions} AS txn
        LEFT JOIN {$mepr_db->events} AS e
          ON e.evt_id = txn.id
          AND e.event = 'transaction-expired'
          AND e.evt_id_type = 'transactions'
      WHERE txn.status='complete'
        AND txn.user_id > 0
        AND txn.expires_at BETWEEN DATE_SUB(%s,INTERVAL 2 DAY) AND %s
        AND e.id IS NULL
      ",
      MeprUtils::now(),
      MeprUtils::ts_to_mysql_date(time())
    );

    return $wpdb->get_results($query);
  }

  public static function list_table( $order_by = '',
                                     $order = '',
                                     $paged = '',
                                     $search = '',
                                     $search_field = 'any',
                                     $perpage = 10,
                                     $params = null ) {
    global $wpdb;
    $mepr_db = new MeprDb();
    if(is_null($params)) { $params=$_GET; }

    $args = array();

    $mepr_options = MeprOptions::fetch();
    $pmt_methods = $mepr_options->payment_methods();

    if(!empty($pmt_methods)) {
      $pmt_method = '(SELECT CASE tr.gateway';

      foreach($pmt_methods as $method) {
        $pmt_method .= $wpdb->prepare(" WHEN %s THEN %s", $method->id, "{$method->label} ({$method->name})");
      }

      $pmt_method .= $wpdb->prepare(" ELSE %s END)", __('Unknown', 'memberpress'));
    }
    else {
      $pmt_method = 'tr.gateway';
    }

    $cols = array(
      'id' => 'tr.id',
      'created_at' => 'tr.created_at',
      'expires_at' => 'tr.expires_at',
      'user_login' => 'm.user_login',
      'user_email' => 'm.user_email',
      'first_name' => "(SELECT um_fname.meta_value FROM {$wpdb->usermeta} AS um_fname WHERE um_fname.user_id = m.ID AND um_fname.meta_key = 'first_name' LIMIT 1)",
      'last_name' => "(SELECT um_lname.meta_value FROM {$wpdb->usermeta} AS um_lname WHERE um_lname.user_id = m.ID AND um_lname.meta_key = 'last_name' LIMIT 1)",
      'user_id' => 'm.ID',
      'product_id' => 'tr.product_id',
      'product_name' => 'p.post_title',
      'gateway' => $pmt_method,
      'gateway_id' => 'tr.gateway',
      'subscr_id' => $wpdb->prepare('IFNULL(sub.subscr_id, %s)', __('None','memberpress')),
      'sub_id' => 'tr.subscription_id',
      'trans_num' => 'tr.trans_num',
      'amount' => 'tr.amount',
      'total' => 'tr.total',
      'tax_amount' => 'tr.tax_amount',
      'tax_rate' => 'tr.tax_rate',
      'tax_class' => 'tr.tax_class',
      'tax_desc' => 'tr.tax_desc',
      'status' => 'tr.status',
      'coupon_id' => 'tr.coupon_id'
    );

    if(isset($params['month']) && is_numeric($params['month'])) {
      $args[] = $wpdb->prepare("MONTH(tr.created_at) = %s",$params['month']);
    }

    if(isset($params['day']) && is_numeric($params['day'])) {
      $args[] = $wpdb->prepare("DAY(tr.created_at) = %s",$params['day']);
    }

    if(isset($params['year']) && is_numeric($params['year'])) {
      $args[] = $wpdb->prepare("YEAR(tr.created_at) = %s",$params['year']);
    }

    // if(isset($params['product']) && $params['product'] != 'all' && is_numeric($params['product'])) {
      // $args[] = $wpdb->prepare("tr.product_id = %d",$params['product']);
    // }

    if(isset($params['prd_id']) && $params['prd_id'] != 'all' && is_numeric($params['prd_id'])) {
      $args[] = $wpdb->prepare("tr.product_id = %d", $params['prd_id']);
    }

    if(isset($params['membership']) && $params['membership'] != 'all' && is_numeric($params['membership'])) {
      $args[] = $wpdb->prepare("tr.product_id = %d", $params['membership']);
    }

    if(isset($params['status']) && $params['status'] != 'all') {
      $args[] = $wpdb->prepare("tr.status = %s", $params['status']);
    }

    if(isset($params['subscription']) && is_numeric($params['subscription'])) {
      $args[] = $wpdb->prepare("tr.subscription_id = %d",$params['subscription']);
    }

    if(isset($params['transaction']) && is_numeric($params['transaction'])) {
      $args[] = $wpdb->prepare("tr.id = %d",$params['transaction']);
    }

    if(isset($params['member']) && !empty($params['member'])) {
      $args[] = $wpdb->prepare("m.user_login = %s",$params['member']);
    }

    if(isset($params['gateway']) && $params['gateway'] != 'all') {
      $args[] = $wpdb->prepare('tr.gateway = %s', $params['gateway']);
    }

    if(isset($params['coupon_id']) && !empty($params['coupon_id'])) {
      $args[] = $wpdb->prepare("tr.coupon_id = %s", $params['coupon_id']);
    }

    // Don't include any subscription confirmation or sub account transactions in the list table
    if(!isset($params['include-confirmations'])) {
      $args[] = $wpdb->prepare("tr.txn_type = %s", self::$payment_str);
      $args[] = $wpdb->prepare("tr.status <> %s", self::$confirmed_str);
    }

    if(isset($params['statuses'])) {
      $qry = array();
      foreach($params['statuses'] as $st) {
        $qry[] = $wpdb->prepare('tr.status = %s', $st);
      }

      $args[] = '('.implode(' OR ',$qry).')';
    }

    $joins = array(
      "/* IMPORTANT */ LEFT JOIN {$wpdb->users} AS m ON tr.user_id = m.ID",
      "/* IMPORTANT */ LEFT JOIN {$wpdb->posts} AS p ON tr.product_id = p.ID",
      "/* IMPORTANT */ LEFT JOIN {$mepr_db->subscriptions} AS sub ON tr.subscription_id=sub.id",
    );

    return MeprDb::list_table($cols, "{$mepr_db->transactions} AS tr", $joins, $args, $order_by, $order, $paged, $search, $search_field, $perpage);
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

  public function is_active($offset = 0) {
    return ( ( $this->rec->status==self::$complete_str ||
               $this->rec->status==self::$confirmed_str ) &&
            !$this->is_expired($offset) );
  }

  public function is_expired($offset = 0) {
    //Check for a lifetime first
    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::db_lifetime()) {
      return false;
    }

    $todays_ts = time() + $offset; // use the offset to check when a txn will expire
    $expires_ts = strtotime($this->expires_at);

    return ($this->status == 'complete' && $expires_ts < $todays_ts);
  }

  public function product() {
    //Don't do static caching stuff here

    return new MeprProduct($this->product_id);
  }

  // Has one through membership
  public function group() {
    $prd = $this->product();

    return $prd->group();
  }

  public function user($force = false) {
    //Don't do static caching stuff here

    return new MeprUser($this->user_id);
  }

  public function subscription() {
    //Don't do static caching stuff here

    if(!isset($this->subscription_id) || empty($this->subscription_id)) {
      return false;
    }

    //For some reason when the free gateway is invoked a subscription is temporarily created
    //then stored with the txn, then deleted, this causes issues so we need to check here
    //that the $sub actually still exists
    $sub = new MeprSubscription($this->subscription_id);

    if(!isset($sub->id) || (int)$sub->id <= 0) { return false; }

    return $sub;
  }

  public function coupon() {
    //Don't do static caching stuff here

    if(!isset($this->coupon_id) || (int)$this->coupon_id <= 0) { return false; }

    $coupon = new MeprCoupon($this->coupon_id);

    if(!isset($coupon->ID) || $coupon->ID <= 0) { return false; }

    return $coupon;
  }

  public function payment_method() {
    $mepr_options = MeprOptions::fetch();
    return $mepr_options->payment_method($this->gateway);
  }

  public function create_fallback_transaction() {
    $purchased_product = $this->product();
    $group = $purchased_product->group();
    $fallback_membership = $group->fallback_membership();
    $user = $this->user();
    $fallback_txn = new MeprTransaction(array(
      'user_id'    => $this->user_id,
      'product_id' => $fallback_membership->ID,
      'status'     => MeprTransaction::$complete_str,
      'txn_type'   => MeprTransaction::$fallback_str,
      'gateway'    => MeprTransaction::$fallback_gateway_str,
      'expires_at' => MeprUtils::db_lifetime(),
    ));

    MeprEvent::record('transaction-completed', $fallback_txn, array('txn_type' => MeprTransaction::$fallback_str, 'user_id' => $this->user_id, 'product_id' => $fallback_membership->ID));
    return $fallback_txn->store();
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
      $expires_at = MeprUtils::db_lifetime();
    }
    else {
      $product_expiration = $product->get_expires_at(strtotime($txn->created_at));

      if(is_null($product_expiration))
        $expires_at = MeprUtils::db_lifetime();
      else
        $expires_at = MeprUtils::ts_to_mysql_date($product_expiration, 'Y-m-d 23:59:59');
    }

    $txn->trans_num  = MeprTransaction::generate_trans_num();
    $txn->status     = self::$pending_str; //This needs to remain as "pending" until we've called maybe_cancel_old_subscription() below
    $txn->txn_type   = self::$payment_str;
    $txn->gateway    = self::$free_gateway_str;
    $txn->expires_at = $expires_at;

    // This will only work before maybe_cancel_old_sub is run
    $upgrade = $txn->is_upgrade();
    $downgrade = $txn->is_downgrade();

    // No such thing as a free subscription in MemberPress
    // So let's clean up this mess right now
    if(!empty($txn->subscription_id) && (int)$txn->subscription_id > 0) {
      MeprHooks::do_action('mepr-before-subscription-destroy-create-free-transaction', $txn);

      $sub = new MeprSubscription($txn->subscription_id);

      $txn->subscription_id = 0;
      $txn->store(); //Store txn here, otherwise it will get deleted during $sub->destroy()

      $sub->destroy();
    }

    //This needs to happen below the $sub destroy or maybe_cancel_old_sub() will fail
    // $txn->store(); //Force store a "pending" status
    $event_txn = $txn->maybe_cancel_old_sub();
    $txn->status = self::$complete_str;
    $txn->store();

    $free_gateway = new MeprBaseStaticGateway(self::$free_gateway_str, __('Free', 'memberpress'), __('Free', 'memberpress'));

    if($upgrade) {
      $free_gateway->upgraded_sub($txn, $event_txn);
    }
    elseif($downgrade) {
      $free_gateway->downgraded_sub($txn, $event_txn);
    }

    MeprUtils::send_signup_notices($txn);
    // $free_gateway->send_transaction_receipt_notices($txn); //Maybe don't need to send a receipt for a free txn
    MeprEvent::record('transaction-completed', $txn); //Delete this if we use $free_gateway->send_transaction_receipt_notices later
    MeprEvent::record('non-recurring-transaction-completed', $txn); //Delete this if we use $free_gateway->send_transaction_receipt_notices later

    $sanitized_title = sanitize_title($product->post_title);
    $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $product->ID);

    MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
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

  public function expire() {
    $this->expires_at = MeprUtils::ts_to_mysql_date(time() - MeprUtils::days(1));
    $this->store();
    MeprEvent::record('transaction-expired', $this, array('txn_type' => $this->txn_type));
  }

  /** Used by one-time payments **/
  public function maybe_cancel_old_sub() {
    $mepr_options = MeprOptions::fetch();

    try {
      $evt_txn = false;
      if($this->is_upgrade_or_downgrade() && $this->is_one_time_payment()) {
        $usr = $this->user();
        $grp = $this->group();

        if(($old_sub = $usr->subscription_in_group($grp->ID))) {
          $evt_txn = $old_sub->latest_txn();
          $old_sub->expire_txns(); //Expire associated transactions for the old subscription
          $_REQUEST['silent'] = true; // Don't want to send cancellation notices
          // PT #157053195 skip cancelled subs
          if($old_sub->status !== MeprSubscription::$cancelled_str) {
            $old_sub->cancel();
          }
        }
        elseif(($old_lifetime_txn = $usr->lifetime_subscription_in_group($grp->ID)) && $old_lifetime_txn->id != $this->id) {
          $old_lifetime_txn->expires_at = MeprUtils::ts_to_mysql_date(time() - MeprUtils::days(1));
          $old_lifetime_txn->store();
          $evt_txn = $old_lifetime_txn;
        }
      }
    }
    catch(Exception $e) {
      // Nothing for now
    }

    if(!empty($evt_txn)) {
      MeprHooks::do_action('mepr-changing-subscription',$this,$evt_txn);
    }

    return $evt_txn;
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

    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::db_lifetime()) {
      return 'lifetime';
    }

    $time_in_this_period = (strtotime($this->expires_at) + MeprUtils::days($mepr_options->grace_expire_days)) - strtotime($this->created_at);

    return intval(round(($time_in_this_period / MeprUtils::days(1))));
  }

  public function days_till_expiration() {
    $mepr_options = MeprOptions::fetch();
    $now = time();

    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::db_lifetime()) {
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
      $pm = $this->payment_method();
      return $pm->process_refund($this);
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
                 ( SELECT sub.status
                     FROM {$mepr_db->subscriptions} AS sub
                    WHERE sub.id = tr.subscription_id
                    LIMIT 1
                 )
             ) AS sub_status
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
         AND tr.user_id > 0
       ORDER BY tr.expires_at
      ",
      MeprUtils::db_lifetime(),
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str,
      MeprUtils::db_now(),
      MeprUtils::db_now()
    );

    $res = $wpdb->get_results($query);

    return $res;
  }

  /** This returns a count of all the transactions that are like this one */
  public function txn_count() {
    return self::get_count_by_user_and_product($this->user_id, $this->product_id, $this->status);
  }

  public function apply_tax($subtotal, $num_decimals = 2, $gross = 0.00) {
    $mepr_options = MeprOptions::fetch();
    $usr = $this->user();
    $prd = $this->product();
    $calculate_taxes = get_option('mepr_calculate_taxes');

    // Now try to calculate tax info from the user info
    if($prd->tax_exempt) { //don't do taxes here yo
      list($this->amount, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = array($gross, $gross, 0.00, 0.00, '', 'standard');
    }
    elseif($calculate_taxes && (int)$usr->ID > 0) {
      list($this->amount, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = $usr->calculate_tax($subtotal, $num_decimals);
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

    if($mepr_options->attr('tax_calc_type') == 'inclusive') {
      $usr = $this->user();
      $subtotal = $usr->calculate_subtotal($subtotal);
    }

    $this->apply_tax($subtotal, 2, $subtotal);
  }

  /** Sets up the transaction total, subtotal and tax based on a gross value.
   * This will never check for tax inclusion because since it's the gross
   *kit doesn't matter (since we already know the gross amount).
   */
  public function set_gross($gross) {
    $usr = $this->user();
    $subtotal = $usr->calculate_subtotal($gross);
    $this->apply_tax($subtotal, 2, $gross);
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

  public static function generate_trans_num() {
    return uniqid('mp-txn-');
  }

  public function is_sub_account() {
    return ($this->txn_type==self::$sub_account_str);
  }

  public function is_confirmation() {
    return ($this->txn_type==self::$subscription_confirmation_str);
  }

  public function is_rebill() {
    $payment_index = $this->subscription_payment_index();
    return ($payment_index!==false && is_numeric($payment_index) && (int)$payment_index > 1);
  }

  /** If this transaction is complete and part of a subscription then this
    * returns the number of rebills up to this current rebill--otherwise it
    * returns false.
    */
  public function subscription_payment_index() {
    global $wpdb;

    $status_array = array(self::$complete_str,self::$refunded_str);
    if( $this->txn_type == self::$payment_str &&
        in_array($this->status,$status_array) &&
        $this->subscription_id > 0 ) {
      $mepr_db = MeprDb::fetch();

      $q = $wpdb->prepare("
          SELECT COUNT(*)
            FROM {$mepr_db->transactions} AS t
           WHERE t.txn_type=%s
             AND t.status IN (%s, %s)
             AND t.subscription_id=%d
             AND t.created_at <= %s
        ",
        self::$payment_str,
        self::$complete_str,
        self::$refunded_str,
        $this->subscription_id,
        $this->created_at
      );

      return (int)$wpdb->get_var($q);
    }

    // If this is not a subscription payment then this value is irrelevant
    return false;
  }

  /***** MAGIC METHOD HANDLERS *****/
  protected function mgm_subtotal($mgm, $val = '') {
    switch($mgm) {
      case 'get':
        if($this->rec->txn_type == MeprTransaction::$subscription_confirmation_str) {
          $sub = new MeprSubscription($this->rec->subscription_id);
          return $sub->price;
        }
        else {
          return $this->rec->amount;
        }
    }
  }

  protected function mgm_net_amount($mgm, $val = '') {
    switch($mgm) {
      case 'get':
        if($this->rec->txn_type == MeprTransaction::$subscription_confirmation_str) {
          $sub = new MeprSubscription($this->rec->subscription_id);
          return $sub->price + $this->rec->tax_amount;
        }
        else {
          return $this->rec->total;
        }
    }
  }

  protected function mgm_latest_txn_id($mgm, $val = '') {
    switch($mgm) {
      case 'get':
        return $this->rec->id;
    }
  }
} //End class
