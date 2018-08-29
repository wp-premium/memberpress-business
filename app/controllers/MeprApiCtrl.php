<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprApiCtrl extends MeprBaseCtrl
{
  public function load_hooks() {
    add_action( 'wp_ajax_nopriv_mepr_user', array($this, 'user') );
    add_action( 'wp_ajax_mepr_user', array($this, 'user') );
  }

  /** GET user information */
  public function user() {
    $user = $this->user_auth();
    $mepr_user = new MeprUser($user->ID);

    $txns = $mepr_user->active_product_subscriptions('transactions');

    $filename = "{$mepr_user->user_login}-" . gmdate('Ymd');

    $struct = array();
    $struct['user'] = (array)$mepr_user->rec;
    $struct['transactions'] = array();
    $struct['timestamp'] = gmdate('Y-m-d H:i:s');

    foreach($txns as $txn) {
      $txn_struct = (array)$txn->rec;
      unset($txn_struct['response']); // exclude for security
      unset($txn_struct['gateway']); // exclude for security
      unset($txn_struct['user_id']); // redundant
      $struct['transactions'][] = $txn_struct;
    }

    if(!isset($_REQUEST['fmt']) or $_REQUEST['fmt']=='json')
      $this->render_json($struct,$filename);
    else if($_REQUEST['fmt']=='xml')
      $this->render_xml($struct,$filename);
    else if($_REQUEST['fmt']=='csv') {
      $csv_struct = array();

      if(empty($txns)) {
        $csv_struct[] = array_keys($struct['user']);
        $csv_struct[] = array_values($struct['user']);
      }

      $txn_headers = array_keys( $struct['transactions'][0] );

      foreach($txn_headers as $i => $val)
        $txn_headers[$i] = "transaction_{$val}";

      $csv_struct[] = array_merge( array_keys( $struct['user'] ),
                                   $txn_headers,
                                   array( 'timestamp' ) );

      foreach($struct['transactions'] as $txn)
        $csv_struct[] = array_merge( array_values( $struct['user'] ),
                                     array_values( $txn ),
                                     array( $struct['timestamp'] ) );

      $this->render_csv($csv_struct,$filename);
    }
  }

  protected function user_auth() {
    if(!isset($_SERVER['PHP_AUTH_USER']))
      $this->unauthorized(__('No credentials have been provided.', 'memberpress'));
    else {
      $user = wp_authenticate($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

      if(is_wp_error($user))
        $this->unauthorized( $user->get_error_message() );

      return $user;
    }
  }

  protected function unauthorized($message) {
    header('WWW-Authenticate: Basic realm="' . get_option('blogname') . '"');
    header('HTTP/1.0 401 Unauthorized');
    die(sprintf(__('UNAUTHORIZED: %s', 'memberpress'),$message));
  }

  protected function render_json($struct,$filename='') {
    header('Content-Type: text/json');

    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.json\"");

    die(json_encode($struct));
  }

  protected function render_xml($struct,$filename='') {
    header('Content-Type: text/xml');

    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");

    die($this->to_xml($struct));
  }

  protected function render_csv($struct,$filename='') {
    if(!$this->is_debug()) {
      header('Content-Type: text/csv');

      if(!empty($filename))
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    }

    header('Content-Type: text/plain');

    die($this->to_csv($struct));
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
  protected function to_xml($data, $root_node_name='memberpressData', $xml=null, $parent_node_name='') {
    // turn off compatibility mode as simple xml throws a wobbly if you don't.
    //DEPRECATED IN PHP 5.3
    // if(ini_get('zend.ze1_compatibility_mode') == 1)
      // ini_set('zend.ze1_compatibility_mode', 0);

    if(is_null($xml))
      $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><{$root_node_name} />");

    // loop through the data passed in.
    foreach( $data as $key => $value ) {
      // no numeric keys in our xml please!
      if( is_numeric( $key ) ) {
        if( empty( $parent_node_name ) )
          $key = "unknownNode_". (string)$key; // make string key...
        else
          $key = preg_replace( '/s$/', '', $parent_node_name ); // We assume that there's an 's' at the end of the string?
      }

      // replace anything not alpha numeric
      //$key = preg_replace('/[^a-z]/i', '', $key);
      $key = $this->camelize( $key );

      // if there is another array found recrusively call this function
      if(is_array($value)) {
        $node = $xml->addChild($key);
        // recrusive call.
        $this->to_xml($value, $root_node_name, $node, $key);
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
  * Formats a line (passed as a fields array) as CSV and returns the CSV as a string.
  * Adapted from http://us3.php.net/manual/en/function.fputcsv.php#87120
  */
  public function to_csv( $struct,
                          $delimiter = ',',
                          $enclosure = '"',
                          $enclose_all = false,
                          $null_to_mysql_null = false ) {
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $csv = '';
    $line_num = 0;
    foreach( $struct as $line ) {
      $output = array();

      foreach( $line as $field ) {
        if( is_null($field) and $null_to_mysql_null ) {
          $output[] = 'NULL';
          continue;
        }

        // Enclose fields containing $delimiter, $enclosure or whitespace
        if( $enclose_all or preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) )
          $output[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
        else
          $output[] = $field;
      }

      $csv .= implode( $delimiter, $output ) . "\n";
      $line_num++;
    }

    return $csv;
  }

  protected function camelize($str) {
    // Level the playing field
    $str = strtolower($str);
    // Replace dashes and/or underscores with spaces to prepare for ucwords
    $str = preg_replace('/[-_]/', ' ', $str);
    // Ucwords bro ... uppercase the first letter of every word
    $str = ucwords($str);
    // Now get rid of the spaces
    $str = preg_replace('/ /', '', $str);
    // Lowercase the first character of the string
    $str{0} = strtolower($str{0});

    return $str;
  }

  protected function is_debug() {
    return (isset($_REQUEST['debug']) and (int)$_REQUEST['debug']=1);
  }
}

