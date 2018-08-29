<?php
if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

class MeprSubscription extends MeprBaseModel implements MeprProductInterface, MeprTransactionInterface {
  /** Instance Variables & Methods **/
  public static $pending_str   = 'pending';
  public static $active_str    = 'active';
  public static $suspended_str = 'suspended';
  public static $cancelled_str = 'cancelled';

  public $statuses;

  /*** Instance Methods ***/
  public function __construct($obj = null) {
    $this->statuses = array(
      self::$pending_str,
      self::$active_str,
      self::$suspended_str,
      self::$cancelled_str
    );

    $this->initialize(
      array(
        'id'                  => 0,
        'subscr_id'           => 'mp-sub-'.uniqid(),
        'gateway'             => 'manual',
        'user_id'             => 0,
        'product_id'          => 0,
        'coupon_id'           => 0,
        'price'               => 0.00,
        'period'              => 1,
        'period_type'         => 'months',
        'limit_cycles'        => false,
        'limit_cycles_num'    => 0,
        'limit_cycles_action' => null,
        'prorated_trial'      => false,
        'trial'               => false,
        'trial_days'          => 0,
        'trial_amount'        => 0.00,
        'status'              => MeprSubscription::$pending_str,
        'created_at'          => null,
        'total'               => 0.00,
        'tax_rate'            => 0.00,
        'tax_amount'          => 0.00,
        'tax_desc'            => '',
        'tax_class'           => 'standard',
        'cc_last4'            => null,
        'cc_exp_month'        => null,
        'cc_exp_year'         => null,
        'token'               => null,
      ),
      $obj
    );
  }

  public function validate() {
    $p = new MeprProduct();

    $this->validate_not_empty($this->subscr_id, 'subscr_id');
    $this->validate_not_empty($this->gateway, 'gateway');
    $this->validate_is_numeric($this->user_id, 1, null, 'user_id');
    $this->validate_is_numeric($this->product_id, 1, null, 'product_id');
    $this->validate_is_numeric($this->coupon_id, 0, null, 'coupon_id'); //Accept no coupon (0) here
    $this->validate_is_currency($this->price, 0, null, 'price');
    $this->validate_is_numeric($this->period, 1, null, 'period');
    $this->validate_is_in_array($this->period_type, $p->period_types, 'period_type');

    $this->validate_is_bool($this->limit_cycles, 'limit_cycles');
    if($this->limit_cycles) {
      $this->validate_is_numeric($this->limit_cycles_num, 1, null, 'limit_cycles_num');
      $this->validate_is_in_array($this->limit_cycles_action, $this->limit_cycles_actions, 'limit_cycles_action');
    }

    $this->validate_is_bool($this->prorated_trial, 'prorated_trial');

    $this->validate_is_bool($this->trial, 'trial');
    if($this->trial) {
      $this->validate_is_numeric($this->trial_days, 0.00, null, 'trial_days');
      $this->validate_is_currency($this->trial_amount, 0.00, null, 'trial_amount');
    }

    $this->validate_is_in_array($this->status, $this->statuses, 'status');
    if(!empty($this->created_at)) { $this->validate_is_date($this->created_at, 'created_at'); }

    $this->validate_is_currency($this->total, 0, null, 'total');
    $this->validate_is_numeric($this->tax_rate, 0, null, 'tax_rate');
    $this->validate_is_currency($this->tax_amount, 0.00, null, 'tax_amount');
    //$this->validate_not_empty($this->tax_desc, 'tax_desc');
    $this->validate_not_empty($this->tax_class, 'tax_class');

    if(!empty($this->cc_last4)) { $this->validate_regex('/^\d{4}$/', trim($this->cc_last4), 'cc_last4'); }
    if(!empty($this->cc_exp_month)) { $this->validate_regex('/^\d{2}$/', trim($this->cc_exp_month), 'cc_exp_month'); }
    if(!empty($this->cc_exp_year)) { $this->validate_regex('/^\d{2}(\d{2})?$/', $this->cc_exp_year, 'cc_exp_year'); }
  }

  public function store() {
    $old_sub = new self($this->id);

    if(isset($this->id) && !is_null($this->id) && (int)$this->id > 0) {
      $this->id = self::update($this);
    }
    else {
      $this->id = self::create($this);
    }

    //Keep this hook at the bottom of this function
    //This should happen after everything is done processing including the subscr txn_count
    MeprHooks::do_action('mepr_subscription_transition_status', $old_sub->status, $this->status, $this);
    MeprHooks::do_action('mepr_subscription_stored', $this);
    MeprHooks::do_action('mepr_subscription_saved', $this);
    MeprHooks::do_action('mepr_subscription_status_'.$this->status, $this);

    // DEPRECATED ... please use the actions above instead
    MeprHooks::do_action('mepr-subscr-transition-status', $old_sub->status, $this->status, $this);
    MeprHooks::do_action('mepr-subscr-store', $this);
    MeprHooks::do_action('mepr-subscr-status-'.$this->status, $this);

    return $this->id;
  }

  public static function create($sub) {
    $mepr_db = new MeprDb();

    if(is_null($sub->created_at)) {
      $sub->created_at = MeprUtils::db_now();
    }

    $args = $sub->get_values();

    return MeprHooks::apply_filters('mepr_create_subscription', $mepr_db->create_record($mepr_db->subscriptions, $args, false), $args, $sub->user_id);
  }

  public static function update($sub) {
    $mepr_db = new MeprDb();
    $args = $sub->get_values();

    $str = MeprUtils::object_to_string($args);

    //error_log($str);

    return MeprHooks::apply_filters('mepr_update_subscription', $mepr_db->update_record($mepr_db->subscriptions, $sub->id, $args), $args, $sub->user_id);
  }

  public function destroy() {
    $mepr_db = new MeprDb();
    $id = $this->id;
    $args = compact('id');
    $sub = self::get_one($id);

    $txns = MeprTransaction::get_all_by_subscription_id($id);

    if(!empty($txns)) {
      foreach($txns as $txn) {
        $kill_txn = new MeprTransaction($txn->id);
        $kill_txn->destroy();
      }
    }

    $subscription_id = $this->id;

    MeprHooks::do_action('mepr_subscription_pre_delete', $subscription_id);

    $res = $mepr_db->delete_records($mepr_db->subscriptions, $args);

    MeprHooks::do_action('mepr_subscription_deleted', $this);

    return $res;
  }

  public function get_attrs() {
    return array_keys((array)$this->rec);
  }

  // Returns trial days based on trial
  public function get_extend_trial_days($value) {
    return $this->trial ? $value : 0;
  }

  // Returns trial amount based on trial
  public function get_extend_trial_amount($value) {
    return $this->trial ? $value : 0.00;
  }

  // Check to see if an id for the given table exists
  public static function exists($id) {
    $mepr_db = MeprDb::fetch();
    return $mepr_db->record_exists($mepr_db->subscriptions, compact('id'));
  }

  public static function get_one($id, $return_type = OBJECT) {
    $mepr_db = new MeprDb();
    $args = compact('id');

    return $mepr_db->get_one_record($mepr_db->subscriptions, $args, $return_type);
  }

  public static function get_one_by_subscr_id($subscr_id) {
    //error_log("********** MeprUtils::get_one_by_subscr_id subscr_id: {$subscr_id}\n");
    global $wpdb;
    $mepr_db = new MeprDb();

    $sql = "
      SELECT sub.id
        FROM {$mepr_db->subscriptions} AS sub
       WHERE sub.subscr_id=%s
       ORDER BY sub.id DESC
       LIMIT 1
    ";

    $sql = $wpdb->prepare($sql, $subscr_id);
    //error_log("********** MeprUtils::get_one_by_subscr_id SQL: \n" . MeprUtils::object_to_string($sql));

    $sub_id = $wpdb->get_var($sql);
    //error_log("********** MeprUtils::get_one_by_subscr_id sub_id: {$sub_id}\n");

    if($sub_id) {
      return new MeprSubscription($sub_id);
    }
    else {
      return false;
    }
  }

