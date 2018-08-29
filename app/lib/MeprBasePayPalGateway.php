<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBasePayPalGateway extends MeprBaseRealGateway {
  public function validate_ipn() {
    // Set the command that is used to validate the message
    $_POST['cmd'] = "_notify-validate";

    // We need to send the message back to PayPal just as we received it
    $params = array(
      'method'      => 'POST',
      'body'        => $_POST,
      'headers'     => array('connection' => 'close'),
      'httpversion' => 1.1,
      'sslverify'   => true,
      'user-agent'  => 'MemberPress/'.MEPR_VERSION,
      'timeout'     => 30
    );

    $this->email_status("POST ARRAY SENDING TO PAYPAL\n" . MeprUtils::object_to_string($params, true) . "\n", $this->settings->debug);

    if(!function_exists('wp_remote_post')) {
      require_once(ABSPATH . WPINC . '/http.php');
    }

    $resp = wp_remote_post( $this->settings->url, $params );

    // Put the $_POST data back to how it was so we can pass it to the action
    unset($_POST['cmd']);

    // If the response was valid, check to see if the request was valid
    if(!is_wp_error($resp) &&
        $resp['response']['code'] >= 200 &&
        $resp['response']['code'] < 300 &&
        (strcmp( $resp['body'], "VERIFIED") == 0)) {
      return true;
    }

    $this->email_status("IPN Verification Just failed:\nIPN:\n" .
                        MeprUtils::object_to_string($_POST, true) .
                        "PayPal Response:\n" .
                        MeprUtils::object_to_string($resp),
                        $this->settings->debug);
    return false;
  }

  //Special method for formatting zero decimal currencies for PayPal (only used in PayPal Standard currently)
  public function format_currency($amount) {
    if(MeprUtils::is_zero_decimal_currency()) {
      $amount = MeprAppHelper::format_currency($amount, false, false);
      return str_replace(array(',','.'), array('',''), $amount); //Strip out all formatting
    }

    return MeprUtils::format_float($amount);
  }
}
