<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUtils {
  //Maybe this should be in MeprUser?
  public static function get_user_id_by_email($email) {
    $user = self::get_user_by('email', $email);
    if(is_object($user))
      return $user->ID;

    return '';
  }

  public static function is_image($filename) {
    if(!file_exists($filename))
      return false;

    $file_meta = @getimagesize($filename); //@ suppress errors if $filename is not an image

    $image_mimes = array("image/gif", "image/jpeg", "image/png");

    return in_array($file_meta['mime'], $image_mimes);
  }

  /** Looks up month names
    * @parameter $abbreviations=false If true then will return month name abbreviations
    * @parameter $index If false then will return the full array of month names
    * @parameter $one_based_index If true then will grab the index of the months array as if the index were one based (meaning January = 1
    * @return mixed -- an array if $index=false and a string if $index=0-12
    */
  public static function month_names($abbreviations = true, $index = false, $one_based_index = false) {
    if($abbreviations) {
      $months = array( __('Jan', 'memberpress'), __('Feb', 'memberpress'), __('Mar', 'memberpress'), __('Apr', 'memberpress'), __('May', 'memberpress'), __('Jun', 'memberpress'), __('Jul', 'memberpress'), __('Aug', 'memberpress'), __('Sept', 'memberpress'), __('Oct', 'memberpress'), __('Nov', 'memberpress'), __('Dec', 'memberpress') );
    }
    else {
      $months = array( __('January', 'memberpress'), __('February', 'memberpress'), __('March', 'memberpress'), __('April', 'memberpress'), __('May', 'memberpress'), __('June', 'memberpress'), __('July', 'memberpress'), __('August', 'memberpress'), __('September', 'memberpress'), __('October', 'memberpress'), __('November', 'memberpress'), __('December', 'memberpress') );
    }

    if($index === false) {
      return $months; // No index then return the full array
    }

    $index = $one_based_index ? $index - 1 : $index;

    return $months[$index];
  }

  /** Convert days to weeks, months or years ... or leave as days.
    * Eventually we may want to make this more accurate but for now
    * it's just a quick way to more nicely format the trial days.
    */
  public static function period_type_from_days($days) {
    // maybe Convert to years
    // For now we don't care about leap year
    if( $days % 365 === 0 ) {
      return array( 'years', (int)round($days/365) );
    }
    // not as exact as we'd like but as close as it's gonna get for now
    else if( $days % 30 === 0 ) {
      return array( 'months', (int)round($days/30) );
    }
    // Of course this is exact ... easy peasy
    else if( $days % 7 === 0 ) {
      return array( 'weeks', (int)round($days/7) );
    }
    else {
      return array( 'days', $days );
    }
  }

  public static function period_type_name($period_type, $count=1) {
    $count = (int)$count;

    switch($period_type) {
      case 'hours':  return _n( 'Hour',  'Hours',  $count, 'memberpress' );
      case 'days':   return _n( 'Day',   'Days',   $count, 'memberpress' );
      case 'weeks':  return _n( 'Week',  'Weeks',  $count, 'memberpress' );
      case 'months': return _n( 'Month', 'Months', $count, 'memberpress' );
      case 'years':  return _n( 'Year',  'Years',  $count, 'memberpress' );
      default: return $period_type;
    }
  }

  public static function rewriting_on() {
    $permalink_structure = get_option('permalink_structure');
    return ($permalink_structure and !empty($permalink_structure));
  }

  public static function is_logged_in_and_current_user($user_id) {
    $current_user = self::get_currentuserinfo();

    return (self::is_user_logged_in() and (is_object($current_user) && $current_user->ID == $user_id));
  }

  public static function is_logged_in_and_an_admin() {
    return (self::is_user_logged_in() and self::is_mepr_admin());
  }

  public static function is_logged_in_and_a_subscriber() {
    return (self::is_user_logged_in() and self::is_subscriber());
  }

  public static function get_mepr_admin_capability() {
    return MeprHooks::apply_filters('mepr-admin-capability', 'remove_users');
  }

  public static function is_mepr_admin($user_id=null) {
    $mepr_cap = self::get_mepr_admin_capability();

    if(empty($user_id)) {
      return self::current_user_can($mepr_cap);
    }
    else {
      return user_can($user_id, $mepr_cap);
    }
  }

  public static function is_subscriber() {
    return (current_user_can('subscriber'));
  }

  public static function current_user_can($role) {
    self::include_pluggables('wp_get_current_user');
    return current_user_can($role);
  }

  public static function minutes($n = 1) {
    return $n * 60;
  }

  public static function hours($n = 1) {
    return $n * self::minutes(60);
  }

  public static function days($n = 1) {
    return $n * self::hours(24);
  }

  public static function weeks($n = 1) {
    return $n * self::days(7);
  }

  public static function months($n, $month_ts=false, $backwards=false, $ts_day=false) {
    $month_ts = empty($month_ts) ? time() : $month_ts;

    // Get the day for the ts_day
    $ts_day = empty($ts_day) ? date('j', $month_ts) : $ts_day;

    // Get the month number for the timestamp
    $ts_month = (int)date('n', $month_ts);

    // ts_month +/- number of months and then mod by 12 to get the right target month
    if($backwards) {
      $month = ($ts_month - $n) % 12;
    }
    else {
      $month = ($ts_month + $n) % 12;
    }

    // Get the year for the timestamp
    $ts_year = (int)date('Y', $month_ts);

    // ts_month +/- number of months and then divide by 12 to get the right target month
    // round down no matter what then either +/- by the ts_year to get the right target year
    if($backwards) {
      $year = $ts_year - abs(floor(($ts_month - $n) / 12));
    }
    else {
      $year = $ts_year + abs(floor(($ts_month + $n) / 12));
    }

    $days_in_month = date('t', mktime(0,0,0,$month,1,$year));

    $day = ($ts_day > $days_in_month) ? $days_in_month : $ts_day;

    $new_month_ts = mktime(
      date('H',$month_ts),
      date('i',$month_ts),
      date('s',$month_ts),
      $month,
      $day,
      $year
    );

    return abs($new_month_ts - $month_ts);
  }

  public static function years($n, $year_timestamp=false, $backwards=false) {
    $year_timestamp = empty($year_timestamp) ? time() : $year_timestamp;
    $seconds = 0;

    // If backward we start in the previous year
    if($backwards) {
      $year_timestamp -= self::days((int)date('t', $year_timestamp));
    }

    for($i=0; $i < $n; $i++) {
      $seconds += $year_seconds = self::days(365 + (int)date('L', $year_timestamp));
      // We want the years going into the past
      if($backwards) {
        $year_timestamp -= $year_seconds;
      }
      else { // We want the years going into the past
        $year_timestamp += $year_seconds;
      }
    }

    return $seconds;
  }

  // convert timestamp into approximate minutes
  public static function tsminutes($ts) {
    return (int)($ts / 60);
  }

  // convert timestamp into approximate hours
  public static function tshours($ts) {
    return (int)(self::tsminutes($ts) / 60);
  }

  // convert timestamp into approximate days
  public static function tsdays($ts) {
    return (int)(self::tshours($ts) / 24);
  }

  // convert timestamp into approximate weeks
  public static function tsweeks($ts) {
    return (int)(self::tsdays($ts) / 7);
  }

  //Coupons rely on this be careful changing it
  public static function make_ts_date($month, $day, $year) {
    return mktime(23, 59, 59, $month, $day, $year);
  }

  //Coupons rely on this be careful changing it
  public static function get_date_from_ts($ts, $format = 'M d, Y') {
    if($ts > 0) {
      return gmdate($format, $ts);
    }
    else {
      return gmdate($format, time());
    }
  }

  public static function db_date_to_ts($mysql_date) {
    return strtotime($mysql_date);
  }

  public static function ts_to_mysql_date($ts, $format='Y-m-d H:i:s') {
    return gmdate($format, $ts);
  }

  public static function db_now($format='Y-m-d H:i:s') {
    return self::ts_to_mysql_date(time(),$format);
  }

  public static function db_lifetime() {
    return '0000-00-00 00:00:00';
  }

  /*** Deprecated mysql* functions ***/
  public static function mysql_date_to_ts($mysql_date) {
    return self::db_date_to_ts($mysql_date);
  }

  public static function mysql_now($format='Y-m-d H:i:s') {
    return self::db_now($format);
  }

  public static function mysql_lifetime() {
    return self::db_lifetime();
  }

  public static function array_to_string($my_array, $debug = false, $level = 0) {
    return self::object_to_string($my_array);
  }

  public static function object_to_string($object) {
    ob_start();
    print_r($object);

    return ob_get_clean();
  }

  //Inserts into an associative array
  public static function a_array_insert($array, $values, $offset) {
    return array_slice($array, 0, $offset, true) + $values + array_slice($array, $offset, NULL, true);
  }

  // Drop in replacement for evil eval
  public static function replace_vals($content, $params, $start_token="\\{\\$", $end_token="\\}") {
    if(!is_array($params)) { return $content; }

    $callback = function($k) use($start_token, $end_token) {
      $k = preg_quote($k, "/");
      return "/{$start_token}" . "[^\W_]*{$k}[^\W_]*" . "{$end_token}/";
    };
    $patterns = array_map( $callback, array_keys($params) );
    $replacements = array_values( $params );

    //Make sure all replacements can be converted to a string yo
    foreach($replacements as $i => $val) {
      //Numbers and strings and objects with __toString are fine as is
      if(is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
        continue;
      }

      //Datetime's
      if($val instanceof DateTime && isset($val->date)) {
        $replacements[$i] = $val->date;
        continue;
      }

      //If we made it here ???
      $replacements[$i] = '';
    }

    return preg_replace( $patterns, $replacements, $content );
  }

  public static function format_tax_percent_for_display($number) {
    $number = self::format_float($number, 3) + 0; //Number with period as decimal point - adding 0 will truncate insignificant 0's at the end

    //How many decimal places are left?
    $num_remain_dec = strlen(substr(strrchr($number, "."), 1));

    return number_format_i18n($number, $num_remain_dec);
  }

  public static function format_float($number, $num_decimals = 2) {
    return number_format($number, $num_decimals, '.', '');
  }

  public static function format_currency_float($number, $num_decimals = 2) {
    if(function_exists('number_format_i18n')) {
      return number_format_i18n($number, $num_decimals); //The wp way
    }

    return self::format_float($number, $num_decimals);
  }

  public static function is_zero_decimal_currency() {
    $mepr_options = MeprOptions::fetch();
    $zero_decimals = array('BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF', 'HUF');

    return in_array($mepr_options->currency_code, $zero_decimals, false);
  }

  public static function get_pages() {
    global $wpdb;

    $query = "SELECT * FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s";
    $query = $wpdb->prepare($query, "publish", "page");
    $results = $wpdb->get_results($query);

    if($results) {
      return $results;
    }
    else {
      return array();
    }
  }

  public static function is_product_page() {
    $current_post = self::get_current_post();

    return is_object($current_post) and $current_post->post_type == 'memberpressproduct';
  }

  public static function protocol() {
    if( is_ssl() ||
        ( defined('MEPR_SECURE_PROXY') && //USER must define this in wp-config.php if they're doing HTTPS between the proxy
          isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
          strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ) ) {
      return 'https';
    }
    else {
      return 'http';
    }
  }

  // Less problemmatic replacement for WordPress' is_ssl() function
  public static function is_ssl() {
    return (self::protocol() === 'https');
  }

  public static function get_property($className, $property) {
    if(!class_exists($className)) { return null; }
    if(!property_exists($className, $property)) { return null; }

    $vars = get_class_vars($className);

    return $vars[$property];
  }

  public static function random_string($length = 10, $lowercase = true, $uppercase = false, $symbols = false)
  {
    $characters = '0123456789';
    $characters .= $uppercase?'ABCDEFGHIJKLMNOPQRSTUVWXYZ':'';
    $characters .= $lowercase?'abcdefghijklmnopqrstuvwxyz':'';
    $characters .= $symbols?'@#*^%$&!':'';
    $string = '';
    $max_index = strlen($characters) - 1;

    for($p = 0; $p < $length; $p++) {
      $string .= $characters[mt_rand(0, $max_index)];
    }

    return $string;
  }

  public static function sanitize_string($string) {
    //Converts "Hey there buddy-boy!" to "hey_there_buddy_boy"
    return str_replace('-', '_', sanitize_title($string));
  }

  public static function flush_rewrite_rules() {
    // Load our controllers
    $controllers = @glob( MEPR_CTRLS_PATH . '/Mepr*Ctrl.php', GLOB_NOSORT );

    foreach( $controllers as $controller ) {
      $class = preg_replace('#\.php#', '', basename($controller));

      if(preg_match('#Mepr.*Ctrl#', $class)) {
        $obj = new $class;

        // Only act on MeprCptCtrls
        if($obj instanceof MeprCptCtrl) {
          $obj->register_post_type();
        }
      }
    }

    flush_rewrite_rules();
  }

  // Format a protected version of a cc num from the last 4 digits
  public static function cc_num($last4 = '****') {
    // If a full cc num happens to get here then it gets reduced to the last4 here
    $last4 = substr($last4, -4);

    return "**** **** **** {$last4}";
  }

  public static function calculate_proration_by_subs($old_sub, $new_sub, $reset_period=false) {
    // find expiring_txn txn on old_sub
    $expiring_txn = $old_sub->expiring_txn();

    // If no money has changed hands then no proration
    if( $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
        $expiring_txn->txn_type == MeprTransaction::$subscription_confirmation_str ) {
      return (object)array('proration' => 0.00, 'days' => 0);
    }

    // If the subscription has a trial and we're in that first trial payment ...
    // and it's not a proration then we need the trial amount rather than the sub price
    if( $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
        $old_sub->txn_count == 1 && $old_sub->trial && !$old_sub->prorated_trial ) {
      $old_price = $expiring_txn->amount;
    }
    else {
      $old_price = $old_sub->price;
    }

    $res = self::calculate_proration( $old_price,
                                      $new_sub->price,
                                      $old_sub->days_in_this_period(),
                                      $new_sub->days_in_this_period(true),
                                      $old_sub->days_till_expiration(),
                                      $reset_period,
                                      $old_sub,
                                      $new_sub );

    return $res;
  }

  public static function calculate_proration( $old_amount,
                                              $new_amount,
                                              $old_period    ='lifetime',
                                              $new_period    ='lifetime',
                                              $old_days_left ='lifetime',
                                              $reset_period  = false,
                                              $old_sub       = false, /*These will be false on non auto-recurring*/
                                              $new_sub       = false  /*These will be false on non auto-recurring*/ ) {
    // By default days left in the new sub are equal to the days left in the old
    $new_days_left = $old_days_left;

    if(is_numeric($old_period) && is_numeric($new_period) && $new_sub !== false && $old_amount > 0) {
      // recurring to recurring
      if($old_days_left > $new_period || $reset_period) {
        // What if the days left exceed the $new_period?
        // And the new outstanding amount is greater?
        // Days left should be reset to the new period
        $new_days_left = $new_period;
      }

      $old_per_day_amount = $old_amount / $old_period;
      $new_per_day_amount = $new_amount / $new_period;

      $old_outstanding_amount = $old_per_day_amount * $old_days_left;
      $new_outstanding_amount = $new_per_day_amount * $new_days_left;

      $proration = $new_outstanding_amount - $old_outstanding_amount;

      $days = $new_days_left;
      if($proration < 0) {
        // Extend days out by finding out how many periods the
        // old outstanding amount will buy to create a free trial
        $days = ($new_amount > 0 ? ((abs($proration)+$new_amount)/$new_amount)*$new_days_left : 0);
        $proration = 0;
      }
    }
    elseif(is_numeric($old_period) && is_numeric($old_days_left) && ($new_period == 'lifetime' || $new_sub === false) && $old_amount > 0) {
      // recurring to lifetime
      // apply outstanding amount to lifetime purchase
      // calculate amount of money left on old sub
      $old_outstanding_amount = (($old_amount / $old_period) * $old_days_left);

      $proration = max($new_amount - $old_outstanding_amount, 0.00);
      $days = 0; // we just do this thing
    }
    elseif($old_period == 'lifetime' && is_numeric($new_period) && $old_amount > 0) {
      // lifetime to recurring{
      $proration = max($new_amount - $old_amount, 0.00);
      $days = $new_period; //(is_numeric($old_days_left) && !$reset_period)?$old_days_left:$new_period;
    }
    elseif($old_period == 'lifetime' && $new_period == 'lifetime' && $old_amount > 0) {
      // lifetime to lifetime
      $proration = max(($new_amount - $old_amount), 0.00);
      $days = 0; // We be lifetime brah
    }
    else {
      // Default
      $proration = 0;
      $days = 0;
    }

    // Don't allow amounts that are less than a dollar but greater than zero
    $proration = (($proration > 0.00 && $proration < 1.00) ? 1.00 : $proration);
    $days = ceil($days);
    $proration = self::format_float($proration);

    //Make sure we don't do more than 1 year on days
    if($days > 365) { $days = 365; }

    $prorations = (object)compact('proration', 'days');

    return MeprHooks::apply_filters('mepr-proration', $prorations, $old_amount, $new_amount, $old_period, $new_period, $old_days_left, $old_sub, $new_sub, $reset_period);
  }

  public static function is_associative_array($arr) {
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

  public static function get_post_meta_with_default($post_id, $meta_key, $single = false, $default = null) {
    $pms = get_post_custom($post_id);
    $var = get_post_meta($post_id, $meta_key, $single);

    if(($single and $var=='') or (!$single and $var==array())) {
      // Since false bools are stored as empty string ('') we need
      // to see if the meta_key is actually stored in the db and
      // it's a bool value before we blindly return default
      if(isset($pms[$meta_key]) and is_bool($default)) {
        return false;
      }
      else {
        return $default;
      }
    }
    else {
      return $var;
    }
  }

  public static function get_post_meta_values($meta_key) {
    global $wpdb;

    $query = $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key=%s", $meta_key );
    $metas = $wpdb->get_results( $query );

    for($i=0; $i<count($metas); $i++) {
      $metas[$i]->meta_value = maybe_unserialize($metas[$i]->meta_value);
    }

    return $metas;
  }

  public static function convert_to_plain_text($text) {
    $text = preg_replace('~<style[^>]*>[^<]*</style>~','',$text);
    $text = strip_tags($text);
    $text = trim($text);
    $text = preg_replace("~\r~",'',$text); // Make sure we're only dealint with \n's here
    $text = preg_replace("~\n\n+~","\n\n",$text); // reduce 1 or more blank lines to 1

    return $text;
  }

  public static function array_splice_assoc(&$input, $offset, $length, $replacement) {
    $replacement = (array) $replacement;
    $key_indices = array_flip(array_keys($input));

    if( isset($input[$offset]) && is_string($offset) ) {
      $offset = $key_indices[$offset];
    }
    if( isset($input[$length]) && is_string($length) ) {
      $length = $key_indices[$length] - $offset;
    }

    $input = array_slice($input, 0, $offset, true)
            + $replacement
            + array_slice($input, $offset + $length, null, true);
  }

  public static function post_uri($post_id) {
    return preg_replace('!'.preg_quote(home_url(),'!').'!','',get_permalink($post_id));
  }

  public static function get_sub_type($sub) {
    if( $sub instanceof MeprSubscription ) {
      return 'recurring';
    }
    elseif( $sub instanceof MeprTransaction ) {
      return 'single';
    }

    return false;
  }

  //Get the current post, and account for non-singular views
  public static function get_current_post() {
    global $post;

    if(in_the_loop()) {
      return get_post(get_the_ID());
    }
    else {
      return (isset($post) && $post instanceof WP_Post)?$post:false;
    }
  }

  public static function render_json($struct,$filename='',$is_debug=false) {
    header('Content-Type: text/json');

    if(!$is_debug and !empty($filename)) {
      header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
    }

    die(json_encode($struct));
  }

  protected function render_xml($struct,$filename='',$is_debug=false) {
    header('Content-Type: text/xml');

    if(!$is_debug and !empty($filename)) {
      header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");
    }

    die(self::to_xml($struct));
  }

  public static function render_csv($struct,$filename='',$is_debug=false) {
    if(!$is_debug) {
      header('Content-Type: text/csv');

      if(!empty($filename)) {
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
      }
    }

    header('Content-Type: text/plain');

    die(self::to_csv($struct));
  }

  public static function render_unauthorized($message) {
    header('WWW-Authenticate: Basic realm="' . get_option('blogname') . '"');
    header('HTTP/1.0 401 Unauthorized');
    die(sprintf(__('UNAUTHORIZED: %s', 'memberpress'),$message));
  }

  /**
   * The main function for converting to an XML document.
   * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
   *
   * @param array $data
   * @param string $root_node_name - what you want the root node to be - defaultsto data.
   * @param SimpleXMLElement $xml - should only be used recursively
   * @return string XML
   */
  public static function to_xml($data, $root_node_name='memberpressData', $xml=null, $parent_node_name='') {
    // turn off compatibility mode as simple xml throws a wobbly if you don't.
    //Deprecated as of PHP 5.3
    // if(ini_get('zend.ze1_compatibility_mode') == 1) {
      // ini_set('zend.ze1_compatibility_mode', 0);
    // }

    if(is_null($xml)) {
      $xml = simplexml_load_string('<?xml version=\'1.0\' encoding=\'utf-8\'?'.'><'.$root_node_name.' />');
    }

    // loop through the data passed in.
    foreach($data as $key => $value) {
      // no numeric keys in our xml please!
      if(is_numeric($key)) {
        if(empty($parent_node_name)) {
          $key = "unknownNode_". (string)$key; // make string key...
        }
        else {
          $key = preg_replace( '/s$/', '', $parent_node_name ); // We assume that there's an 's' at the end of the string?
        }
      }

      // replace anything not alpha numeric
      //$key = preg_replace('/[^a-z]/i', '', $key);
      $key = self::camelcase( $key );

      // if there is another array found recrusively call this function
      if(is_array($value)) {
        $node = $xml->addChild($key);
        // recrusive call.
        self::to_xml($value, $root_node_name, $node, $key);
      }
      else {
        // add single node.
        $value = htmlentities($value);
        $xml->addChild($key,$value);
      }
    }

    // pass back as string. or simple xml object if you want!
    return $xml->asXML();
  }

  /**
  * Formats an associative array as CSV and returns the CSV as a string.
  * Can handle nested arrays, headers are named by associative array keys.
  * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
  */
  public static function to_csv( $struct,
                                 $delimiter = ',',
                                 $enclosure = '"',
                                 $enclose_all = false,
                                 $telescope = '.',
                                 $null_to_mysql_null = false ) {
    $struct = self::deep_convert_to_associative_array($struct);

    if(self::is_associative_array($struct)) {
      $struct = array($struct);
    }

    $csv = '';
    $headers = array();
    $lines = array();

    foreach( $struct as $row ) {
      $last_path=''; // tracking for the header
      $lines[] = self::process_csv_row(
                   $row, $headers, $last_path, '', $delimiter,
                   $enclosure, $enclose_all,
                   $telescope, $null_to_mysql_null );
    }

    // Always enclose headers
    $csv .= $enclosure . implode( $enclosure.$delimiter.$enclosure, array_keys($headers) ) . $enclosure . "\n";

    foreach( $lines as $line ) {
      $csv_line = array_merge($headers, $line);
      $csv .= implode( $delimiter, array_values($csv_line) ) . "\n";
    }

    return $csv;
  }

  /** Expects an associative array for a row of this data structure. Should
    * handle nested arrays by telescoping header values with the $telescope arg.
    */
  private static function process_csv_row( $row, &$headers, &$last_path, $path='',
                                           $delimiter = ',',
                                           $enclosure = '"',
                                           $enclose_all = false,
                                           $telescope = '.',
                                           $null_to_mysql_null=false ) {

    $output = array();

    foreach($row as $label => $field) {
      $new_path = (empty($path) ? $label : $path.$telescope.$label);

      if(is_null($field) and $null_to_mysql_null) {
        $headers = self::header_insert( $headers, $new_path, $last_path );
        $last_path = $new_path;
        $output[$new_path] = 'NULL';

        continue;
      }

      $field = MeprHooks::apply_filters('mepr_process_csv_cell', $field, $label);

      if(is_array($field)) {
        $output += self::process_csv_row($field, $headers, $last_path, $new_path, $delimiter, $enclosure, $enclose_all, $telescope, $null_to_mysql_null);
      }
      else {
        $delimiter_esc = preg_quote($delimiter, '/');
        $enclosure_esc = preg_quote($enclosure, '/');
        $headers = self::header_insert( $headers, $new_path, $last_path );
        $last_path = $new_path;

        // Enclose fields containing $delimiter, $enclosure or whitespace
        if($enclose_all or preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field)) {
          $output[$new_path] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
        }
        else {
          $output[$new_path] = $field;
        }
      }
    }

    return $output;
  }

  private static function header_insert($headers, $new_path, $last_path) {
    if(!isset($headers[$new_path])) {
      $headers = self::array_insert($headers, $last_path, array($new_path => ''));
    }

    return $headers;
  }

  public static function array_insert($array, $index, $insert) {
    $pos    = array_search($index, array_keys($array));
    $pos    = empty($pos) ? 0 : (int)$pos;
    $before = array_slice($array, 0, $pos + 1);
    $after  = array_slice($array, $pos);
    $array  = $before + $insert + $after;

    return $array;
  }

  /* Convert a snake-case string to camel case. The 'lower' parameter
   * will allow you to choose 'lower' camelCase or 'upper' CamelCase.
   */
  public static function camelcase($str, $type='lower') {
    // Level the playing field
    $str = strtolower($str);
    // Replace dashes and/or underscores with spaces to prepare for ucwords
    $str = preg_replace('/[-_]/', ' ', $str);
    // Ucwords bro ... uppercase the first letter of every word
    $str = ucwords($str);
    // Now get rid of the spaces
    $str = preg_replace('/ /', '', $str);

    if($type=='lower') {
      // Lowercase the first character of the string
      $str{0} = strtolower($str{0});
    }

    return $str;
  }

  public static function lower_camelcase($str) {
    return self::camelcase($str, 'lower');
  }

  public static function upper_camelcase($str) {
    return self::camelcase($str, 'upper');
  }

  public static function snakecase($str, $delim='_') {
    // Search for '_-' then just lowercase and ensure correct delim
    if( preg_match( '/[-_]/', $str ) ) {
      $str = preg_replace( '/[-_]/', $delim, $str );
    }
    else { // assume camel case
      $str = preg_replace('/([A-Z])/', $delim.'$1', $str);
      $str = preg_replace('/^'.preg_quote($delim).'/', '', $str);
    }

    return strtolower($str);
  }

  public static function kebabcase($str) {
    return self::snakecase($str, '-');
  }

  public static function humancase($str, $delim = ' ') {
    $str = self::snakecase($str, $delim);
    return ucwords($str);
  }

  public static function unsanitize_title($str) {
    if(!is_string($str)) { return __('Unknown', 'memberpress'); }

    $str = str_replace(array('-', '_'), array(' ', ' '), $str);
    return ucwords($str);
  }

  // Deep convert to associative array using JSON
  // TODO: Find some cleaner way to do a deep convert to an assoc array
  public static function deep_convert_to_associative_array($struct) {
    return json_decode(json_encode($struct),true);
  }

  public static function hex_encode($str, $delim = "%") {
    $encoded = bin2hex($str);
    $encoded = chunk_split($encoded, 2, $delim);
    $encoded = $delim . substr($encoded, 0, strlen($encoded) - strlen($delim));

    return $encoded;
  }

  public static function user_meta_exists($user_id, $meta_key) {
    global $wpdb;

    $q = "SELECT COUNT(*)
            FROM {$wpdb->usermeta} AS um
           WHERE um.user_id=%d
             AND um.meta_key=%s";
    $q = $wpdb->prepare( $q, $user_id, $meta_key );
    $count = $wpdb->get_var( $q );

    return ($count > 0);
  }

  /** Parses a CSV file and returns an associative array */
  public static function parse_csv_file($filepath, $validations=array(), $mappings=array()) {
    $assoc = $headers = array();
    $col_count = 0;
    $row = 1;

    if (($handle = fopen($filepath, 'r')) !== false) {
      while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        if($row === 1) {
          foreach($data as $i => $header) {
            if(!empty($header)) {
              if(isset($mappings[$header])) {
                $headers[$i] = $mappings[$header];
              }
              else {
                $headers[$i] = $header;
              }
            }
            foreach($validations as $col => $v) {
              if(in_array('required',$v) && !in_array($col,$headers)) {
                throw new Exception(sprintf(__('Your CSV file must contain the column: %s', 'memberpress')));
              }
            }
          }
          $col_count = count($headers);
        }
        else {
          if(!self::csv_row_is_blank($data)) {
            $new_row = array();
            for($i=0;$i < $col_count;$i++) {
              $new_row[$headers[$i]] = $data[$i];
            }
            foreach($validations as $col => $v) {
              if(in_array('required',$v) && !in_array($col,$headers)) {
                throw new Exception(sprintf(__('Your CSV file must contain the column: %s', 'memberpress')));
              }
            }
            $assoc[] = $new_row;
          }
        }
        $row++;
      }
      fclose($handle);
    }

    return $assoc;
  }

  private static function csv_row_is_blank( $row ) {
    foreach( $row as $i => $cell ) {
      if( !empty($cell) ) { return false; }
    }

    return true;
  }

  public static function countries($prioritize_my_country=true) {
    $countries = require(MEPR_I18N_PATH . '/countries.php');

    if($prioritize_my_country) {
      $country_code = get_option('mepr_biz_country');

      if(!empty($country_code) && isset($countries[$country_code])) {
        $my_country = array($country_code => $countries[$country_code]);
        unset($countries[$country_code]);
        $countries = array_merge($my_country, $countries);
      }
    }

    return MeprHooks::apply_filters(
      'mepr_countries',
      $countries,
      $prioritize_my_country
    );
  }

  public static function country_name($code) {
    $countries = self::countries(false);
    return (isset($countries[$code]) ? $countries[$code] : $code);
  }

  public static function states() {
    $states = array();
    $sfiles = @glob( MEPR_I18N_PATH . '/states/[A-Z][A-Z].php', GLOB_NOSORT );
    foreach( $sfiles as $sfile ) {
      require( $sfile );
    }

    return $states;
  }

  public static function clean($str) {
    return sanitize_text_field($str);
  }

  /** This is for converting an array that would look something like this into an SQL where clause:

        array(
          array(
            'var' => 'tr.id',
            'val' => '28'
          ),
          array(
            'cond' => 'OR',
            'var'  => 'tr.txn_type',
            'op'   => '<>',
            'val'  => 'payment'
          )
        )

      This is mainly used with params coming in from the URL so we don't get any sql injection happening.
  */
  public static function build_where_clause($q, $where='') {
    global $wpdb;

    if(!empty($q)) {
      foreach($q AS $qk => $qv) {
        if(isset($qv['var']) && isset($qv['val'])) {
          $cond = ' ';
          $cond .= ((isset($qv['cond'])&&preg_match('/(AND|OR)/i',$qv['cond']))?$qv['cond']:'AND');
          $cond .= ' ';
          $cond .= esc_sql($qv['var']);
          $cond .= ((isset($qv['op'])&&preg_match('/(<>|<|>|<=|>=)/i',$qv['op']))?$qv['op']:'=');
          $cond .= is_numeric($qv['val'])?'%d':'%s';
          $where .= $wpdb->prepare($cond, $qv['val']);
        }
      }
    }

    return $where;
  }

  public static function compress_css($buffer) {
    /* remove comments */
    $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

    /* remove tabs, spaces, newlines, etc. */
    $buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);

    return $buffer;
  }

  public static function load_css_url($files=array()) {
    // return admin_url('admin-ajax.php?action=mepr_load_css&f=' . implode(',',$files));
    return MEPR_SCRIPT_URL.'&action=mepr_load_css&f='.implode(',', $files);
  }

  public static function load_price_table_css_url() {
    // return admin_url('admin-ajax.php?action=mepr_load_css&t=price_table');
    return MEPR_SCRIPT_URL.'&action=mepr_load_css&t=price_table';
  }

  public static function locate_by_ip($ip = null, $source = 'geoplugin') {
    $ip = (is_null($ip)?$_SERVER['REMOTE_ADDR']:$ip);
    if(!self::is_ip($ip)) { return false; }

    $lockey = 'mp_locate_by_ip_' . md5($ip.$source);
    $loc = get_transient($lockey);

    if(false === $loc) {
      if($source == 'freegeoip') {
        $url    = "https://freegeoip.net/json/{$ip}";
        $cindex = 'country_code';
        $sindex = 'region_code';
      }
      else { // geoplugin
        $url    = "http://www.geoplugin.net/json.gp?ip={$ip}";
        $cindex = 'geoplugin_countryCode';
        $sindex = 'geoplugin_regionCode';
      }

      $res = wp_remote_get($url);
      $obj = json_decode($res['body']);

      $state = (isset($obj->{$sindex})?$obj->{$sindex}:'');
      $country = (isset($obj->{$cindex})?$obj->{$cindex}:'');

      // If the state is goofy then just blank it out
      if(file_exists(MEPR_I18N_PATH . '/states/' . $country . '.php')) {
        $states = array();
        require(MEPR_I18N_PATH . '/states/' . $country . '.php');
        if(!isset($states[$country][$state])) {
          $state = '';
        }
      }

      $loc = (object)compact('state','country');
      set_transient($lockey, $loc, DAY_IN_SECONDS);
    }

    return $loc;
  }

  public static function is_ip($ip) {
    // return preg_match('#^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$#',$ip);
    return ((bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6));
  }

  public static function country_by_ip($ip = null, $source = 'geoplugin') {
    return (($loc = self::locate_by_ip()) ? $loc->country : '' );
  }

  public static function state_by_ip($ip = null, $source = 'geoplugin') {
    return (($loc = self::locate_by_ip()) ? $loc->state : '' );
  }

  public static function base36_encode($base10) {
    return base_convert($base10, 10, 36);
  }

  public static function base36_decode($base36) {
    return base_convert($base36, 36, 10);
  }

  public static function is_date($str) {
    if(!is_string($str)) { return false; }
    $d = strtotime($str);
    return ($d !== false);
  }

  public static function is_url($str) {
    return preg_match('/https?:\/\/[\w-]+(\.[\w-]{2,})*(:\d{1,5})?/', $str);
  }

  public static function is_email($str) {
    return preg_match('/[\w\d._%+-]+@[\w\d.-]+\.[\w]{2,4}/', $str);
  }

  public static function is_phone($str) {
    return preg_match('/\(?\d{3}\)?[- ]\d{3}-\d{4}/', $str);
  }

  public static function get_delim($link) {
    return ((preg_match("#\?#",$link))?'&':'?');
  }

  public static function http_status_codes() {
    return array(
      100 => 'Continue',
      101 => 'Switching Protocols',
      102 => 'Processing',
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      207 => 'Multi-Status',
      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      306 => 'Switch Proxy',
      307 => 'Temporary Redirect',
      400 => 'Bad Request',
      401 => 'Unauthorized',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Timeout',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Long',
      415 => 'Unsupported Media Type',
      416 => 'Requested Range Not Satisfiable',
      417 => 'Expectation Failed',
      418 => 'I\'m a teapot',
      422 => 'Unprocessable Entity',
      423 => 'Locked',
      424 => 'Failed Dependency',
      425 => 'Unordered Collection',
      426 => 'Upgrade Required',
      449 => 'Retry With',
      450 => 'Blocked by Windows Parental Controls',
      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout',
      505 => 'HTTP Version Not Supported',
      506 => 'Variant Also Negotiates',
      507 => 'Insufficient Storage',
      509 => 'Bandwidth Limit Exceeded',
      510 => 'Not Extended'
    );
  }

  public static function exit_with_status($status,$message='') {
    $codes = self::http_status_codes();
    header("HTTP/1.1 {$status} {$codes[$status]}", true, $status);
    exit($message);
  }

  public static function error_log($error) {
    error_log(sprintf(__('*** MemberPress Error: %s', 'memberpress'),$error));
  }

  public static function debug_log($message) {
    //Getting some complaints about using WP_DEBUG here
    if(defined('WP_MEPR_DEBUG') && WP_MEPR_DEBUG) {
      error_log(sprintf(__('*** MemberPress Debug: %s', 'memberpress'),$message));
    }
  }

  public static function is_wp_error($obj) {
    if(is_wp_error($obj)) {
      self::error_log($obj->get_error_message());
      return true;
    }

    return false;
  }

  /** EMAIL NOTICE METHODS **/
  public static function send_notices($obj, $user_class=null, $admin_class=null, $force=false) {
    if($obj instanceof MeprSubscription) {
      $params = MeprSubscriptionsHelper::get_email_params($obj);
    }
    elseif($obj instanceof MeprTransaction) {
      $params = MeprTransactionsHelper::get_email_params($obj);
    }
    else {
      return false;
    }

    $usr = $obj->user();

    try {
      if( !is_null($user_class) ) {
        $uemail = MeprEmailFactory::fetch($user_class);
        $uemail->to = $usr->formatted_email();

        if($force) {
          $uemail->send($params);
        }
        else {
          $uemail->send_if_enabled($params);
        }
      }

      if( !is_null($admin_class) ) {
        $aemail = MeprEmailFactory::fetch($admin_class);

        if($force) {
          $aemail->send($params);
        }
        else {
          $aemail->send_if_enabled($params);
        }
      }
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }

  public static function send_signup_notices($txn, $force=false, $send_admin_notices=true) {
    $admin_one_off_class = ($send_admin_notices ? 'MeprAdminNewOneOffEmail' : null);
    $admin_class = ($send_admin_notices ? 'MeprAdminSignupEmail' : null);

    $params = MeprTransactionsHelper::get_email_params($txn);
    $usr = $txn->user();
    $prd_email = MeprEmailFactory::fetch(
      'MeprUserProductWelcomeEmail',
      'MeprBaseProductEmail',
      array(
        array(
          'product_id' => $txn->product_id
        )
      )
    );
    $prd_sent = false;

    //Send Product Welcome Email?
    if($prd_email->enabled()) {
      self::send_product_welcome_notices($prd_email, $params, $usr);
      $prd_sent = true;
    }

    //If this is a one-off send that email too
    if(empty($txn->subscription_id)) {
      self::send_notices($txn, null, $admin_one_off_class, $force);
    }

    //Send New Signup Emails?
    if(!$usr->signup_notice_sent) {
      //Don't send the MemberPress Welcome Email if the Product Welcome Email was sent instead
      if($prd_sent) {
        self::send_notices($txn, null, $admin_class, $force);
      }
      else {
        self::send_notices($txn, 'MeprUserWelcomeEmail', $admin_class, $force);
      }

      $usr->signup_notice_sent = true;
      $usr->store();

      //Maybe move this to the bottom of this method outside of an if statement?
      //Not sure if this should happen on each new signup, or only on a member's first signup
      MeprEvent::record('member-signup-completed', $usr, (object)$txn->rec); //have to use ->rec here for some reason
    }
  }

  public static function send_new_sub_notices($sub) {
    self::send_notices( $sub, null, 'MeprAdminNewSubEmail' );
    MeprEvent::record('subscription-created', $sub);
  }

  public static function send_transaction_receipt_notices( $txn ) {
    /** TODO: These events should probably be moved ... but
      * 'tis very convenient to put them here for now. */
    MeprEvent::record('transaction-completed', $txn);

    // This is a recurring payment
    if(($sub = $txn->subscription())) {
      MeprEvent::record('recurring-transaction-completed', $txn);

      if($sub->txn_count > 1) {
        MeprEvent::record('renewal-transaction-completed', $txn);
      }
    }
    elseif(!$sub) {
      MeprEvent::record('non-recurring-transaction-completed', $txn);
    }

    self::send_notices(
      $txn,
      'MeprUserReceiptEmail',
      'MeprAdminReceiptEmail'
    );
  }

  public static function send_suspended_sub_notices($sub) {
    self::send_notices(
      $sub,
      'MeprUserSuspendedSubEmail',
      'MeprAdminSuspendedSubEmail'
    );
    MeprEvent::record('subscription-paused', $sub);
  }

  public static function send_resumed_sub_notices($sub) {
    self::send_notices(
      $sub,
      'MeprUserResumedSubEmail',
      'MeprAdminResumedSubEmail'
    );
    MeprEvent::record('subscription-resumed', $sub);
  }

  public static function send_cancelled_sub_notices($sub) {
    self::send_notices(
      $sub,
      'MeprUserCancelledSubEmail',
      'MeprAdminCancelledSubEmail'
    );
    MeprEvent::record('subscription-stopped', $sub);
  }

  public static function send_upgraded_txn_notices($txn) {
    self::send_upgraded_sub_notices($txn);
  }

  public static function send_upgraded_sub_notices($sub) {
    self::send_notices(
      $sub,
      'MeprUserUpgradedSubEmail',
      'MeprAdminUpgradedSubEmail'
    );
  }

  public static function record_upgraded_sub_events($sub, $event_txn) {
    MeprEvent::record('subscription-upgraded', $sub);
    MeprEvent::record('subscription-changed', $event_txn, $sub->latest_txn_id);

    if($sub instanceof MeprTransaction) {
      MeprEvent::record('subscription-upgraded-to-one-time', $sub);
    }
    else {
      MeprEvent::record('subscription-upgraded-to-recurring', $sub);
    }
  }

  public static function send_downgraded_txn_notices($txn) {
    self::send_downgraded_sub_notices($txn);
  }

  public static function send_downgraded_sub_notices($sub) {
    self::send_notices(
      $sub,
      'MeprUserDowngradedSubEmail',
      'MeprAdminDowngradedSubEmail'
    );
  }

  public static function record_downgraded_sub_events($sub, $event_txn) {
    MeprEvent::record('subscription-downgraded', $sub);
    MeprEvent::record('subscription-changed', $event_txn, $sub->latest_txn_id);

    if($sub instanceof MeprTransaction) {
      MeprEvent::record('subscription-downgraded-to-one-time', $sub);
    }
    else {
      MeprEvent::record('subscription-downgraded-to-recurring', $sub);
    }
  }

  public static function send_refunded_txn_notices($txn) {
    self::send_notices(
      $txn,
      'MeprUserRefundedTxnEmail',
      'MeprAdminRefundedTxnEmail'
    );
    MeprEvent::record('transaction-refunded', $txn);

    // This is a recurring payment
    if(($sub = $txn->subscription()) && $sub->txn_count > 0) {
      MeprEvent::record('recurring-transaction-refunded', $txn);
    }
  }

  public static function send_failed_txn_notices($txn) {
    self::send_notices(
      $txn,
      'MeprUserFailedTxnEmail',
      'MeprAdminFailedTxnEmail'
    );

    MeprEvent::record('transaction-failed', $txn);

    // This is a recurring payment
    if(($sub = $txn->subscription()) && $sub->txn_count > 0) {
      MeprEvent::record('recurring-transaction-failed', $txn);
    }
  }

  public static function send_product_welcome_notices($uemail, $params, $usr) {
    try {
      $uemail->to = $usr->formatted_email();
      $uemail->send_if_enabled($params);
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }

  public static function send_cc_expiration_notices( $txn ) {
    $sub = $txn->subscription();

    if( $sub instanceof MeprSubscription &&
        $sub->cc_expiring_before_next_payment() ) {
      self::send_notices(
        $sub,
        'MeprUserCcExpiringEmail',
        'MeprAdminCcExpiringEmail'
      );
    }
  }

  public static function filter_array_keys($sarray, $keys) {
    $rarray = array();
    foreach($sarray as $key => $value) {
      if(in_array($key, $keys)) {
        $rarray[$key] = $value;
      }
    }
    return $rarray;
  }

  public static function maybe_wpautop($text) {
    $wpautop_disabled = get_option('mepr_wpautop_disable_for_emails');

    if($wpautop_disabled) {
      return $text;
    }

    return wpautop($text);
  }

  public static function match_uri($pattern,$uri,&$matches,$include_query_string=false) {
    if($include_query_string) {
      $uri = urldecode($uri);
    }
    else {
      // Remove query string and decode
      $uri = preg_replace('#(\?.*)?$#','',urldecode($uri));
    }

    // Resolve WP installs in sub-directories
    preg_match('!^https?://[^/]*?(/.*)$!', site_url(), $m);

    $subdir = ( isset($m[1]) ? $m[1] : '' );
    $regex = '!^'.$subdir.$pattern.'$!';
    return preg_match($regex, $uri, $matches);
  }

  /** Verifies that a url parameter exists and optionally that it contains a certain value. */
  public static function valid_url_param($name, $value=null, $method=null) {
    $params = $_REQUEST;
    if(!empty($method)) {
      $method = strtoupper($method);

      if($method=='GET') {
        $params = $_GET;
      }
      else if($method=='POST') {
        $params = $_POST;
      }
    }

    $verified = isset($params[$name]);

    if($verified && !empty($value)) {
      $verified = ($params[$name]==$value);
    }

    return $verified;
  }

  public static function build_query_string( $add_params=array(),
                                             $include_query_string=false,
                                             $exclude_params=array(),
                                             $exclude_referer=true ) {
    $query_string = '';
    if($include_query_string) {
      $query_string = $_SERVER['QUERY_STRING'];
    }

    if(empty($query_string)) {
      $query_string = http_build_query($add_params);
    }
    else {
      $query_string = $query_string . '&' . http_build_query($add_params);
    }

    if($exclude_referer) {
      $exclude_params[] = '_wp_http_referer';
    }

    foreach($exclude_params as $param) {
      $query_string = preg_replace('!&?' . preg_quote($param,'!') . '=[^&]*!', '', $query_string);
    }

    return $query_string;
  }

  // $add_nonce = [$action,$name]
  public static function admin_url( $path,
                                    $add_nonce=array(),
                                    $add_params=array(),
                                    $include_query_string=false,
                                    $exclude_params=array(),
                                    $exclude_referer=true ) {
    $delim = MeprUtils::get_delim($path);

    // Automatically exclude the nonce if it's present
    if(!empty($add_nonce)) {
      $nonce_action = $add_nonce[0];
      $nonce_name = (isset($add_nonce[1]) ? $add_nonce[1] : '_wpnonce');
      $exclude_params[] = $nonce_name;
    }

    $url = admin_url($path.$delim.self::build_query_string($add_params,$include_query_string,$exclude_params,$exclude_referer));

    if(empty($add_nonce)) {
      return $url;
    }
    else {
      return html_entity_decode(wp_nonce_url($url,$nonce_action,$nonce_name));
    }
  }

  public static function pretty_permalinks_using_index() {
    $permalink_structure = get_option('permalink_structure');
    return preg_match('!^/index.php!',$permalink_structure);
  }

  /** This returns the structure for all of the gateway notify urls.
    * It can even account for folks unlucky enough to have to prepend
    * their URLs with '/index.php'.
    * NOTE: This function is only applicable if pretty permalinks are enabled.
    */
  public static function gateway_notify_url_structure() {
    $pre_slug_index = '';
    if(self::pretty_permalinks_using_index()) {
      $pre_slug_index = '/index.php';
    }

    return MeprHooks::apply_filters(
      'mepr_gateway_notify_url_structure',
      "{$pre_slug_index}/mepr/notify/%gatewayid%/%action%"
    );
  }

  /** This modifies the gateway notify url structure to be matched against a uri.
    * By default it will generate this: /mepr/notify/([^/\?]+)/([^/\?]+)/?
    * However, this could change depending on what gateway_notify_url_structure returns
    */
  public static function gateway_notify_url_regex_pattern() {
    return preg_replace('!(%gatewayid%|%action%)!', '([^/\?]+)', self::gateway_notify_url_structure()) . '/?';
  }

/* PLUGGABLE FUNCTIONS AS TO NOT STEP ON OTHER PLUGINS' CODE */
  public static function get_currentuserinfo() {
    self::include_pluggables('wp_get_current_user');
    $current_user = wp_get_current_user();

    if(isset($current_user->ID) && $current_user->ID > 0) {
      return new MeprUser($current_user->ID);
    }
    else {
      return false;
    }
  }

  public static function get_current_user_id() {
    self::include_pluggables('wp_get_current_user');
    return get_current_user_id();
  }

  public static function get_user_by($field = 'login', $value) {
    self::include_pluggables('get_user_by');

    return get_user_by($field, $value);
  }

  // Just sends to the emails configured in MP
  public static function wp_mail_to_admin($subject, $message, $headers = '') {
    $mepr_options = MeprOptions::fetch();
    $recipient = $mepr_options->admin_email_addresses;
    self::wp_mail($recipient, $subject, $message, $headers);
  }

  public static function wp_mail($recipient, $subject, $message, $headers = '') {
    self::include_pluggables('wp_mail');

    // Parse shortcodes in the message body
    $message = do_shortcode($message);

    add_filter('wp_mail_from_name', 'MeprUtils::set_mail_from_name');
    add_filter('wp_mail_from',      'MeprUtils::set_mail_from_email');

    // We just send individual emails
    $recipients = explode(',', $recipient);
    $recipients = MeprHooks::apply_filters('mepr-wp-mail-recipients',  $recipients,  $subject,     $message, $headers);
    $subject    = MeprHooks::apply_filters('mepr-wp-mail-subject',     $subject,     $recipients,  $message, $headers);
    $message    = MeprHooks::apply_filters('mepr-wp-mail-message',     $message,     $recipients,  $subject, $headers);
    $headers    = MeprHooks::apply_filters('mepr-wp-mail-headers',     $headers,     $recipients,  $subject, $message);

    foreach($recipients as $to) {
      $to = trim($to);

      //TEMP FIX TO AVOID ALL THE SENDGRID ISSUES WE'VE BEEN SEEING IN SUPPORT LATELY
      //Let's get rid of the pretty TO's -- causing too many problems
      //mbstring?
      if(extension_loaded('mbstring')) {
        if(mb_strpos($to, '<') !== false) {
          $to = mb_substr($to, (mb_strpos($to, '<') + 1), -1);
        }
      }
      else {
        if(strpos($to, '<') !== false) {
          $to = substr($to, (strpos($to, '<') + 1), -1);
        }
      }

      wp_mail($to, $subject, $message, $headers);

      //Just leaving these here as I need to debug this shiz enough, it would save me some time
      /*
      global $phpmailer;
      var_dump($phpmailer);
      */
    }

    remove_filter('wp_mail_from',      'MeprUtils::set_mail_from_name');
    remove_filter('wp_mail_from_name', 'MeprUtils::set_mail_from_email');
  }

  public static function set_mail_from_name($name) {
    $mepr_options = MeprOptions::fetch();

    return $mepr_options->mail_send_from_name;
  }

  public static function set_mail_from_email($email) {
    $mepr_options = MeprOptions::fetch();

    return $mepr_options->mail_send_from_email;
  }

  public static function is_user_logged_in() {
    self::include_pluggables('is_user_logged_in');

    return is_user_logged_in();
  }

  public static function get_avatar($id, $size) {
    self::include_pluggables('get_avatar');

    return get_avatar($id, $size);
  }

  public static function wp_hash_password($password_str) {
    self::include_pluggables('wp_hash_password');

    return wp_hash_password($password_str);
  }

  public static function wp_generate_password($length, $special_chars) /*dontTest*/
  {
    self::include_pluggables('wp_generate_password');

    return wp_generate_password($length, $special_chars);
  }

  // Special handling for protocol
  public static function get_permalink($id = 0, $leavename = false) {
    $permalink = get_permalink($id, $leavename);

    if(self::is_ssl()) {
      $permalink = preg_replace( '!^https?://!', 'https://', $permalink );
    }

    return $permalink;
  }

  public static function wp_redirect($location, $status = 302) {
    self::include_pluggables('wp_redirect');

    //Don't cache redirects YO!
    header("Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate, proxy-revalidate");
    // header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Fri, 01 Jan 2016 00:00:01 GMT", true); //Some date in the past
    wp_redirect($location, $status);

    exit;
  }

  //Probably shouldn't use this any more to authenticate passwords - see MeprUtils::wp_check_password instead
  public static function wp_authenticate($username, $password) {
    self::include_pluggables('wp_authenticate');
    return wp_authenticate($username,$password);
  }

  public static function wp_check_password($user, $password) {
    self::include_pluggables('wp_check_password');
    return wp_check_password($password, $user->data->user_pass, $user->ID);
  }

  public static function wp_new_user_notification($user_id, $deprecated=null, $notify='') {
    self::include_pluggables('wp_new_user_notification');
    return wp_new_user_notification($user_id, $deprecated, $notify);
  }

  public static function check_ajax_referer($slug,$param) {
    self::include_pluggables('check_ajax_referer');
    return check_ajax_referer($slug,$param);
  }

  public static function include_pluggables($function_name) {
    if(!function_exists($function_name)) {
      require_once(ABSPATH.WPINC.'/pluggable.php');
    }
  }

  public static function login_url() { //These funcs are thin wrappers for WP funcs, no need to test.
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->login_page_id > 0) {
      return $mepr_options->login_page_url();
    }
    else {
      return wp_login_url($mepr_options->account_page_url());
    }
  }

  public static function logout_url() {
    return MeprHooks::apply_filters('mepr-logout-url', wp_logout_url(self::login_url()));
  }

  public static function site_domain() {
    return preg_replace('#^https?://(www\.)?([^\?\/]*)#', '$2', home_url());
  }

  public static function is_curl_enabled() {
    return function_exists('curl_version');
  }

  public static function is_post_request() {
    if(isset($_SERVER['REQUEST_METHOD'])) {
      return (strtolower($_SERVER['REQUEST_METHOD']) == 'post');
    }
    else {
      return (isset($_POST) && !empty($_POST));
    }
  }

  public static function is_get_request() {
    if(isset($_SERVER['REQUEST_METHOD'])) {
      return (strtolower($_SERVER['REQUEST_METHOD']) == 'get');
    }
    else {
      return (!isset($_POST) || empty($_POST));
    }
  }

  /* Pieces together the current url like a champ */
  public static function request_url() {
    $url = (self::is_ssl())?'https://':'http://';

    if($_SERVER['SERVER_PORT'] != '80') {
      $url .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
    }
    else {
      $url .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
    }

    return $url;
  }

  public static function get_formatted_usermeta($user_id) {
    $mepr_options = MeprOptions::fetch();
    $ums = get_user_meta($user_id);
    $new_ums = array();
    $return_ugly_val = MeprHooks::apply_filters('mepr-return-ugly-usermeta-vals', false);

    if(!empty($ums)) {
      foreach($ums as $umkey => $um) {
        // Only support first val for now and yes some of these will be serialized values
        $val = maybe_unserialize($um[0]);
        $strval = $val;

        if(is_array($val)) { //Handle array type custom fields like multi-select, checkboxes etc we'll unsanitize the vals
          if(!empty($val)) {
            foreach($val as $i => $k) {
              if(is_int($i)) { //Multiselects (indexed array)
                if(!$return_ugly_val) { $k = self::unsanitize_title($k); }
                $strval = (is_array($strval))?"{$k}":$strval.", {$k}";
              }
              else { //Checkboxes (associative array)
                if(!$return_ugly_val) { $i = self::unsanitize_title($i); }
                $strval = (is_array($strval))?"{$i}":$strval.", {$i}";
              }
            }
          }
          else { //convert empty array to empty string
            $strval = '';
          }
        }
        elseif($val == 'on') { //Single checkbox
          $strval = _x('Checked', 'ui', 'memberpress');
        }
        elseif($return_ugly_val) { //Return the ugly value
          $strval = $val;
        }
        else { //We need to check for checkboxes and radios and match them up with MP custom fields
          $mepr_field = $mepr_options->get_custom_field($umkey);

          if(!is_null($mepr_field) && !empty($mepr_field->options)) {
            foreach($mepr_field->options as $option) {
              if($option->option_value == $val) {
                $strval = stripslashes($option->option_name);
                break; //Found a match, so stop here
              }
            }
          }
        }

        $new_ums["{$umkey}"] = $strval;
      }
    }

    return $new_ums;
  }

  // purely for backwards compatibility (deprecated)
  public static function send_admin_signup_notification($params) {
    $txn = MeprTransaction::get_one_by_trans_num($params['trans_num']);
    $txn = new MeprTransaction($txn->id);
    $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these
    $usr = $txn->user();

    try {
      $aemail = MeprEmailFactory::fetch('MeprAdminSignupEmail');
      $aemail->send($params);
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }

  public static function send_user_signup_notification($params) {
    $txn = MeprTransaction::get_one_by_trans_num($params['trans_num']);
    $txn = new MeprTransaction($txn->id);
    $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these
    $usr = $txn->user();

    try {
      $uemail = MeprEmailFactory::fetch('MeprUserWelcomeEmail');
      $uemail->to = $usr->formatted_email();
      $uemail->send($params);
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }

  public static function send_user_receipt_notification($params) {
    $txn = MeprTransaction::get_one_by_trans_num($params['trans_num']);
    $txn = new MeprTransaction($txn->id);
    $params = MeprTransactionsHelper::get_email_params($txn); // Yeah, re-set these
    $usr = $txn->user();

    try {
      $uemail = MeprEmailFactory::fetch('MeprUserReceiptEmail');
      $uemail->to = $usr->formatted_email();
      $uemail->send($params);

      $aemail = MeprEmailFactory::fetch('MeprAdminReceiptEmail');
      $aemail->send($params);
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }
} // End class