  public static function search_by_subscr_id($search) {
    global $wpdb;

    $mepr_db = new MeprDb();

    $sql = "SELECT id FROM {$mepr_db->subscriptions} WHERE subscr_id LIKE %s";
    $sql = $wpdb->prepare($sql, "{$search}%");
    $ids = $wpdb->get_col($sql);
    $subs = array();

    if(!empty($ids)) {
      foreach($ids as $id) {
        $subs[] = new MeprSubscription($id);
      }
    }

    return $subs;
  }

  public static function get_all_active_by_user_id($user_id, $order = "", $limit = "", $count = false, $look_for_lapsed = false) {
    global $wpdb;

    $mepr_db = new MeprDb();

    $order  = empty($order)?'':" ORDER BY {$order}";
    $limit  = empty($limit)?'':" LIMIT {$limit}";
    $fields = $count?'COUNT(*)':'sub.*';

    if(!$look_for_lapsed) {
      $sql = "
        SELECT {$fields}
          FROM {$mepr_db->subscriptions} AS sub
            JOIN {$mepr_db->transactions} AS t
              ON sub.id = t.subscription_id
          WHERE t.user_id = %d
            AND t.expires_at > %s
            AND t.status IN(%s,%s)
            AND sub.status <> %s
        {$order}{$limit}
      ";

      $sql = $wpdb->prepare($sql, $user_id, MeprUtils::db_now(), MeprTransaction::$complete_str, MeprTransaction::$confirmed_str, MeprSubscription::$pending_str);
    }
    else { //Look for lapsed subs here
      $sql = "
        SELECT {$fields}
          FROM {$mepr_db->subscriptions} AS sub
          WHERE user_id = %d
            AND status IN (%s, %s)
        {$order}{$limit}
      ";

      $sql = $wpdb->prepare($sql, $user_id, MeprSubscription::$active_str, MeprSubscription::$suspended_str);
    }
    if($count) {
      return $wpdb->get_var($sql);
    }
    else {
      return $wpdb->get_results($sql);
    }
  }

  public static function get_all() {
    global $wpdb;

    $mepr_db = new MeprDb();

    $sql = "SELECT * FROM {$mepr_db->subscriptions}";

    return $wpdb->get_results($sql);
  }

  public static function subscription_exists($subscr_id) {
    return is_object(self::get_one_by_subscr_id($subscr_id));
  }

  //Sets membership ID to 0 if for some reason a membership is deleted
  public static function nullify_product_id_on_delete($id) {
    global $wpdb, $post_type;

    $mepr_db = new MeprDb();

    $q = "
      UPDATE {$mepr_db->subscriptions}
         SET product_id = 0
       WHERE product_id = %d
    ";

    $wpdb->query($wpdb->prepare($q, $id));
  }

  //Sets user id to 0 if for some reason a user is deleted
  public static function nullify_user_id_on_delete($id) {
    global $wpdb;

    $mepr_db = new MeprDb();

    $q = "
      UPDATE {$mepr_db->subscriptions}
         SET user_id = 0
       WHERE user_id = %d
    ";

    $wpdb->query($wpdb->prepare($q, $id));
  }

  public static function account_subscr_table( $order_by = '',
                                               $order = '',
                                               $paged = '',
                                               $search = '',
                                               $search_field = 'any',
                                               $perpage = 10,
                                               $countonly = false,
                                               $params=null,
                                               $encols='all' ) {
    global $wpdb;

    // Get the individual queries
    $lsql = self::lifetime_subscr_table( '', '', '', $search, $search_field, 0,
                                         $countonly, $params, $encols, true );

    $sql = self::subscr_table( '', '', '', $search, $search_field, 0,
                               $countonly, $params, $encols, true );

    /* -- Ordering parameters -- */
    //Parameters that are going to be used to order the result
    $order_by = (!empty($order_by) and !empty($order))?($order_by = ' ORDER BY '.$order_by.' '.$order):'';

    //Page Number
    if(empty($paged) or !is_numeric($paged) or $paged<=0) { $paged=1; }

    $limit = '';
    //adjust the query to take pagination into account
    if(!empty($paged) and !empty($perpage)) {
      $offset=($paged - 1) * $perpage;
      $limit = ' LIMIT '.(int)$offset.','.(int)$perpage;
    }

    $wpdb->query("SET SQL_BIG_SELECTS=1");

    $asql = "({$lsql['query']}) UNION ({$sql['query']}){$order_by}{$limit}";
    $acsql = "SELECT (({$lsql['total_query']}) + ({$sql['total_query']}))";

    $results = $wpdb->get_results($asql);
    $count = $wpdb->get_var($acsql);

    return compact('results', 'count');
  }

