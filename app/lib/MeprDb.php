<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprDb
{
  public $transactions;

  public function __construct() {
    global $wpdb;

    $this->transactions = "{$wpdb->prefix}mepr_transactions";
    $this->events = "{$wpdb->prefix}mepr_events";
    $this->jobs = "{$wpdb->prefix}mepr_jobs";
    $this->tax_rates = "{$wpdb->prefix}mepr_tax_rates";
    $this->tax_rate_locations = "{$wpdb->prefix}mepr_tax_rate_locations";
  }

  public function do_upgrade() {
    $old_db_version = get_option('mepr_db_version', 0);
    return (version_compare(MEPR_VERSION, $old_db_version, '>'));
  }

  /** Will automatically run once when the plugin is upgraded */
  public function upgrade() {
    global $wpdb;

    //This line makes it safe to check this code during admin_init action.
    if($this->do_upgrade()) {
      $old_db_version = get_option('mepr_db_version', 0);
      $this->before_upgrade($old_db_version);

      $charset_collate = '';
      if($wpdb->has_cap('collation')) {
        if(!empty($wpdb->charset)) {
          $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }
        if(!empty($wpdb->collate)) {
          $charset_collate .= " COLLATE {$wpdb->collate}";
        }
      }

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

      /* Create/Upgrade Board Posts Table */
      $txns =
        "CREATE TABLE {$this->transactions} (
          id bigint(20) NOT NULL auto_increment,
          amount decimal(16,2) NOT NULL,
          total decimal(16,2) DEFAULT 0,
          tax_amount decimal(16,2) DEFAULT 0,
          tax_rate decimal(5,2) DEFAULT 0,
          tax_desc varchar(255) DEFAULT '',
          tax_compound int(1) DEFAULT 0,
          tax_shipping int(1) DEFAULT 1,
          tax_class varchar(255) DEFAULT 'standard',
          user_id bigint(20) NOT NULL,
          product_id bigint(20) NOT NULL,
          coupon_id bigint(20) DEFAULT NULL,
          trans_num varchar(255) DEFAULT NULL,
          status varchar(255) DEFAULT '".MeprTransaction::$pending_str."',
          txn_type varchar(255) DEFAULT '".MeprTransaction::$payment_str."',
          response text DEFAULT NULL,
          gateway varchar(255) DEFAULT 'MeprPayPalGateway',
          subscription_id bigint(20) DEFAULT NULL,
          ip_addr varchar(255) DEFAULT NULL,
          prorated tinyint(1) DEFAULT 0,
          created_at datetime NOT NULL,
          expires_at datetime DEFAULT NULL,
          PRIMARY KEY  (id),
          KEY amount (amount),
          KEY total (total),
          KEY tax_amount (tax_amount),
          KEY tax_rate (tax_rate),
          KEY tax_desc (tax_desc),
          KEY tax_compound (tax_compound),
          KEY tax_shipping (tax_shipping),
          KEY tax_class (tax_class),
          KEY user_id (user_id),
          KEY status (status),
          KEY txn_type (txn_type),
          KEY product_id (product_id),
          KEY coupon_id (coupon_id),
          KEY trans_num (trans_num),
          KEY subscription_id (subscription_id),
          KEY gateway (gateway),
          KEY ip_addr (ip_addr),
          KEY prorated (prorated),
          KEY created_at (created_at),
          KEY expires_at (expires_at)
        ) {$charset_collate};";

      dbDelta($txns);

      $events =
        "CREATE TABLE {$this->events} (
          id bigint(20) NOT NULL auto_increment,
          event varchar(255) NOT NULL DEFAULT 'login',
          ip varchar(255) DEFAULT NULL,
          args varchar(255) DEFAULT NULL,
          evt_id varchar(255) NOT NULL,
          evt_id_type varchar(255) NOT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY event_ip (ip),
          KEY event_args (args),
          KEY event_event (event),
          KEY event_evt_id (evt_id),
          KEY event_evt_id_type (evt_id_type),
          KEY event_created_at (created_at)
        ) {$charset_collate};";

      dbDelta($events);

      $jobs =
        "CREATE TABLE {$this->jobs} (
          id bigint(20) NOT NULL auto_increment,
          runtime datetime NOT NULL,
          firstrun datetime NOT NULL,
          lastrun datetime DEFAULT NULL,
          priority bigint(20) DEFAULT 10,
          tries bigint(20) DEFAULT 0,
          class varchar(255) NOT NULL,
          batch varchar(255) DEFAULT NULL,
          args text DEFAULT '',
          reason text DEFAULT '',
          status varchar(255) DEFAULT 'pending',
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY job_runtime (runtime),
          KEY job_firstrun (firstrun),
          KEY job_lastrun (lastrun),
          KEY job_status (status),
          KEY job_priority (priority),
          KEY job_tries (tries),
          KEY job_class (class),
          KEY job_batch (batch),
          KEY job_created_at (created_at)
        ) {$charset_collate};";

      dbDelta($jobs);

      $tax_rates =
        "CREATE TABLE {$this->tax_rates} (
          id bigint(20) NOT NULL auto_increment,
          tax_country varchar(255) NOT NULL DEFAULT '',
          tax_state varchar(255) NOT NULL DEFAULT '',
          tax_rate varchar(255) NOT NULL DEFAULT '',
          tax_desc varchar(255) NOT NULL DEFAULT '',
          tax_priority bigint(20) NOT NULL DEFAULT 0,
          tax_compound int(1) NOT NULL DEFAULT 0,
          tax_shipping int(1) NOT NULL DEFAULT 1,
          tax_order bigint(20) NOT NULL,
          tax_class varchar(255) NOT NULL DEFAULT 'standard',
          PRIMARY KEY  (id),
          KEY mp_tax_country (tax_country),
          KEY mp_tax_state (tax_state),
          KEY mp_tax_rate (tax_rate),
          KEY mp_tax_desc (tax_desc),
          KEY mp_tax_order (tax_order),
          KEY mp_tax_class (tax_class),
          KEY mp_tax_priority (tax_priority),
          KEY mp_tax_compound (tax_compound),
          KEY mp_tax_shipping (tax_shipping)
        ) {$charset_collate};";

      dbDelta($tax_rates);

      // This is purely so we can lump multiple cities and zips into the same rate
      $tax_rate_locations =
        "CREATE TABLE {$this->tax_rate_locations} (
          id bigint(20) NOT NULL auto_increment,
          tax_rate_id bigint(20) NOT NULL,
          location_code varchar(200) NOT NULL,
          location_type varchar(40) NOT NULL,
          PRIMARY KEY  (id),
          KEY mp_tax_rate_id (tax_rate_id),
          KEY mp_location_type (location_type),
          KEY mp_location_code (location_code),
          KEY mp_location_type_code (location_type,location_code)
        ) {$charset_collate};";

      dbDelta($tax_rate_locations);

      $this->after_upgrade($old_db_version);

      // Ensure that the rewrite rules are flushed & in place
      MeprUtils::flush_rewrite_rules();

      // Update the version in the DB now that we've run the upgrade
      update_option('mepr_db_version', MEPR_VERSION);
    }
  }

  public function before_upgrade($curr_db_version) {
    global $wpdb;

    // TODO: We should delete this at some point in the future when we're
    // confident that no members are still using version 1.0.6 of MemberPress
    MeprOptions::migrate_to_new_unauth_system();

    /*
    if( version_compare( $curr_db_version, '1.1.8b11', '<=' ) ) {
      // Convert amount field to use decimal instead of float
      // (More accurate for monetary transactions)
      $wpdb->query("ALTER TABLE {$this->transactions} MODIFY amount decimal(16,2)");
    }
    */
  }

  public function after_upgrade($curr_db_version) {
    global $wpdb;

    // Forcably take care of the user_id column
    if( $this->column_exists( $this->events, 'user_id' ) ) {
      $wpdb->query( "UPDATE `{$this->events}` SET evt_id_type='users', evt_id=user_id" );
      $this->remove_column( $this->events, 'event_user_id', 'KEY' );
      $this->remove_column( $this->events, 'user_id' );
    }
  }

  public function column_exists($table, $column) {
    global $wpdb;

    $query = "SELECT * " .
               "FROM information_schema.COLUMNS " .
              "WHERE TABLE_SCHEMA = %s " .
                "AND TABLE_NAME = %s " .
                "AND COLUMN_NAME = %s";

    $query = $wpdb->prepare( $query, DB_NAME, $table, $column );
    $res = $wpdb->get_results( $query );

    return !empty($res);
  }

  public function remove_column($table, $column, $type='COLUMN') {
    global $wpdb;

    $query = "ALTER TABLE {$table} DROP {$type} {$column}";

    return $wpdb->query( $query );
  }

  public function create_record($table, $args, $record_created_at = true) {
    global $wpdb;

    $cols = array();
    $vars = array();
    $values = array();

    $i = 0;

    foreach($args as $key => $value) {
      $cols[$i] = "`$key`";
      if(is_numeric($value) and preg_match('!\.!',$value))
        $vars[$i] = '%f';
      else if(is_int($value) or is_bool($value))
        $vars[$i] = '%d';
      else
        $vars[$i] = '%s';

      if(is_bool($value))
        $values[$i] = $value ? 1 : 0;
      else
        $values[$i] = $value;
      $i++;
    }

    if($record_created_at) {
      $cols[$i] = 'created_at';
      $vars[$i] = "'".date('c')."'";
    }

    if(empty($cols))
      return false;

    $cols_str = implode(',', $cols);
    $vars_str = implode(',', $vars);

    $query = "INSERT INTO {$table} ({$cols_str}) VALUES ({$vars_str})";
    $query = $wpdb->prepare($query, $values);

    $query_results = $wpdb->query($query);

    if($query_results) {
      return $wpdb->insert_id;
    }
    else {
      return false;
    }
  }

  public function update_record($table, $id, $args) {
    global $wpdb;

    if(empty($args) or empty($id))
      return false;

    $set = '';
    $values = array();
    foreach($args as $key => $value) {
      if(empty($set))
        $set .= ' SET';
      else
        $set .= ',';

      $set .= " `{$key}`=";

      if(is_numeric($value) and preg_match('!\.!',$value))
        $set .= "%f";
      else if(is_int($value) or is_bool($value))
        $set .= "%d";
      else
        $set .= "%s";

      if(is_bool($value))
        $values[] = $value ? 1 : 0;
      else
        $values[] = $value;
    }

    $values[] = $id;
    $query = "UPDATE {$table}{$set} WHERE id=%d";
    $query = $wpdb->prepare($query, $values);
    $wpdb->query($query);

    return $id;
  }

  public function delete_records($table, $args) {
    global $wpdb;
    extract(MeprDb::get_where_clause_and_values($args));

    $query = "DELETE FROM {$table}{$where}";
    $query = $wpdb->prepare($query, $values);

    return $wpdb->query($query);
  }

  public function get_count($table, $args=array()) {
    global $wpdb;
    extract(MeprDb::get_where_clause_and_values($args));

    $query = "SELECT COUNT(*) FROM {$table}{$where}";
    $query = $wpdb->prepare($query, $values);
    return $wpdb->get_var($query);
  }

  public static function get_where_clause_and_values($args) {
    $where = '';
    $values = array();
    foreach($args as $key => $value) {
      if(!empty($where))
        $where .= ' AND';
      else
        $where .= ' WHERE';

      $where .= " `{$key}`=";

      if(is_numeric($value) and preg_match('!\.!',$value))
        $where .= "%f";
      else if(is_int($value) or is_bool($value))
        $where .= "%d";
      else
        $where .= "%s";

      if(is_bool($value))
        $values[] = $value ? 1 : 0;
      else
        $values[] = $value;
    }

    return compact('where', 'values');
  }

  public function get_one_record($table, $args = array(), $return_type=OBJECT) {
    global $wpdb;

    extract(MeprDb::get_where_clause_and_values($args));
    $query = "SELECT * FROM {$table}{$where} LIMIT 1";
    $query = $wpdb->prepare($query, $values);

    return $wpdb->get_row($query, $return_type);
  }

  public function get_records($table, $args=array(), $order_by='', $limit='', $return_type=OBJECT) {
    global $wpdb;

    extract(MeprDb::get_where_clause_and_values($args));

    if(!empty($order_by)) { $order_by = " ORDER BY {$order_by}"; }

    if(!empty($limit)) { $limit = " LIMIT {$limit}"; }

    $query = "SELECT * FROM {$table}{$where}{$order_by}{$limit}";
    $query = $wpdb->prepare($query, $values);
    return $wpdb->get_results($query,$return_type);
  }

  /* Built to work with WordPress' built in WP_List_Table class */
  public static function list_table( $cols,
                                     $from,
                                     $joins=array(),
                                     $args=array(),
                                     $order_by='',
                                     $order='',
                                     $paged='',
                                     $search='',
                                     $perpage=10,
                                     $countonly=false,
                                     $queryonly=false ) {
    global $wpdb;

    // Setup selects
    $col_str_array = array();
    foreach($cols as $col => $code)
      $col_str_array[] = "{$code} AS {$col}";

    $col_str = implode(", ", $col_str_array);

    // Setup Joins
    $important_joins = $normal_joins = array();

    if(!empty($joins)) {
      foreach($joins as $join) {
        if(preg_match('/\/\*\sIMPORTANT/', $join)) {
          $important_joins[] = $join;
        }
        else {
          $normal_joins[] = $join;
        }
      }
      $important_join_str = " ".implode(" ", $important_joins);
      $normal_join_str = " ".implode(" ", $normal_joins);
      $join_str = " ".implode(" ", $joins);
    }

    $args_str = implode(' AND ', $args);

    /* -- Ordering parameters -- */
    //Parameters that are going to be used to order the result
    $order_by = (!empty($order_by) and !empty($order))?($order_by = ' ORDER BY '.$order_by.' '.$order):'';

    //Page Number
    if(empty($paged) or !is_numeric($paged) or $paged<=0)
      $paged=1;

    $limit = '';
    //adjust the query to take pagination into account
    if(!empty($paged) and !empty($perpage)) {
      $offset=($paged - 1) * $perpage;
      $limit = ' LIMIT '.(int)$offset.','.(int)$perpage;
    }

    // Searching
    $search_str = "";
    $searches = array();
    if(!empty($search)) {
      $terms = explode(' ', $search); //BOOM, much more robust search now

      foreach($terms as $term)
        foreach($cols as $col => $code)
          $searches[] = "{$code} LIKE '%{$term}%'";

      if(!empty($searches))
        $search_str = implode(" OR ", $searches);

      // If we're doing a search we need all the joins in place or we get errors
      $important_join_str = $join_str;
    }

    $conditions = "";

    // Pull Searching into where
    if(!empty($args)) {
      if(!empty($searches))
        $conditions = " WHERE $args_str AND ({$search_str})";
      else
        $conditions = " WHERE $args_str";
    }
    else {
      if(!empty($searches))
        $conditions = " WHERE {$search_str}";
    }

    $query = "SELECT {$col_str} FROM {$from}{$join_str}{$conditions}{$order_by}{$limit}";
    $total_query = "SELECT COUNT(*) FROM {$from}{$important_join_str}{$conditions}";

    if($queryonly) {
      return compact('query', 'total_query');
    }
    else {
      // Allows us to run the bazillion JOINS we use on the list tables
      $wpdb->query("SET SQL_BIG_SELECTS=1");
      $results = $wpdb->get_results($query);
      $count = $wpdb->get_var($total_query);
      return array('results' => $results, 'count' => $count);
    }
  }
}

