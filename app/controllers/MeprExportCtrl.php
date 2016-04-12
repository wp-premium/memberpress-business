<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprExportCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('init', 'MeprExportCtrl::export_users_csv');
    add_action('init', 'MeprExportCtrl::export_inactive_users_csv');
  }

  // This will need a major overhaul eventually, but we needed a quick fix for some clients
  // So this is the quick fix. It can only be accessed via
  // /wp-admin/admin.php?page=memberpress-options&mepr-export-users-csv currently.
  // If we change this, we definitely need to let the folks at theballstonjournal.com know
  public static function export_users_csv() {
    global $wpdb;
    $mepr_db = new MeprDb();

    if(!MeprUtils::is_mepr_admin()) { //Make sure we're an admin
      return;
    }

    if(isset($_GET['page']) && $_GET['page'] == 'memberpress-options' && isset($_GET['mepr-export-users-csv'])) {
      $q = "SELECT u.ID AS user_ID, u.user_login AS username, u.user_email AS email, f.meta_value AS first_name, l.meta_value AS last_name, a1.meta_value AS address1, a2.meta_value AS address2, c.meta_value AS city, s.meta_value AS state, z.meta_value AS zip, u.user_registered AS start_date, t.expires_at AS end_date, p.post_title AS membership, t.gateway, cp.post_title AS coupon
              FROM {$wpdb->users} AS u
                LEFT JOIN {$wpdb->usermeta} AS f
                  ON u.ID = f.user_id AND f.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} AS l
                  ON u.ID = l.user_id AND l.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} AS a1
                  ON u.ID = a1.user_id AND a1.meta_key = 'mepr-address-one'
                LEFT JOIN {$wpdb->usermeta} AS a2
                  ON u.ID = a2.user_id AND a2.meta_key = 'mepr-address-two'
                LEFT JOIN {$wpdb->usermeta} AS c
                  ON u.ID = c.user_id AND c.meta_key = 'mepr-address-city'
                LEFT JOIN {$wpdb->usermeta} AS s
                  ON u.ID = s.user_id AND s.meta_key = 'mepr-address-state'
                LEFT JOIN {$wpdb->usermeta} AS z
                  ON u.ID = z.user_id AND z.meta_key = 'mepr-address-zip'
                INNER JOIN {$mepr_db->transactions} AS t
                  ON u.ID = t.user_id AND (t.status = 'complete' OR t.status = 'confirmed') AND (t.expires_at IS NULL OR t.expires_at = 0 OR t.expires_at >= '".date('c')."')
                LEFT JOIN {$wpdb->posts} AS p
                  ON t.product_id = p.ID
                LEFT JOIN {$wpdb->posts} AS cp
                  ON t.coupon_id = cp.ID";

      // output headers so that the file is downloaded rather than displayed
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=active_customers.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      // output the column headings
      fputcsv($output, array( 'User_ID',
                              'Username',
                              'Email',
                              'First Name',
                              'Last Name',
                              'Address 1',
                              'Address 2',
                              'City',
                              'State',
                              'Zip',
                              'Start Date',
                              'End Date',
                              'Subscription',
                              'Gateway',
                              'Coupon' ));

      // fetch the data
      $wpdb->query("SET SQL_BIG_SELECTS=1");
      $rows = $wpdb->get_results($q, ARRAY_A);

      // loop over the rows, outputting them
      foreach($rows as $row) {
        fputcsv($output, $row);
      }

      // close the file and exit
      fclose($output);
      exit;
    }
  }

  // This will need a major overhaul eventually, but we needed a quick fix for some clients
  // So this is the quick fix. It can only be accessed via
  // /wp-admin/admin.php?page=memberpress-options&mepr-export-inactive-users-csv currently.
  // If we change this, we definitely need to let the folks at theballstonjournal.com know
  public static function export_inactive_users_csv() {
    global $wpdb;
    $mepr_db = new MeprDb();

    if(!MeprUtils::is_mepr_admin()) { //Make sure we're an admin
      return;
    }

    if(isset($_GET['page']) && $_GET['page'] == 'memberpress-options' && isset($_GET['mepr-export-inactive-users-csv'])) {
      $mysql_now = $wpdb->prepare('%s',MeprUtils::mysql_now());
      $mysql_lifetime = $wpdb->prepare('%s',MeprUtils::mysql_lifetime());
      $q = "SELECT u.ID AS user_ID, u.user_login AS username, u.user_email AS email, f.meta_value AS first_name, l.meta_value AS last_name, a1.meta_value AS address1, a2.meta_value AS address2, c.meta_value AS city, s.meta_value AS state, z.meta_value AS zip, u.user_registered AS start_date, t.expires_at AS end_date, p.post_title AS membership, t.gateway, cp.post_title AS coupon
              FROM {$wpdb->users} AS u
                LEFT JOIN {$wpdb->usermeta} AS f
                  ON u.ID = f.user_id AND f.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} AS l
                  ON u.ID = l.user_id AND l.meta_key = 'last_name'
                LEFT JOIN {$wpdb->usermeta} AS a1
                  ON u.ID = a1.user_id AND a1.meta_key = 'mepr-address-one'
                LEFT JOIN {$wpdb->usermeta} AS a2
                  ON u.ID = a2.user_id AND a2.meta_key = 'mepr-address-two'
                LEFT JOIN {$wpdb->usermeta} AS c
                  ON u.ID = c.user_id AND c.meta_key = 'mepr-address-city'
                LEFT JOIN {$wpdb->usermeta} AS s
                  ON u.ID = s.user_id AND s.meta_key = 'mepr-address-state'
                LEFT JOIN {$wpdb->usermeta} AS z
                  ON u.ID = z.user_id AND z.meta_key = 'mepr-address-zip'
                INNER JOIN (SELECT *
                              FROM {$mepr_db->transactions}
                              WHERE user_id NOT IN (SELECT user_id
                                                      FROM {$mepr_db->transactions}
                                                      WHERE (expires_at >= {$mysql_now}
                                                             OR expires_at IS NULL
                                                             OR expires_at = {$mysql_lifetime})
                                                        AND status IN ('complete', 'confirmed')
                                                    GROUP BY user_id)
                            GROUP BY user_id) AS t ON u.ID = t.user_id
                LEFT JOIN {$wpdb->posts} AS p
                  ON p.ID = t.product_id
                LEFT JOIN {$wpdb->posts} AS cp
                  ON t.coupon_id = cp.ID
            GROUP BY u.ID";

      // output headers so that the file is downloaded rather than displayed
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=inactive_customers.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      // output the column headings
      fputcsv($output, array( 'User_ID',
                              'Username',
                              'Email',
                              'First Name',
                              'Last Name',
                              'Address 1',
                              'Address 2',
                              'City',
                              'State',
                              'Zip',
                              'Start Date',
                              'End Date',
                              'Subscription',
                              'Gateway',
                              'Coupon' ) );

      // fetch the data
      $wpdb->query("SET SQL_BIG_SELECTS=1");
      $rows = $wpdb->get_results($q, ARRAY_A);

      // loop over the rows, outputting them
      foreach($rows as $row)
        fputcsv($output, $row);

      // close the file and exit
      fclose($output);
      exit;
    }
  }
} //End class