  public static function subscr_table( $order_by = '',
                                       $order = '',
                                       $paged = '',
                                       $search = '',
                                       $search_field = '',
                                       $perpage = 10,
                                       $countonly = false,
                                       $params=null,
                                       $encols='all',
                                       $queryonly = false
                                     ) {
    global $wpdb;
    $mepr_options = MeprOptions::fetch();
    $pmt_methods = $mepr_options->payment_methods();
    $mepr_db = new MeprDb();
    $en = function($c, $e) { return (!is_array($e) || in_array($c, $e)); };

    if(is_null($params)) { $params = $_GET; }

    if(!empty($pmt_methods)) {
      $gateway = '(SELECT CASE sub.gateway';

      foreach($pmt_methods as $method) {
        $gateway .= $wpdb->prepare(" WHEN %s THEN %s", $method->id, "{$method->label} ({$method->name})");
      }

      $gateway .= $wpdb->prepare(" ELSE %s END)", __('Unknown', 'memberpress'));
    }
    else {
      $gateway = 'sub.gateway';
    }

    // The transaction count
    $txn_count = $wpdb->prepare("
      (SELECT COUNT(*)
         FROM {$mepr_db->transactions} AS txn_cnt
        WHERE txn_cnt.subscription_id=sub.id
          AND txn_cnt.status=%s)",
      MeprTransaction::$complete_str
    );

    $active = $wpdb->prepare('
      (SELECT
         CASE
           WHEN expiring_txn.expires_at = 0
             OR expiring_txn.expires_at = %s
           THEN %s
           WHEN expiring_txn.expires_at IS NULL
             OR expiring_txn.expires_at < %s
           THEN %s
           WHEN expiring_txn.status IN (%s,%s)
            AND expiring_txn.txn_type IN (%s,%s)
           THEN %s
         ELSE %s
       END
      )',
      MeprUtils::db_lifetime(),
      '<span class="mepr-active">' . __('Yes','memberpress') . '</span>',
      MeprUtils::db_now(),
      '<span class="mepr-inactive">' . __('No','memberpress') . '</span>',
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str,
      MeprTransaction::$subscription_confirmation_str,
      'sub_account',
      '<span class="mepr-active">' . __('Yes','memberpress') . '</span>',
      '<span class="mepr-active">' . __('Yes','memberpress') . '</span>'
    );

    $fname = "
      (SELECT um_fname.meta_value
         FROM {$wpdb->usermeta} AS um_fname
        WHERE um_fname.user_id = u.ID
          AND um_fname.meta_key = 'first_name'
        LIMIT 1)
    ";

    $lname = "
      (SELECT um_lname.meta_value
         FROM {$wpdb->usermeta} AS um_lname
        WHERE um_lname.user_id = u.ID
          AND um_lname.meta_key = 'last_name'
        LIMIT 1)
    ";

    $cols = array( 'sub_type' => "'subscription'" );
    if( $en('id',             $encols) ) { $cols['id']             = 'sub.id'; }
    if( $en('user_email',     $encols) ) { $cols['user_email']     = 'u.user_email'; }
    if( $en('gateway',        $encols) ) { $cols['gateway']        = 'sub.gateway'; }
    if( $en('member',         $encols) ) { $cols['member']         = 'u.user_login'; }
    if( $en('first_name',     $encols) ) { $cols['first_name']     = $fname; }
    if( $en('last_name',      $encols) ) { $cols['last_name']      = $lname; }
    if( $en('product_name',   $encols) ) { $cols['product_name']   = 'prd.post_title'; }
    if( $en('first_txn_id',   $encols) ) { $cols['first_txn_id']   = 'first_txn.id'; }
    if( $en('latest_txn_id',  $encols) ) { $cols['latest_txn_id']  = 'last_txn.id'; }
    if( $en('expiring_txn_id',$encols) ) { $cols['expiring_txn_id']= 'expiring_txn.id'; }
    if( $en('txn_count',      $encols) ) { $cols['txn_count']      = $txn_count; }
    if( $en('expires_at',     $encols) ) { $cols['expires_at']     = 'expiring_txn.expires_at'; }

    $tmp_sub = new MeprSubscription();
    $pms = $tmp_sub->get_attrs();

    // Add postmeta columns
    foreach( $pms as $slug ) {
      if( $en($slug, $encols) ) {
        $cols[$slug] = "sub.{$slug}";
      }
    }

    // Very important this comes after the meta columns ...
    // must maintain same order as the lifetime table
    if( $en('active', $encols) ) { $cols['active'] = $active; }

    $args = array();

    if(isset($params['member']) && !empty($params['member'])) {
      $args[] = $wpdb->prepare("u.user_login = %s", $params['member']);
    }

    if(isset($params['subscription']) && !empty($params['subscription'])) {
      $args[] = $wpdb->prepare("sub.id = %d", $params['subscription']);
    }

    if(isset($params['prd_id']) && is_numeric($params['prd_id'])) {
      $args[] = $wpdb->prepare("sub.product_id = %d", $params['prd_id']);
    }

    if(isset($params['membership']) && is_numeric($params['membership'])) {
      $args[] = $wpdb->prepare("sub.product_id = %d", $params['membership']);
    }

    if(isset($params['status']) && $params['status'] != 'all') {
      $args[] = $wpdb->prepare('sub.status = %s', $params['status']);
    }

    if(isset($params['gateway']) && $params['gateway'] != 'all') {
      $args[] = $wpdb->prepare('sub.gateway = %s', $params['gateway']);
    }

    if(isset($params['statuses']) && !empty($params['statuses'])) {
      $qry = array();

      foreach($params['statuses'] as $st) {
        $qry[] = $wpdb->prepare('sub.status = %s', $st);
      }

      $args[] = '(' . implode( ' OR ', $qry ) . ')';
    }

    $joins = array();

    $important_joins = array('status','user_id','product_id');

    $joins[] = "/* IMPORTANT */ LEFT JOIN {$wpdb->users} AS u ON u.ID = sub.user_id";
    $joins[] = "/* IMPORTANT */ LEFT JOIN {$wpdb->posts} AS prd ON prd.ID = sub.product_id";

    // The first transaction
    $joins[] = $wpdb->prepare(
      "LEFT JOIN {$mepr_db->transactions} AS first_txn
         ON first_txn.id=(
           SELECT ft1.id
             FROM {$mepr_db->transactions} AS ft1
            WHERE ft1.subscription_id=sub.id
              AND ft1.status IN (%s,%s)
            ORDER BY ft1.id ASC
            LIMIT 1
         )",
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str
    );

    // The last transaction made
    $joins[] = $wpdb->prepare(
      "LEFT JOIN {$mepr_db->transactions} AS last_txn
         ON last_txn.id=(
           SELECT lt1.id
             FROM {$mepr_db->transactions} AS lt1
            WHERE lt1.subscription_id=sub.id
              AND lt1.status IN (%s,%s)
            ORDER BY lt1.id DESC
            LIMIT 1
         )",
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str
    );

    // The transaction associated with this subscription with the latest expiration date
    $joins[] = $wpdb->prepare(
      "LEFT JOIN {$mepr_db->transactions} AS expiring_txn
         ON expiring_txn.id = (
           SELECT t.id
            FROM {$mepr_db->transactions} AS t
           WHERE t.subscription_id=sub.id
             AND t.status IN (%s,%s)
             AND ( t.expires_at = %s
                   OR ( t.expires_at <> %s
                        AND t.expires_at=(
                          SELECT MAX(t2.expires_at)
                            FROM {$mepr_db->transactions} as t2
                           WHERE t2.subscription_id=sub.id
                             AND t2.status IN (%s,%s)
                        )
                      )
                 )
           ORDER BY t.expires_at
           LIMIT 1
         )",
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str,
      MeprUtils::db_lifetime(),
      MeprUtils::db_lifetime(),
      MeprTransaction::$confirmed_str,
      MeprTransaction::$complete_str
    );

    return MeprDb::list_table(
      MeprHooks::apply_filters('mepr_recurring_subscriptions_table_cols',  $cols),
      MeprHooks::apply_filters('mepr_recurring_subscriptions_table_from',  "{$mepr_db->subscriptions} AS sub"),
      MeprHooks::apply_filters('mepr_recurring_subscriptions_table_joins', $joins),
      MeprHooks::apply_filters('mepr_recurring_subscriptions_table_args',  $args),
      $order_by, $order, $paged, $search, $search_field, $perpage, $countonly, $queryonly
    );
  }

