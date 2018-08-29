<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUsage {
  public function uuid($regenerate=false) {
    $uuid_key = 'mepr-usage-uuid';
    $uuid = get_option($uuid_key);

    if($regenerate || empty($uuid)) {
      // Definitely not cryptographically secure but
      // close enough to provide an unique id
      $uuid = md5(uniqid().site_url());
      update_option($uuid_key,$uuid);
    }

    return $uuid;
  }

  public function snapshot() {
    global $wpdb, $mepr_update;

    $txn_stats = MeprReports::transaction_stats();
    $sub_stats = MeprReports::subscription_stats();

    $last_week = MeprUtils::ts_to_mysql_date(time()-MeprUtils::weeks(1));
    $weekly_txn_stats = MeprReports::transaction_stats($last_week);
    $weekly_sub_stats = MeprReports::subscription_stats($last_week);

    $snap = array(
      'uuid'               => $this->uuid(),
      'php_version'        => phpversion(),
      'mysql_version'      => $wpdb->db_version(),
      'os'                 => php_uname('s'),
      'webserver'          => $_SERVER["SERVER_SOFTWARE"],
      'active_license'     => MeprUpdateCtrl::is_activated(),
      'edition'            => MEPR_EDITION,
      'all_users'          => MeprReports::get_total_wp_users_count(),
      'all_members'        => MeprReports::get_total_members_count(),
      'active_members'     => MeprReports::get_active_members_count(),
      'inactive_members'   => MeprReports::get_inactive_members_count(),
      'free_members'       => MeprReports::get_free_active_members_count(),
      'timestamp'          => gmdate('c'),
      'memberships'        => $this->memberships(),
      'plugins'            => $this->plugins(),
      'gateways'           => $this->gateways(),
      //'ltv'                => MeprReports::get_average_lifetime_value(),
      //'mrr'                => '',
      //'arr'                => '',
    );

    //if(!empty($weekly_txn_stats)) {
    //  $snap['week_revenue'] = $weekly_txn_stats->complete_sum_total;
    //  $snap['week_transactions'] = $weekly_txn_stats->complete;
    //}

    //if(!empty($sub_stats)) {
    //  $snap['subscriptions'] = $sub_stats->enabled;
    //}

    //if(!empty($weekly_sub_stats)) {
    //  $snap['week_subscriptions'] = $weekly_sub_stats->enabled;
    //}

    //if(!empty($txn_stats)) {
    //  $snap['transactions'] = $txn_stats->complete;
    //}

    return MeprHooks::apply_filters('mepr_usage_snapshot', $snap);
  }

  private function memberships() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $objs = MeprProduct::get_all();

    //$q = $wpdb->prepare("
    //    SELECT COUNT(*)
    //      FROM {$mepr_db->transactions}
    //     WHERE status=%s
    //       AND txn_type=%s
    //       AND created_at >= %s
    //  ",
    //  'complete',
    //  'payment',
    //  MeprUtils::ts_to_mysql_date(time()-MeprUtils::weeks(1))
    //);

    $memberships = array();
    foreach($objs as $obj) {
      //$mq = $q . $wpdb->prepare(" AND product_id=%d", $obj->ID);
      $memberships[] = array(
        'amount'       => $obj->price,
        'recurring'    => !$obj->is_one_time_payment(),
        'period_type'  => $obj->period_type,
        'period'       => $obj->period,
        'trial'        => $obj->trial,
        'limit_cycles' => $obj->limit_cycles,
        //'weekly_transactions' => $wpdb->get_var($mq),
      );
    }

    return $memberships;
  }

  private function plugins() {
    $plugin_list = get_plugins();

    $plugins = array();
    foreach($plugin_list as $slug => $info) {
      $plugins[] = array(
        'name'        => $info['Name'],
        'slug'        => $slug,
        'version'     => $info['Version'],
        'active'      => is_plugin_active($slug),
        //'plugin_uri'  => $info['PluginURI'],
        //'description' => $info['Description'],
        //'author'      => $info['Author'],
        //'author_uri'  => $info['AuthorURI'],
        //'text_domain' => $info['TextDomain'],
        //'domain_path' => $info['DomainPath'],
        //'network'     => $info['Network'],
        //'title'       => $info['Title'],
        //'author_name' => $info['AuthorName'],
      );
    }

    return $plugins;
  }

  private function gateways() {
    $mepr_options = MeprOptions::fetch();

    $pms = $mepr_options->payment_methods(false);

    $gateways = array();
    foreach($pms as $pm) {
      $gateways[] = array(
        'name' => $pm->name,
        'livemode' => !$pm->is_test_mode(),
      );
    }

    return $gateways;
  }

} //End class

