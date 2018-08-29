<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprReports {
  public static function get_transactions_count($status, $day = false, $month = false, $year = false, $product = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";
    $andday = ($day)?" AND DAY(created_at) = {$day}":"";
    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andproduct = (!isset($product) || $product == "all")?"":" AND product_id = {$product}";

    $q = "SELECT COUNT(*)
            FROM {$mepr_db->transactions}
            WHERE status = %s
              AND txn_type = %s
              {$andmonth}
              {$andday}
              {$andyear}
              {$andproduct}";

    return (int)$wpdb->get_var($wpdb->prepare($q, $status, MeprTransaction::$payment_str));
  }

  public static function get_revenue($month = false, $day = false, $year = false, $product = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";
    $andday = ($day)?" AND DAY(created_at) = {$day}":"";
    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andproduct = (!isset($product) || $product == "all")?"":" AND product_id = {$product}";

    $q = "SELECT SUM(amount)
            FROM {$mepr_db->transactions}
            WHERE status = %s
              AND txn_type = %s
              {$andmonth}
              {$andday}
              {$andyear}
              {$andproduct}";

    return $wpdb->get_var($wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$payment_str));
  }

  public static function get_collected($month = false, $day = false, $year = false, $product = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";
    $andday = ($day)?" AND DAY(created_at) = {$day}":"";
    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andproduct = (!isset($product) || $product == "all")?"":" AND product_id = {$product}";

    $q = "SELECT (SUM(amount)+SUM(tax_amount))
            FROM {$mepr_db->transactions}
            WHERE status IN (%s,%s)
              AND txn_type = %s
              {$andmonth}
              {$andday}
              {$andyear}
              {$andproduct}";

    return $wpdb->get_var($wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$refunded_str, MeprTransaction::$payment_str));
  }

  public static function get_refunds($month = false, $day = false, $year = false, $product = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";
    $andday = ($day)?" AND DAY(created_at) = {$day}":"";
    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andproduct = (!isset($product) || $product == "all")?"":" AND product_id = {$product}";

    $q = "SELECT (SUM(amount)+SUM(tax_amount))
            FROM {$mepr_db->transactions}
            WHERE status = %s
              AND txn_type = %s
              {$andmonth}
              {$andday}
              {$andyear}
              {$andproduct}";

    return $wpdb->get_var($wpdb->prepare($q, MeprTransaction::$refunded_str, MeprTransaction::$payment_str));
  }

  public static function get_taxes($month = false, $day = false, $year = false, $product = null) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";
    $andday = ($day)?" AND DAY(created_at) = {$day}":"";
    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andproduct = (!isset($product) || $product == "all")?"":" AND product_id = {$product}";

    $q = "SELECT SUM(tax_amount)
            FROM {$mepr_db->transactions}
            WHERE status = %s
              AND txn_type = %s
              {$andmonth}
              {$andday}
              {$andyear}
              {$andproduct}";

    return $wpdb->get_var($wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$payment_str));
  }

  public static function get_widget_data($type='amounts') {
    global $wpdb;
    $mepr_db = new MeprDb();

    $results = array();
    $time = time();

    $selecttype = ($type == 'amounts')?"SUM(amount)":"COUNT(*)";

    $q = "SELECT %s AS date,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$pending_str."') as p,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$failed_str."') as f,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$complete_str."') as c,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = %d
              AND MONTH(created_at) = %d
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$refunded_str."') as r";

    for($i = 6; $i >= 0; $i--) {
      $ts = $time - MeprUtils::days($i);
      $date = gmdate('M j', $ts);
      $year = gmdate('Y', $ts);
      $month = gmdate('n', $ts);
      $day = gmdate('j', $ts);
      $results[$i] = $wpdb->get_row($wpdb->prepare($q, $date, $year, $month, $day, $year, $month, $day, $year, $month, $day, $year, $month, $day));
    }

    return $results;
  }

  public static function get_pie_data($year = false, $month = false) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $andyear = ($year)?" AND YEAR(created_at) = {$year}":"";
    $andmonth = ($month)?" AND MONTH(created_at) = {$month}":"";

    $q = "SELECT p.post_title AS product, COUNT(t.id) AS transactions
            FROM {$mepr_db->transactions} AS t
              LEFT JOIN {$wpdb->posts} AS p
                ON t.product_id = p.ID
            WHERE t.status = %s
              AND txn_type = '".MeprTransaction::$payment_str."'
              {$andyear}
              {$andmonth}
          GROUP BY t.product_id";

    return $wpdb->get_results($wpdb->prepare($q, MeprTransaction::$complete_str));
  }

  public static function get_monthly_data($type, $month, $year, $product, $q=array()) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $results = array();
    $days_in_month = gmdate('t', mktime(0, 0, 0, $month, 1, $year));
    $andproduct = ($product == "all")?"":" AND product_id = {$product}";
    $where = MeprUtils::build_where_clause($q);

    $selecttype = ($type == 'amounts')?"SUM(amount)":"COUNT(*)";

    $q = "SELECT %d AS day,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = {$year}
              AND MONTH(created_at) = {$month}
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$pending_str."'
              {$andproduct}{$where}) as p,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = {$year}
              AND MONTH(created_at) = {$month}
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$failed_str."'
              {$andproduct}{$where}) as f,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = {$year}
              AND MONTH(created_at) = {$month}
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$complete_str."'
              {$andproduct}{$where}) as c,
          (SELECT {$selecttype}
            FROM {$mepr_db->transactions}
            WHERE YEAR(created_at) = {$year}
              AND MONTH(created_at) = {$month}
              AND DAY(created_at) = %d
              AND txn_type = '".MeprTransaction::$payment_str."'
              AND status = '".MeprTransaction::$refunded_str."'
              {$andproduct}{$where}) as r";

    if($type == "amounts") {
      $q .= ",
        (SELECT SUM(tax_amount)
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = {$month}
            AND DAY(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$complete_str."'
            {$andproduct}{$where}) as x,
        (SELECT (SUM(tax_amount)+SUM(amount))
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = {$month}
            AND DAY(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status IN ('".MeprTransaction::$complete_str."', '".MeprTransaction::$refunded_str."')
            {$andproduct}{$where}) as t";
    }

    for($i = 1; $i <= $days_in_month; $i++) {
      if($type == "amounts") {
        $results[$i] = $wpdb->get_row($wpdb->prepare($q, $i, $i, $i, $i, $i, $i, $i));
      }
      else {
        $results[$i] = $wpdb->get_row($wpdb->prepare($q, $i, $i, $i, $i, $i));
      }
    }

    return $results;
  }

  public static function get_yearly_data($type, $year, $product, $q=array()) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $results = array();
    $andproduct = ($product == "all")?"":" AND product_id = {$product}";
    $where = MeprUtils::build_where_clause($q);

    $selecttype = ($type == "amounts")?"SUM(amount)":"COUNT(*)";

    $q = "
      SELECT %d AS month,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$pending_str."'
            {$andproduct}{$where}) as p,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$failed_str."'
            {$andproduct}{$where}) as f,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$complete_str."'
            {$andproduct}{$where}) as c,
        (SELECT {$selecttype}
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$refunded_str."'
            {$andproduct}{$where}) as r";

    if($type == "amounts") {
      $q .= ",
        (SELECT SUM(tax_amount)
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status = '".MeprTransaction::$complete_str."'
            {$andproduct}{$where}) as x,
        (SELECT (SUM(tax_amount)+SUM(amount))
          FROM {$mepr_db->transactions}
          WHERE YEAR(created_at) = {$year}
            AND MONTH(created_at) = %d
            AND txn_type = '".MeprTransaction::$payment_str."'
            AND status IN ('".MeprTransaction::$complete_str."', '".MeprTransaction::$refunded_str."')
            {$andproduct}{$where}) as t";
    }

    for($i = 1; $i <= 12; $i++) {
      if($type == "amounts") {
        $results[$i] = $wpdb->get_row($wpdb->prepare($q, $i, $i, $i, $i, $i, $i, $i));
      }
      else {
        $results[$i] = $wpdb->get_row($wpdb->prepare($q, $i, $i, $i, $i, $i));
      }
    }

    return $results;
  }

  public static function get_first_year() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = "SELECT YEAR(created_at)
            FROM {$mepr_db->transactions}
            WHERE txn_type = '".MeprTransaction::$payment_str."'
              AND created_at IS NOT NULL
              AND created_at <> '".MeprUtils::db_lifetime()."'
          ORDER BY created_at
          LIMIT 1";

    $year = $wpdb->get_var($q);

    if($year)
      return $year;

    return gmdate('Y');
  }

  public static function get_last_year()
  {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = $wpdb->prepare( "SELECT YEAR(created_at) " .
                           "FROM {$mepr_db->transactions} " .
                          "WHERE txn_type = %s " .
                          "ORDER BY created_at DESC " .
                          "LIMIT 1",
                         MeprTransaction::$payment_str );

    $year = $wpdb->get_var($q);

    if($year) { return $year; }

    return gmdate('Y');
  }

  public static function get_total_members_count() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = "SELECT COUNT(u.ID)
                FROM {$wpdb->users} AS u
               WHERE 0 <
                     (SELECT COUNT(tr.user_id)
                        FROM {$mepr_db->transactions} AS tr
                       WHERE tr.user_id=u.ID
                     )";

    return $wpdb->get_var( $query );
  }

  public static function get_total_wp_users_count() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = "SELECT COUNT(ID) FROM {$wpdb->users}";

    return $wpdb->get_var($query);
  }

  public static function get_active_members_count() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = "
      SELECT COUNT(DISTINCT u.ID)
        FROM {$mepr_db->transactions} AS tr
       INNER JOIN {$wpdb->users} AS u
          ON u.ID=tr.user_id
       WHERE (tr.expires_at >= %s OR tr.expires_at IS NULL OR tr.expires_at = %s)
         AND tr.status IN (%s, %s)
    ";

    $query = $wpdb->prepare(
      $query,
      MeprUtils::db_now(),
      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str
    );

    return $wpdb->get_var($query);
  }

  public static function get_inactive_members_count() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = "
      SELECT COUNT(u.ID)
        FROM {$wpdb->users} AS u
        WHERE u.ID NOT IN
          (SELECT tr.user_id
            FROM {$mepr_db->transactions} AS tr
            WHERE (tr.expires_at >= %s OR tr.expires_at IS NULL OR tr.expires_at = %s)
              AND tr.status IN (%s, %s)
          )
          AND 0 <
            (SELECT COUNT(tr2.user_id)
              FROM {$mepr_db->transactions} AS tr2
              WHERE tr2.user_id=u.ID
            )
    ";

    $query = $wpdb->prepare(
      $query,
      MeprUtils::db_now(),
      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str
    );

    return $wpdb->get_var($query);
  }

  public static function get_free_active_members_count() {
    return self::get_free_or_paid_active_members_count();
  }

  public static function get_paid_active_members_count() {
    return self::get_free_or_paid_active_members_count(true);
  }

  private static function get_free_or_paid_active_members_count($paid=false) {
    global $wpdb;

    $sum_operator = $paid ? '>' : '<=';

    $mepr_db = new MeprDb();

    $query = "
      SELECT COUNT(*) AS famc
        FROM ( SELECT t.user_id AS user_id,
                      (SUM(t.amount)+SUM(t.tax_amount)) AS lv
                 FROM {$mepr_db->transactions} AS t
                WHERE t.status IN (%s,%s)
                  AND ( t.expires_at = %s OR t.expires_at >= %s )
                GROUP BY t.user_id ) as lvsums
       WHERE lvsums.lv {$sum_operator} 0
    ";

    $query = $wpdb->prepare(
      $query,
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprUtils::db_lifetime(),
      MeprUtils::db_now()
    );

    return $wpdb->get_var( $query );
  }

  public static function get_average_lifetime_value() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $query = "SELECT AVG(lv) AS alv " .
               "FROM ( SELECT SUM(t.amount) AS lv " .
                        "FROM {$mepr_db->transactions} AS t " .
                       "WHERE t.txn_type = %s " .
                         "AND t.status = %s " .
                       "GROUP BY t.user_id ) as lvsums";

    // Gotta check for confirmed too ... we want all "members" included in the calculation
    $query = $wpdb->prepare( $query,
                             MeprTransaction::$payment_str,
                             MeprTransaction::$complete_str );

    return $wpdb->get_var( $query );
  }

  public static function get_average_payments_per_member() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = "
      SELECT AVG(p.num) AS appm
        FROM ( SELECT COUNT(*) AS num
                 FROM {$mepr_db->transactions} AS t
                WHERE t.status=%s
                  AND t.txn_type=%s
                GROUP BY t.user_id ) as p
    ";

    $q = $wpdb->prepare($q, MeprTransaction::$complete_str, MeprTransaction::$payment_str);

    return $wpdb->get_var($q);
  }

  public static function get_percentage_members_who_rebill() {
    global $wpdb;
    $mepr_db = new MeprDb();

    //$q = "
    //  SELECT (
    //    SELECT COUNT(p.num) AS up
    //      FROM ( SELECT COUNT(*) AS num
    //               FROM {$mepr_db->transactions} AS t
    //              WHERE t.status=%s
    //                AND ( SELECT tr.id
    //                        FROM {$mepr_db->transactions} AS tr
    //                       WHERE tr.status=%s
    //                         AND tr.user_id=t.user_id
    //                         AND tr.expires_at <> '0000-00-00 00:00:00'
    //                         AND tr.expires_at < %s
    //                       ORDER BY tr.id ASC
    //                       LIMIT 1 ) IS NOT NULL
    //              GROUP BY t.user_id ) as p
    //     WHERE p.num > 1
    //  ) / (
    //    SELECT COUNT(p.num) AS up
    //      FROM ( SELECT COUNT(*) AS num
    //               FROM {$mepr_db->transactions} AS t
    //              WHERE t.status=%s
    //                AND ( SELECT tr.id
    //                        FROM {$mepr_db->transactions} AS tr
    //                       WHERE tr.status=%s
    //                         AND tr.user_id=t.user_id
    //                         AND tr.expires_at <> '0000-00-00 00:00:00'
    //                         AND tr.expires_at < %s
    //                       ORDER BY tr.id ASC
    //                       LIMIT 1 ) IS NOT NULL
    //              GROUP BY t.user_id ) as p
    //  ) * 100
    //";

    //$q = $wpdb->prepare($q,
    //                    MeprTransaction::$complete_str,
    //                    MeprTransaction::$complete_str,
    //                    $now,
    //                    MeprTransaction::$complete_str,
    //                    MeprTransaction::$complete_str,
    //                    $now);

    $q = "
      SELECT COUNT(*) AS num
        FROM {$mepr_db->transactions} AS t
       WHERE t.status = %s
         AND t.txn_type = %s
         AND ( SELECT tr.id
                 FROM {$mepr_db->transactions} AS tr
                WHERE tr.status=%s
                  AND tr.txn_type=%s
                  AND tr.user_id=t.user_id
                  AND tr.expires_at <> '0000-00-00 00:00:00'
                  AND tr.expires_at < %s
                ORDER BY tr.id ASC
                LIMIT 1 ) IS NOT NULL
       GROUP BY t.user_id
    ";

    $q = $wpdb->prepare($q,
                        MeprTransaction::$complete_str,
                        MeprTransaction::$payment_str,
                        MeprTransaction::$complete_str,
                        MeprTransaction::$payment_str,
                        gmdate('Y-m-d H:i:s'));

    $res = $wpdb->get_col($q);

    if(empty($res)) { return 0; }

    $gt_two = 0;
    foreach($res as $num) {
      if($num > 1) {
        $gt_two++;
      }
    }

    return (($gt_two / count($res)) * 100);
  }

  //Wrapper function
  public static function make_table_date($month, $day, $year, $format = 'm/d/Y')
  {
    $ts = mktime(0, 0, 1, $month, $day, $year);
    return MeprUtils::get_date_from_ts($ts, $format);
  }

  public static function subscription_stats($created_since=false) {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT SUM(IF(status=%s,1,0)) AS pending,
               SUM(IF(status=%s,1,0)) AS enabled,
               SUM(IF(status=%s,1,0)) AS suspended,
               SUM(IF(status=%s,1,0)) AS cancelled,
               TRUNCATE(AVG(IF(status=%s,total,0)),2) AS pending_average_total,
               TRUNCATE(AVG(IF(status=%s,total,0)),2) AS enabled_average_total,
               TRUNCATE(AVG(IF(status=%s,total,0)),2) AS suspended_average_total,
               TRUNCATE(AVG(IF(status=%s,total,0)),2) AS cancelled_average_total,
               TRUNCATE(SUM(IF(status=%s,total,0)),2) AS pending_sum_total,
               TRUNCATE(SUM(IF(status=%s,total,0)),2) AS enabled_sum_total,
               TRUNCATE(SUM(IF(status=%s,total,0)),2) AS suspended_sum_total,
               TRUNCATE(SUM(IF(status=%s,total,0)),2) AS cancelled_sum_total
          FROM {$mepr_db->subscriptions}
      ",
      MeprSubscription::$pending_str,
      MeprSubscription::$active_str,
      MeprSubscription::$suspended_str,
      MeprSubscription::$cancelled_str,
      MeprSubscription::$pending_str,
      MeprSubscription::$active_str,
      MeprSubscription::$suspended_str,
      MeprSubscription::$cancelled_str,
      MeprSubscription::$pending_str,
      MeprSubscription::$active_str,
      MeprSubscription::$suspended_str,
      MeprSubscription::$cancelled_str
    );

    if(!empty($created_since)) {
      $q .= $wpdb->prepare("WHERE created_at >= %s",$created_since);
    }

    return $wpdb->get_row($q);
  }

  public static function transaction_stats($created_since=false) {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT SUM(IF(txn_type=%s AND status=%s,1,0)) AS pending,
               SUM(IF(txn_type=%s AND status=%s,1,0)) AS failed,
               SUM(IF(txn_type=%s AND status=%s,1,0)) AS complete,
               SUM(IF(txn_type=%s AND status=%s,1,0)) AS refunded,
               TRUNCATE(AVG(IF(txn_type=%s AND status=%s,total,0)),2) AS pending_average_total,
               TRUNCATE(AVG(IF(txn_type=%s AND status=%s,total,0)),2) AS failed_average_total,
               TRUNCATE(AVG(IF(txn_type=%s AND status=%s,total,0)),2) AS complete_average_total,
               TRUNCATE(AVG(IF(txn_type=%s AND status=%s,total,0)),2) AS refunded_average_total,
               TRUNCATE(SUM(IF(txn_type=%s AND status=%s,total,0)),2) AS pending_sum_total,
               TRUNCATE(SUM(IF(txn_type=%s AND status=%s,total,0)),2) AS failed_sum_total,
               TRUNCATE(SUM(IF(txn_type=%s AND status=%s,total,0)),2) AS complete_sum_total,
               TRUNCATE(SUM(IF(txn_type=%s AND status=%s,total,0)),2) AS refunded_sum_total
          FROM {$mepr_db->transactions}
      ",
      MeprTransaction::$payment_str, MeprTransaction::$pending_str,
      MeprTransaction::$payment_str, MeprTransaction::$failed_str,
      MeprTransaction::$payment_str, MeprTransaction::$complete_str,
      MeprTransaction::$payment_str, MeprTransaction::$refunded_str,
      MeprTransaction::$payment_str, MeprTransaction::$pending_str,
      MeprTransaction::$payment_str, MeprTransaction::$failed_str,
      MeprTransaction::$payment_str, MeprTransaction::$complete_str,
      MeprTransaction::$payment_str, MeprTransaction::$refunded_str,
      MeprTransaction::$payment_str, MeprTransaction::$pending_str,
      MeprTransaction::$payment_str, MeprTransaction::$failed_str,
      MeprTransaction::$payment_str, MeprTransaction::$complete_str,
      MeprTransaction::$payment_str, MeprTransaction::$refunded_str
    );

    if(!empty($created_since)) {
      $q .= $wpdb->prepare("WHERE created_at >= %s",$created_since);
    }

    return $wpdb->get_row($q);
  }
} //End class