  // Okay, these are actually transactions but to the unwashed masses ... they're subscriptions
  public static function lifetime_subscr_table( $order_by = '',
                                                $order = '',
                                                $paged = '',
                                                $search = '',
                                                $search_field = 'any',
                                                $perpage = 10,
                                                $countonly = false,
                                                $params = null,
                                                $encols = 'all',
                                                $queryonly = false
                                              ) {
    global $wpdb;
    $mepr_options = MeprOptions::fetch();
    $pmt_methods = $mepr_options->payment_methods();
    $mepr_db = new MeprDb();
    $en = function($c, $e) { return (!is_array($e) || in_array($c, $e)); };

    if(is_null($params)) { $params=$_GET; }

    if(!empty($pmt_methods)) {
      $gateway = '(SELECT CASE txn.gateway';

      foreach($pmt_methods as $method) {
        $gateway .= $wpdb->prepare(" WHEN %s THEN %s", $method->id, "{$method->label} ({$method->name})");
      }

      $gateway .= $wpdb->prepare(" ELSE %s END)", __('Unknown', 'memberpress'));
    }
    else {
      $gateway = 'txn.gateway';
    }

    $fname = "(SELECT um_fname.meta_value FROM {$wpdb->usermeta} AS um_fname WHERE um_fname.user_id = u.ID AND um_fname.meta_key = 'first_name' LIMIT 1)";
    $lname = "(SELECT um_lname.meta_value FROM {$wpdb->usermeta} AS um_lname WHERE um_lname.user_id = u.ID AND um_lname.meta_key = 'last_name' LIMIT 1)";

    $cols = array( 'sub_type' => "'transaction'" );

    if( $en('id',             $encols) ) { $cols['id'] = 'txn.id'; }
    if( $en('user_email',     $encols) ) { $cols['user_email'] = 'u.user_email'; }
    if( $en('gateway',        $encols) ) { $cols['gateway'] = $gateway; }
    if( $en('member',         $encols) ) { $cols['member'] = 'u.user_login'; }
    if( $en('first_name',     $encols) ) { $cols['first_name'] = $fname; }
    if( $en('last_name',      $encols) ) { $cols['last_name'] = $lname; }
    if( $en('product_name',   $encols) ) { $cols['product_name'] = 'prd.post_title'; }
    if( $en('first_txn_id',   $encols) ) { $cols['first_txn_id'] = 'txn.id'; }
    if( $en('latest_txn_id',  $encols) ) { $cols['latest_txn_id'] = 'txn.id'; }
    if( $en('expiring_txn_id',$encols) ) { $cols['expiring_txn_id'] = 'txn.id'; }
    if( $en('txn_count',      $encols) ) { $cols['txn_count'] = $wpdb->prepare('%s',1); }
    if( $en('expires_at',     $encols) ) { $cols['expires_at'] = 'txn.expires_at'; }

    if( $en('subscr_id',     $encols) ) { $cols['subscr_id'] = 'txn.trans_num'; }
    if( $en('user_id',       $encols) ) { $cols['user_id'] = 'txn.user_id'; }
    if( $en('product_id',    $encols) ) { $cols['product_id'] = 'txn.product_id'; }
    if( $en('coupon_id',     $encols) ) { $cols['coupon_id'] = 'txn.coupon_id'; }
    if( $en('price',         $encols) ) { $cols['price'] = 'txn.amount'; }
    if( $en('period',        $encols) ) { $cols['period'] = $wpdb->prepare('%d',1); }
    if( $en('period_type',   $encols) ) { $cols['period_type'] = $wpdb->prepare('%s','lifetime'); }
    if( $en('prorated_trial',$encols) ) { $cols['prorated_trial'] = $wpdb->prepare('%d',0); }
    if( $en('trial',         $encols) ) { $cols['trial'] = $wpdb->prepare('%d',0); }
    if( $en('trial_days',    $encols) ) { $cols['trial_days'] = $wpdb->prepare('%d',0); }
    if( $en('trial_amount',  $encols) ) { $cols['trial_amount'] = $wpdb->prepare('%f',0.00); }
    if( $en('status',        $encols) ) { $cols['status'] = $wpdb->prepare('%s',__('None','memberpress')); }
    if( $en('created_at',    $encols) ) { $cols['created_at'] = 'txn.created_at'; }
    if( $en('active',$encols) ) {
      $cols['active'] = $wpdb->prepare('
        (SELECT
           CASE
             WHEN txn.status IN (%s,%s)
              AND ( txn.expires_at = %s OR
                    txn.expires_at >= %s )
             THEN %s
           ELSE %s
         END)',
         MeprTransaction::$complete_str,
         MeprTransaction::$confirmed_str,
         MeprUtils::db_lifetime(),
         MeprUtils::db_now(),
         '<span class="mepr-active">' . __('Yes','memberpress') . '</span>',
         '<span class="mepr-inactive">' . __('No', 'memberpress') . '</span>'
      );
    }

    $args = array('(txn.subscription_id IS NULL OR txn.subscription_id <= 0)');

    if(isset($params['member']) && !empty($params['member'])) {
      $args[] = $wpdb->prepare("u.user_login = %s", $params['member']);
    }

    if(isset($params['subscription']) and !empty($params['subscription'])) {
      $args[] = $wpdb->prepare("txn.id = %d", $params['subscription']);
    }

    if(isset($params['prd_id']) && is_numeric($params['prd_id'])) {
      $args[] = $wpdb->prepare("prd.ID = %d", $params['prd_id']);
    }

    if(isset($params['membership']) && is_numeric($params['membership'])) {
      $args[] = $wpdb->prepare("prd.ID = %d", $params['membership']);
    }

    if(isset($params['gateway']) && $params['gateway'] != 'all') {
      $args[] = $wpdb->prepare('txn.gateway = %s', $params['gateway']);
    }

    if(isset($params['statuses']) && !empty($params['statuses'])) {
      $qry = array();

      foreach($params['statuses'] as $st) {
        // Map subscription status to transaction status
        $txn_status = MeprTransaction::map_subscr_status( $st );

        if( !$txn_status ) { continue; }

        if( !is_array( $txn_status ) ) { $txn_status = array( $txn_status ); }

        foreach( $txn_status as $txn_st ) {
          $qry[] = $wpdb->prepare( 'txn.status=%s', $txn_st );
        }
      }

      $args[] = '(' . implode( ' OR ', $qry ) . ')';
    }

    $joins = array();
    $joins[] = "/* IMPORTANT */ LEFT JOIN {$wpdb->users} AS u ON u.ID = txn.user_id";
    $joins[] = "/* IMPORTANT */ LEFT JOIN {$wpdb->posts} AS prd ON prd.ID = txn.product_id";

    if( $en('period_type',$encols) ) { $joins[] = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} AS pm_period_type ON pm_period_type.post_id = prd.ID AND pm_period_type.meta_key = %s", MeprProduct::$period_type_str ); }

    return MeprDb::list_table(
      MeprHooks::apply_filters('mepr_nonrecurring_subscriptions_table_cols',  $cols),
      MeprHooks::apply_filters('mepr_nonrecurring_subscriptions_table_from',  "{$mepr_db->transactions} AS txn"),
      MeprHooks::apply_filters('mepr_nonrecurring_subscriptions_table_joins', $joins),
      MeprHooks::apply_filters('mepr_nonrecurring_subscriptions_table_args',  $args),
      $order_by, $order, $paged,
      $search, $search_field, $perpage, $countonly, $queryonly
    );
  }

  public function user($force = false) {
    //Don't do static caching stuff here

    return new MeprUser($this->user_id);
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

  public function coupon() {
    //Don't do static caching stuff here

    if(!isset($this->coupon_id) || (int)$this->coupon_id <= 0) { return false; }

    $coupon = new MeprCoupon($this->coupon_id);

    if(!isset($coupon->ID) || $coupon->ID <= 0) { return false; }

    return $coupon;
  }

  public function first_txn() {
    $first_txn_id = $this->first_txn_id;
    return empty($first_txn_id) ? false : new MeprTransaction($this->first_txn_id);
  }

  public function latest_txn() {
    $latest_txn_id = $this->latest_txn_id;
    return empty($latest_txn_id) ? false : new MeprTransaction($this->latest_txn_id);
  }

  public function expiring_txn() {
    $expiring_txn_id = $this->expiring_txn_id;
    return empty($expiring_txn_id) ? false : new MeprTransaction($this->expiring_txn_id);
  }

  public function transactions($return_objects = true, $where = "", $order = "created_at") {
    global $wpdb;

    $mepr_db = new MeprDb();

    if(!empty($where)) { $where = "AND {$where}"; }

    $query = "SELECT id FROM {$mepr_db->transactions} AS t
                WHERE t.subscription_id = %d
                {$where}
              {$order}";
    $query = $wpdb->prepare($query, $this->id);
    $res = $wpdb->get_col($query);

    if($return_objects and !empty($res)) {
      $txns = array();

      foreach($res as $id) {
        $txns[] = new MeprTransaction($id);
      }

      return $txns;
    }

    return $res;
  }

  //Cancels a subscription is the limit_cycles_num >= txn_count
  //$trial_offset is used if a paid trial payment exists
  public function limit_payment_cycles() {
    //Check if limiting is even enabled
    if(!$this->limit_cycles) { return; }

    $pm = $this->payment_method();

    if($pm === false) { return; } //What else to do here?

    $trial_offset = (($this->trial && $this->trial_amount > 0.00)?1:0);

    //Cancel this subscription if the payment cycles are limited and have been reached
    if($this->status == MeprSubscription::$active_str && ($this->txn_count - $trial_offset) >= $this->limit_cycles_num) {
      $_REQUEST['expire'] = true; // pass the expire
      $_REQUEST['silent'] = true; // Don't want to send cancellation notices
      try {
        $pm->process_cancel_subscription($this->id);
      }
      catch(Exception $e) {
        // TODO: We might want to actually do something here at some point
        return;
      }
    }
  }

  // This should be called from process_cancel_subscription
  public function limit_reached_actions() {
    //Check if limiting is even enabled
    if(!$this->limit_cycles) { return; }

    if($this->limit_cycles_action == 'lifetime') {
      $txn = $this->latest_txn();

      if(!empty($txn) && $txn instanceof MeprTransaction) {
        $txn->expires_at = MeprUtils::db_lifetime(); // lifetime expiration
        $txn->store();
      }
    }

    MeprHooks::do_action('mepr-limit-payment-cycles-reached', $this);
  }

  public function expire_txns() {
    global $wpdb;
    $mepr_db = new MeprDb();
    $time = time();

    $q = "UPDATE {$mepr_db->transactions}
            SET expires_at = %s
            WHERE subscription_id = %d
              AND subscription_id > 0
              AND expires_at >= %s";

    // Set expiration 1 day in the past so it expires NOW
    $wpdb->query( $wpdb->prepare( $q,
                                  MeprUtils::ts_to_mysql_date($time-MeprUtils::days(1)),
                                  $this->id,
                                  MeprUtils::ts_to_mysql_date($time) ) );
  }

  // Used to expire an active confirmation txn when a payment fails,
  // is refunded or the subscription is cancelled
  public function expire_confirmation_txn() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("
        UPDATE {$mepr_db->transactions}
           SET expires_at = created_at
         WHERE subscription_id = %d
           AND txn_type = %s
           AND expires_at >= %s
      ",
      $this->id,
      MeprTransaction::$subscription_confirmation_str,
      MeprUtils::db_now()
    );

    $wpdb->query($q);
  }

  public function payment_method() {
    $mepr_options = MeprOptions::fetch();

    return $mepr_options->payment_method($this->gateway);
  }

  public function in_free_trial() {
    return $this->in_trial('free');
  }

  public function in_paid_trial() {
    return $this->in_trial('paid');
  }

  /* Paid or Free trial ... it matters not ... this will return true */
  public function in_trial($type = 'all') {
    if($this->trial) {
      $trial_started = strtotime($this->created_at);
      $trial_ended   = $trial_started + MeprUtils::days($this->trial_days);

      if(($type == 'paid' && (float)$this->trial_amount <= 0.00) || ($type == 'free' && (float)$this->trial_amount > 0.00)) {
        return false;
      }

      return (time() < $trial_ended);
    }

    return false;
  }

  public function in_grace_period() {
    if($this->txn_count == 0 && $this->status == self::$active_str) {
      $first_txn = $this->first_txn();

      //The subscription hasn't been cancelled, and it doesn't have any "payment" txn's yet
      //So let's check the confirmation expiration now (using 25 hours as a bit of a leway for the 24 hour grace period)
      if( $first_txn != false &&
          $first_txn instanceof MeprTransaction &&
          $first_txn->txn_type == MeprTransaction::$subscription_confirmation_str &&
          strtotime($first_txn->expires_at) >= time() &&
          (strtotime($first_txn->expires_at) - strtotime($first_txn->created_at)) <= MeprUtils::hours(25) ) {
        return true;
      }
    }

    return false;
  }

  public function days_till_expiration() {
    $expiring_txn = $this->expiring_txn();

    return $expiring_txn->days_till_expiration();
  }

  public function days_in_this_period($ignore_trial = false) {
    if($this->in_trial() && !$ignore_trial) {
      $period_seconds = MeprUtils::days($this->trial_days);
    }
    else {
      if((int)$this->id > 0) {
        $latest_txn = $this->latest_txn();

        if($latest_txn == false || !($latest_txn instanceof MeprTransaction)) {
          $latest_txn = new MeprTransaction();
          $latest_txn->created_at = MeprUtils::ts_to_mysql_date(time());
        }
      }
      else {
        //this could happen when checking upgrade prorated price on a new sub
        $latest_txn = new MeprTransaction();
        $latest_txn->created_at = MeprUtils::ts_to_mysql_date(time());
      }

      switch($this->period_type) {
        case 'weeks':
          $period_seconds = MeprUtils::weeks($this->period);
          break;
        case 'months':
          if(!$this->id) {
            $period_seconds = MeprUtils::months($this->period, time()); //probably an upgrade calculation, x months from now
          }
          else {
            $renewal_dom = date('j', strtotime($this->renewal_base_date));
            $period_seconds = MeprUtils::months($this->period, strtotime($latest_txn->created_at), false, $renewal_dom);
          }
          break;
        case 'years':
          $period_seconds = MeprUtils::years($this->period, strtotime($latest_txn->created_at));
          break;
        default:
          return false;
      }
    }

    return intval(round($period_seconds / MeprUtils::days(1)));
  }

  public function trial_expires_at() {
    $created_at = strtotime($this->created_at);

    return ($created_at + MeprUtils::days($this->trial_days));
  }

  public function is_expired($offset = 0) {
    //Check for a lifetime first
    if(is_null($this->expires_at) || $this->expires_at == MeprUtils::db_lifetime()) {
      return false;
    }

    //Check for a false expires_at date
    if($this->expires_at === false) { return true; }

    $todays_ts = time() + $offset; // use the offset to check when a txn will expire
    $expires_ts = strtotime($this->expires_at);

    return ($expires_ts < $todays_ts);
  }

  //Is the most recent transaction a failure?
  //used mostly for catchup feature in authorize.net
  public function latest_txn_failed() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $status = $wpdb->get_var("
      SELECT status
        FROM {$mepr_db->transactions}
       WHERE subscription_id = {$this->id}
       ORDER BY id DESC
       LIMIT 1
    ");

    return ($status == MeprTransaction::$failed_str);
  }

  public function is_lifetime() {
    return ($this->expires_at == MeprUtils::db_lifetime());
  }

  public function is_active() {
    return !$this->is_expired();
  }

  public function is_cancelled() {
    return ($this->status==self::$cancelled_str);
  }

  public function cc_num() {
    return MeprUtils::cc_num($this->cc_last4);
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

  // This doesn't store ... this just sets up the
  // prorated trial ... do what you will later on
  public function maybe_prorate() {
    $mepr_options = MeprOptions::fetch();
    $usr = $this->user();
    $this->prorated_trial = false;

    if($usr->is_logged_in_and_current_user() &&
       $this->is_upgrade_or_downgrade() &&
       $mepr_options->pro_rated_upgrades) {
      $grp = $this->group();
      $old_lifetime = $usr->lifetime_subscription_in_group($grp->ID);

      //One-time payment upgrade?
      if($old_lifetime !== false) {
        $old_amount = $old_lifetime->amount;
        $new_amount = $this->price;
        $old_period = $old_lifetime->days_in_this_period();
        $new_period = $this->days_in_this_period(true);
        $days_left = $old_lifetime->days_till_expiration();

        $r = MeprUtils::calculate_proration($old_amount, $new_amount, $old_period, $new_period, $days_left, false, $old_lifetime, $this);
      }
      else { //Recurring upgrade
        if(($old_sub=$usr->subscription_in_group($grp->ID)) &&
           $old_sub->id!=$this->id && !$old_sub->in_free_trial()) {
          $r = MeprUtils::calculate_proration_by_subs($old_sub, $this, $grp->upgrade_path_reset_period);
        }
      }

      // Prorations override the trial ... if there is one
      // Only makes sense if the days are greater than 0
      if(isset($r) && $r->days > 0) {
        $this->prorated_trial = true;
        $this->trial          = true;
        $this->trial_days     = $r->days;
        $this->trial_amount   = $r->proration;
      }
    }
  }

  public function maybe_cancel_old_sub() {
    $mepr_options = MeprOptions::fetch();
    $usr = $this->user();
    $grp = $this->group();
    $evt_txn = false;

    //no group? Not an upgrade then
    if($grp === false) { return false; }

    // no upgrade path here ... not an upgrade
    if(!$grp->is_upgrade_path) { return false; }

    try {
      if(($old_sub = $usr->subscription_in_group($grp->ID, true)) && $old_sub->id != $this->id) {
        $evt_txn = $old_sub->latest_txn();
        $old_sub->expire_txns(); //Expire associated transactions for the old subscription
        $_REQUEST['silent'] = true; // Don't want to send cancellation notices
        if($old_sub->status !== MeprSubscription::$cancelled_str) {
          $old_sub->cancel();
        }
      }
      elseif($old_lifetime_txn = $usr->lifetime_subscription_in_group($grp->ID)) {
        $old_lifetime_txn->expires_at = MeprUtils::ts_to_mysql_date(time() - MeprUtils::days(1));
        $old_lifetime_txn->store();
        $evt_txn = $old_lifetime_txn;
      }
    }
    catch(Exception $e) {
      // Nothing for now
    }

    if(!empty($evt_txn)) {
      MeprHooks::do_action('mepr-changing-subscription',$this->latest_txn(),$evt_txn);
    }

    return $evt_txn;
  }

  /** Gets the value for 'expires_at' for the given created_at time for this membership. */
  public function get_expires_at($created_ts=null) {
    $mepr_options = MeprOptions::fetch();

    if(is_null($created_ts)) { $created_ts = time(); }

    $expires_at = $created_ts;
    $period = $this->period;

    switch($this->period_type) {
      case 'days':
          $expires_at += MeprUtils::days($period) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'weeks':
          $expires_at += MeprUtils::weeks($period) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'months':
          $renewal_date = MeprUtils::db_date_to_ts($this->renewal_base_date);
          // Ensure we are not dealing with a pause/resume
          if($renewal_date === MeprUtils::db_date_to_ts($this->created_at)) {
            $renewal_date += MeprUtils::days($this->trial_days);
          }
          $renewal_dom = date('j', $renewal_date);
          $expires_at += MeprUtils::months($period, $created_ts, false, $renewal_dom);
          //Fixes bug 1136 early/late renewals
          $created_expires_diff_in_days = abs(floor(($expires_at - $created_ts)/MeprUtils::days(1)))/$this->period;
          if(!$this->latest_txn_failed() && $created_expires_diff_in_days > 32) {
            //This is a late renewal. We gave too much. Take away the extra month
            $expires_at -= MeprUtils::months('1', $expires_at, true, $renewal_dom);
          }
          elseif(!$this->latest_txn_failed() && $created_expires_diff_in_days < 27) {
            //This is an early renewal. Give them 1 month
            $expires_at += MeprUtils::months('1', $expires_at, false, $renewal_dom);
          }
          $expires_at += MeprUtils::days($mepr_options->grace_expire_days);
          break;
      case 'years':
          $expires_at += MeprUtils::years($period, $created_ts) + MeprUtils::days($mepr_options->grace_expire_days);
          break;
      default:
          $expires_at = null;
    }

    return $expires_at;
  }

  public function load_product_vars($prd, $cpn_code=null, $set_subtotal=false) {
    $mock_cpn = (object)array('post_title' => null, 'ID' => 0, 'trial' => 0);

    if(empty($cpn_code) || !MeprCoupon::is_valid_coupon_code($cpn_code, $prd->ID)) {
      $cpn = $mock_cpn;
    }
    else {
      if(!($cpn = MeprCoupon::get_one_from_code($cpn_code))) {
        $cpn = $mock_cpn;
      }
    }

    $this->product_id = $prd->ID;
    $this->coupon_id = $cpn->ID;
    $this->period = $prd->period;
    $this->period_type = $prd->period_type;
    $this->limit_cycles = $prd->limit_cycles;
    $this->limit_cycles_num = $prd->limit_cycles_num;
    $this->limit_cycles_action = $prd->limit_cycles_action;
    $this->trial = $prd->trial;
    $this->trial_days = $prd->trial ? $prd->trial_days : 0;
    $this->trial_amount = $prd->trial_amount;

    if($set_subtotal) {
      $this->set_subtotal($prd->adjusted_price($cpn->post_title));
    }
    else {
      $this->price = $prd->adjusted_price($cpn->post_title);
    }

    // If trial only once is set and the member has
    // already had a trial then get rid of it
    if($prd->trial_once && $prd->trial_is_expired()) {
      $this->trial = false;
      $this->trial_days = 0;
      $this->trial_amount = 0.00;
    }

    // This will only happen with a real coupon
    if($cpn->trial) {
      $this->trial = $cpn->trial;
      $this->trial_days = $cpn->trial_days;
      $this->trial_amount = $cpn->trial_amount;
    }
  }

  /** Convenience method to determine what we can do
    * with the gateway associated with the subscription
    */
  public function can($cap) {
    $pm = $this->payment_method();

    if($pm!=false and is_object($pm)) {
      return $pm->can($cap);
    }

    return false;
  }

  public function suspend() {
    if($this->can('suspend-subscriptions')) {
      try {
        $pm = $this->payment_method();
        return $pm->process_suspend_subscription($this->id);
      }
      catch(Exception $e) {
        return false;
      }
    }

    return false;
  }

  public function resume() {
    if($this->can('resume-subscriptions')) {
      try {
        $pm = $this->payment_method();
        return $pm->process_resume_subscription($this->id);
      }
      catch(Exception $e) {
        return false;
      }
    }

    return false;
  }

  public function cancel() {
    if($this->can('cancel-subscriptions')) {
      $pm = $this->payment_method();
      return $pm->process_cancel_subscription($this->id);
    }

    return false;
  }

  public function cc_expiring_before_next_payment() {
    if( $next_billing_at = $this->next_billing_at and
        $exp_month = $this->cc_exp_month and
        $exp_year = $this->cc_exp_year ) {
      $cc_exp_ts = mktime( 0, 0, 0, $exp_month, 1, $exp_year );
      $next_billing_ts = strtotime( $next_billing_at );
      return ( $cc_exp_ts < $next_billing_ts );
    }

    return false;
  }

  public function update_url() {
    $mepr_options = MeprOptions::fetch();

    return $mepr_options->account_page_url("action=update&sub={$this->id}");
  }

  public function upgrade_url() {
    $mepr_options = MeprOptions::fetch();

    if($grp = $this->group() and $grp->is_upgrade_path) {
      return $mepr_options->account_page_url("action=upgrade&sub={$this->id}");
    }

    return '';
  }

  //PRETTY MUCH ONLY FOR AUTHORIZE.NET CURRENTLY
  //But could be used for Manual Subscriptions / PayPal Reference txn's eventually
  public function calculate_catchup($type = 'proration') {
    /*
     * $types can be any of the following
     *
     * none       = no payment
     * full       = from expiration date of last txn until next billing date
     * period     = full amount for current period -- regardless of date
     * proration  = prorated amount for current period only (default)
     *
     */

    //If type is none, or the subscription hasn't expired -- return false
    if($type == 'none' || !$this->is_expired()) {
      return false;
    }

    // Calculate Next billing time
    $expired_at = strtotime($this->expires_at);
    $now = time();
    $time_elapsed = $now - $expired_at;
    $periods_elapsed = (int)ceil($time_elapsed / MeprUtils::days($this->days_in_this_period())); //We want to round this up to INT
    $next_billing = $now;
    $subscription_cost_per_day = (float)((float)$this->price / $this->days_in_this_period());

    //$periods_elapsed should never be 0, but just in case:
    if($periods_elapsed <= 0) {
      $periods_elapsed = 1;
    }

    switch($this->period_type) {
      case 'weeks':
        $next_billing = $expired_at + MeprUtils::weeks($periods_elapsed*$this->period);
        break;
      case 'months':
        $renewal_dom = date('j', strtotime($this->renewal_base_date));
        $next_billing = $expired_at + MeprUtils::months($periods_elapsed*$this->period, $expired_at, false, $renewal_dom);
        break;
      case 'years':
        $next_billing = $expired_at + MeprUtils::years($periods_elapsed*$this->period, $expired_at);
        break;
    }

    //Handle $type = period
    if($type == 'period') {
      $full_price = MeprUtils::format_float($this->price);

      return (object)array('proration' => $full_price, 'next_billing' => $next_billing);
    }

    //Handle $type = full
    if($type == 'full') {
      //Multiply $this->price * $periods_elapsed to get a nice pretty catchup number
      $sub_price = MeprUtils::format_float($this->price);
      $full_proration = MeprUtils::format_float(($sub_price * $periods_elapsed));

      return (object)array('proration' => $full_proration, 'next_billing' => $next_billing);
    }

    //All other $types have been handled, so if we made it here just calculate $type = 'proration'
    $seconds_till_billing = $next_billing - $now;
    $days_till_billing = (int)($seconds_till_billing / MeprUtils::days(1));
    $proration = MeprUtils::format_float($subscription_cost_per_day * $days_till_billing);

    return (object)compact('proration', 'next_billing');
  }

  //Used for the catchup payments, pretty much Authorize.net is the only one using this
  public function first_real_payment_failed() {
    if($sub->txn_count > 1) {
      return false;
    }

    if($this->trial && $this->trial_amount > 0.00) {
      //Only the trial payment exists?
      if($this->txn_count == 1) {
        //Check for failed
        if($this->has_a_txn_failed()) {
          return true;
        }
      }
    }
    else {
      //No real payments recorded yet?
      if($this->txn_count == 0) {
        //Check for failed
        if($this->has_a_txn_failed()) {
          return true;
        }
      }
    }

    return false;
  }

  //Used for the catchup payments, pretty much Authorize.net is the only one using this
  public function has_a_txn_failed() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare("SELECT COUNT(*) FROM {$mepr_db->transactions} WHERE status = %s AND subscription_id = %d", MeprTransaction::$failed_str, $this->id);
    $res = $wpdb->get_var($q);

    if($res) {
      return true;
    }

    return false;
  }

  public function apply_tax($subtotal, $num_decimals = 2, $gross = 0.00) {
    $mepr_options = MeprOptions::fetch();
    $usr = $this->user();
    $prd = $this->product();
    $calculate_taxes = get_option('mepr_calculate_taxes');

    // Now try to calculate tax info from the user info
    if($prd->tax_exempt) {
      list($this->price, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = array($gross, $gross, 0.00, 0.00, '', 'standard');
    }
    elseif($calculate_taxes && !$prd->tax_exempt && ($usr->ID != 0 || ((int)$usr->ID == 0 && $mepr_options->attr('tax_calc_location') == 'merchant'))) {
      list($this->price, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = $usr->calculate_tax($subtotal, $num_decimals);
    }
    else { // If all else fails, let's blank out the tax info
      list($this->price, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class) = array($subtotal, $subtotal, 0.00, 0.00, '', 'standard');
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
   * This will never check for tax inclusion because it's the gross
   * it doesn't matter (since we already know the gross amount).
   */
  public function set_gross($gross) {
    $usr = $this->user();
    $subtotal = $usr->calculate_subtotal($gross);
    $this->apply_tax($subtotal, 2, $gross);
  }

  public function tax_info() {
    return array($this->price, $this->total, $this->tax_rate, $this->tax_amount, $this->tax_desc, $this->tax_class);
  }

  /***** MAGIC METHOD HANDLERS *****/
  protected function mgm_first_txn_id($mgm, $val = '') {
    global $wpdb;
    $mepr_db = new MeprDb();
    $where = '';

    switch($mgm) {
      case 'get':
        //Get the first real payment
        if(isset($_REQUEST['mepr_get_real_payment'])) {
          $where = $wpdb->prepare("AND t.txn_type = %s AND t.status = %s", MeprTransaction::$payment_str, MeprTransaction::$complete_str);
        }

        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.subscription_id = %d
                   {$where}
             ORDER BY t.id ASC
             LIMIT 1
          ",
          $this->rec->id
        );

        $txn_id = $wpdb->get_var($q);

        //No real payments yet, so let's look for a confirmation?
        if(empty($txn_id)) {
          $q = $wpdb->prepare("
              SELECT t.id
                FROM {$mepr_db->transactions} AS t
               WHERE t.subscription_id = %d
               ORDER BY t.id ASC
               LIMIT 1
            ",
            $this->rec->id
          );

          $txn_id = $wpdb->get_var($q);
        }

        return empty($txn_id) ? false : $txn_id;
      default:
        return true;
    }
  }

  protected function mgm_latest_txn_id($mgm, $val = '') {
    global $wpdb;
    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT t.id
              FROM {$mepr_db->transactions} AS t
             WHERE t.subscription_id = %d
               AND t.status IN (%s, %s)
             ORDER BY t.id DESC
             LIMIT 1
          ",
          $this->rec->id,
          MeprTransaction::$complete_str,
          MeprTransaction::$confirmed_str
        );

        $id = $wpdb->get_var($q);
        return empty($id) ? false : $id;
      default:
        return true;
    }
  }

  protected function mgm_expiring_txn_id($mgm, $val = '') {
    global $wpdb;
    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
          SELECT t.id
            FROM {$mepr_db->transactions} AS t
           WHERE t.subscription_id=%d
             AND t.status IN (%s,%s)
             AND ( t.expires_at = %s
               OR ( t.expires_at <> %s
                 AND t.expires_at=(
                   SELECT MAX(t2.expires_at)
                     FROM {$mepr_db->transactions} as t2
                    WHERE t2.subscription_id=%d
                      AND t2.status IN (%s,%s)
                 )
               )
             ) " .
          // If there's a lifetime and an expires at, favor the lifetime
          "
            ORDER BY t.expires_at
            LIMIT 1
          ",
          $this->rec->id,
          MeprTransaction::$confirmed_str,
          MeprTransaction::$complete_str,
          MeprUtils::db_lifetime(),
          MeprUtils::db_lifetime(),
          $this->rec->id,
          MeprTransaction::$confirmed_str,
          MeprTransaction::$complete_str
        );

        $id = $wpdb->get_var($q);
        return empty($id) ? false : $id;
      default:
        return true;
    }
  }

  protected function mgm_txn_count($mgm, $val = '') {
    global $wpdb;

    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        $q = $wpdb->prepare("
            SELECT COUNT(*)
              FROM {$mepr_db->transactions} AS t
             WHERE t.subscription_id=%d
               AND t.status=%s
          ",
          $this->rec->id,
          MeprTransaction::$complete_str
        );

        return $wpdb->get_var($q);
      default:
        return true;
    }
  }

  protected function mgm_expires_at($mgm, $val = '') {
    global $wpdb;

    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        if($this->status == self::$pending_str) { return false; } //pending subs should not be active - see self::is_active()

        $q = $wpdb->prepare("
          SELECT t.expires_at
            FROM {$mepr_db->transactions} AS t
           WHERE t.subscription_id=%d
             AND t.status IN (%s,%s)
             AND ( t.expires_at = %s
               OR ( t.expires_at <> %s
                 AND t.expires_at=(
                   SELECT MAX(t2.expires_at)
                     FROM {$mepr_db->transactions} as t2
                    WHERE t2.subscription_id=%d
                      AND t2.status IN (%s,%s)
                 )
               )
             )
        " .
        // If there's a lifetime and an expires at, favor the lifetime
        "
           ORDER BY t.expires_at
           LIMIT 1
        ",
        $this->rec->id,
        MeprTransaction::$confirmed_str,
        MeprTransaction::$complete_str,
        MeprUtils::db_lifetime(),
        MeprUtils::db_lifetime(),
        $this->rec->id,
        MeprTransaction::$confirmed_str,
        MeprTransaction::$complete_str);

        $expires_at = $wpdb->get_var($q);

        if(!$this->id || is_null($expires_at) || false === $expires_at) {
          $expires_at = $this->get_expires_at();
          // Convert to mysql date
          $expires_at = MeprUtils::ts_to_mysql_date($expires_at);
        }

        return $expires_at;
      default:
        return true;
    }
  }

  protected function mgm_next_billing_at($mgm, $value = '') {
    global $wpdb;

    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        if( $this->status == MeprSubscription::$active_str and
            !empty($this->expires_at) and
            $this->expires_at != MeprUtils::db_lifetime() and
            ( !$this->limit_cycles or
              ( $this->limit_cycles and
                $this->txn_count < $this->limit_cycles_num ) ) ) {
          return $this->expires_at;
        }
        else {
          return false;
        }
      default:
        return true;
    }
  }

  protected function mgm_ID($mgm, $value = '') {
    global $wpdb;

    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        return $this->rec->id;
      case 'set':
        $this->rec->id = $value;
      default:
        return true;
    }
  }

  /** This is the effective start date for the subscription. Under normal circumstances
    * this is just the created_at date but when a subscription is paused and resumed
    * the startdate will reset to whenever the latest resume_at date occurs.
    */
  protected function mgm_renewal_base_date($mgm, $value = '') {
    global $wpdb;

    $mepr_db = new MeprDb();

    switch($mgm) {
      case 'get':
        $pm = $this->payment_method();
        if($pm instanceof MeprBaseGateway) {
          return $pm->get_renewal_base_date($this);
        }
        return $this->created_at;
      case 'set':
        return false;
      default:
        return true;
    }
  }

  // SPECIFICALLY TO USE IN MEPRDB TO MIGRATE SUBSCRIPTIONS TO IT'S NEW TABLE
  public static function upgrade_attrs() {
    return array(
      'subscr_id'           => 'CONCAT("mp-sub-",UUID_SHORT())',
      'gateway'             => 'manual',
      'user_id'             => 0,
      'product_id'          => 0,
      'coupon_id'           => 0,
      'price'               => 0.00,
      'total'               => "{{price}}",
      'period'              => 1,
      'period_type'         => 'months',
      'limit_cycles'        => false,
      'limit_cycles_num'    => 0,
      'limit_cycles_action' => null,
      'prorated_trial'      => false,
      'trial'               => false,
      'trial_days'          => 0,
      'trial_amount'        => 0.00,
      'status'              => MeprSubscription::$pending_str,
      'created_at'          => null,
      'tax_rate'            => 0.00,
      'tax_amount'          => 0.00,
      'tax_desc'            => '',
      'tax_class'           => 'standard',
      'cc_last4'            => null,
      'cc_exp_month'        => null,
      'cc_exp_year'         => null,
    );
  }

  private static function col_stmt($slug,$default) {
    global $wpdb;

    if(is_null($default)) {
      // A left join will naturally produce a NULL value if not found...no IFNULL needed
      $col = "pm_{$slug}.meta_value";
    }
    else if($slug=='subscr_id') {
      $col = "IFNULL(pm_{$slug}.meta_value,{$default})";
    }
    else if(is_integer($default)) {
      $col = $wpdb->prepare("IFNULL(pm_{$slug}.meta_value,%d)", $default);
    }
    else if(is_float($default)) {
      $col = $wpdb->prepare("IFNULL(pm_{$slug}.meta_value,%f)", $default);
    }
    else {
      $col = $wpdb->prepare("IFNULL(pm_{$slug}.meta_value,%s)", $default);
    }

    return $col;
  }

  // SPECIFICALLY TO USE IN MEPRDB TO MIGRATE SUBSCRIPTIONS TO IT'S NEW TABLE
  public static function upgrade_query($subscription_id=null, $exclude_already_upgraded=false, $limit='') {
    global $wpdb;

    $mepr_options = MeprOptions::fetch();
    $mepr_db      = MeprDb::fetch();

    //$cols = array('id' => 'DISTINCT pst.ID');
    $cols = array('id' => 'pst.ID');

    // Add postmeta columns
    // Must be the same order and name as the table itself
    $pms = self::upgrade_attrs();

    foreach($pms as $slug => $default) {
      if(preg_match("/^\{\{([^\{\}]*)\}\}$/",$default,$m)) {
        $cols[$slug] = "IFNULL(pm_{$slug}.meta_value," . self::col_stmt($m[1],$pms[$m[1]]) . ')';
      }
      else {
        $cols[$slug] = self::col_stmt($slug,$default);
      }
    }

    // The database can handle these
    //$cols['tax_compound'] = 0;
    //$cols['tax_shipping'] = 1;

    $args = array($wpdb->prepare('pst.post_type = %s', 'mepr-subscriptions'));

    // Don't upgrade any that are already upgraded
    if($exclude_already_upgraded) {
      $args[] = "pst.ID NOT IN (SELECT id FROM {$mepr_db->subscriptions})";
    }

    if(!is_null($subscription_id)) {
      $args[] = $wpdb->prepare('pst.ID = %d', $subscription_id);
    }

    $joins = array();
    //$ignore_cols = array('tax_compound','tax_shipping');

    // Add postmeta joins
    foreach( $pms as $slug => $default ) {
      //if(!in_array($slug, $ignore_cols)) {
        $joins[] = self::join_pm($slug, 'LEFT JOIN');
      //}
    }

    if($limit===false) {
      $paged='';
      $perpage=0;
    }
    else {
      $paged=1;
      $perpage=$limit;
    }

    $order_by = 'pst.ID';
    $order = 'DESC';

    return MeprDb::list_table($cols, "{$wpdb->posts} AS pst", $joins,
                              $args, $order_by, $order, $paged, '', 'any',
                              $perpage, false, true);
  }

  public static function upgrade_table($subscription_id=null, $exclude_already_upgraded=false, $limit='') {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $subq = self::upgrade_query($subscription_id,$exclude_already_upgraded,$limit);
    $attrs = 'id,'.implode(',',array_keys(self::upgrade_attrs()));

    $subq = "INSERT IGNORE INTO {$mepr_db->subscriptions} ({$attrs}) {$subq['query']}";
    $res = $wpdb->query($subq);

    if($res === false) { //$res will never return a WP_Error
      throw new MeprDbMigrationException(sprintf(__('MemberPress database migration failed: %1$s %2$s', 'memberpress'),$wpdb->last_error,$subq));
    }
  }

  // STILL USING THIS TO MIGRATE THE DATABASE
  private static function join_pm( $slug, $join='LEFT JOIN', $post='pst' ) {
    global $wpdb;
    $vals = self::legacy_str_vals();

    $class = new ReflectionClass( 'MeprSubscription' );
    $val = $vals[$slug];

    return $wpdb->prepare( "{$join} {$wpdb->postmeta} AS pm_{$slug}
                                 ON pm_{$slug}.post_id = {$post}.ID
                                AND pm_{$slug}.meta_key = %s", $val );
  }

  // STILL USING THIS TO MIGRATE THE DATABASE
  private static function legacy_str_vals() {
    return array(
      'subscr_id'           => '_mepr_subscr_id',
      'user_id'             => '_mepr_subscr_user_id',
      'gateway'             => '_mepr_subscr_gateway',
      'product_id'          => '_mepr_subscr_product_id',
      'coupon_id'           => '_mepr_subscr_coupon_id',
      'price'               => '_mepr_subscr_price',
      'period'              => '_mepr_subscr_period',
      'period_type'         => '_mepr_subscr_period_type',
      'limit_cycles'        => '_mepr_subscr_limit_cycles',
      'limit_cycles_num'    => '_mepr_subscr_limit_cycles_num',
      'limit_cycles_action' => '_mepr_subscr_limit_cycles_action',
      'prorated_trial'      => '_mepr_subscr_prorated_trial',
      'trial'               => '_mepr_subscr_trial',
      'trial_days'          => '_mepr_subscr_trial_days',
      'trial_amount'        => '_mepr_subscr_trial_amount',
      'status'              => '_mepr_subscr_status',
      'created_at'          => '_mepr_subscr_created_at',
      'cc_last4'            => '_mepr_subscr_cc_last4',
      'cc_exp_month'        => '_mepr_subscr_cc_month_exp',
      'cc_exp_year'         => '_mepr_subscr_cc_year_exp',
      'total'               => '_mepr_subscr_total',
      'tax_rate'            => '_mepr_subscr_tax_rate',
      'tax_amount'          => '_mepr_subscr_tax_amount',
      'tax_desc'            => '_mepr_subscr_tax_desc',
      'tax_class'           => '_mepr_subscr_tax_class',
      'cpt'                 => 'mepr-subscriptions',
    );
  }

} //End class
