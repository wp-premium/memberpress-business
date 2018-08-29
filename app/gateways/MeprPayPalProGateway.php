<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprPayPalProGateway extends MeprBasePayPalGateway {
  // This is stored with the user meta & the subscription meta
  public static $paypal_token_str = '_mepr_paypal_pro_token';
  public static $has_spc_form = true;

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = __('PayPal Payments Pro', 'memberpress');
    $this->set_defaults();

    $this->capabilities = array(
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'suspend-subscriptions',
      'resume-subscriptions',
      'subscription-trial-payment'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'ipn'    => 'listener',
      'cancel' => 'cancel_handler',
      'return' => 'return_handler'
    );

    $this->message_pages = array( 'cancel' => 'cancel_message' );
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  protected function set_defaults() {
    if(!isset($this->settings)) {
      $this->settings = array();
    }

    $this->settings = (object)array_merge(
      array(
        'gateway' => 'MeprPayPalProGateway',
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'icon' => MEPR_IMAGES_URL . '/checkout/cards.png',
        'use_icon' => true,
        'desc' => __('Pay with your credit card via PayPal', 'memberpress'),
        'use_desc' => true,
        'api_username' => '',
        'api_password' => '',
        'signature' => '',
        'sandbox' => false,
        'force_ssl' => false,
        'debug' => false
      ),
      (array)$this->settings
    );

    $this->id = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->icon = $this->settings->icon;
    $this->use_icon = $this->settings->use_icon;
    $this->desc = $this->settings->desc;
    $this->use_desc = $this->settings->use_desc;

    if($this->is_test_mode()) {
      $this->settings->url     = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
      $this->settings->api_url = 'https://api-3t.sandbox.paypal.com/nvp';
    }
    else {
      $this->settings->url = 'https://www.paypal.com/cgi-bin/webscr';
      $this->settings->api_url = 'https://api-3t.paypal.com/nvp';
    }

    $this->settings->api_version = 69;

    // An attempt to correct people who paste in spaces along with their credentials
    $this->settings->api_username = trim($this->settings->api_username);
    $this->settings->api_password = trim($this->settings->api_password);
    $this->settings->signature    = trim($this->settings->signature);
  }

  /** Listens for an incoming connection from PayPal and then handles the request appropriately. */
  public function listener() {
    $_POST = wp_unslash($_POST);
    $this->email_status("PayPal IPN Recieved\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->settings->debug);

    if($this->validate_ipn()) { return $this->process_ipn(); }

    return false;
  }

  private function process_ipn() {
    if(isset($_POST['txn_type']) &&
        (strtolower($_POST['txn_type'])=='recurring_payment' ||
         strtolower($_POST['txn_type'])=='subscr_payment')) {
      $this->record_subscription_payment();
    }
    else if((isset($_POST['txn_type']) &&
             in_array(strtolower($_POST['txn_type']),
                      array('recurring_payment_skipped',
                            'subscr_failed'))) ||
             (isset($_POST['payment_status']) &&
              in_array(strtolower($_POST['payment_status']),
                         array('denied','expired','failed')))) {
      $this->record_payment_failure();
    }
    else if( isset($_POST['txn_type']) &&
             strtolower($_POST['txn_type'])=='recurring_payment_profile_cancel' ) {
      $this->record_cancel_subscription();
    }
    else if( isset($_POST['txn_type']) &&
             strtolower($_POST['txn_type'])=='recurring_payment_suspended' ) {
      $this->record_suspend_subscription();
    }
    else if(isset($_POST['parent_txn_id']) && !isset($_POST['txn_type'])) {
      if(in_array(strtolower($_POST['payment_status']),array('refunded','reversed','voided'))) {
        return $this->record_refund();
      }
    }
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    if(!isset($_POST['recurring_payment_id']) && !isset($_POST['subscr_id'])) {
      return;
    }

    if(isset($_POST['subscr_id']) && !empty($_POST['subscr_id'])) {
      $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
    }
    else {
      $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);
    }

    if($sub) {
      $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();
      $first_txn = $sub->first_txn();

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $first_txn = new MeprTransaction();
        $first_txn->user_id = $sub->user_id;
        $first_txn->product_id = $sub->product_id;
        $first_txn->coupon_id = $sub->coupon_id;
      }

      //Prevent recording duplicates
      $existing_txn = MeprTransaction::get_one_by_trans_num($_POST['txn_id']);
      if( isset($existing_txn->id) &&
          $existing_txn->id > 0 &&
          in_array($existing_txn->status, array(MeprTransaction::$complete_str, MeprTransaction::$confirmed_str)) ) {
        return;
      }

      //If this is a trial payment, let's just convert the confirmation txn into a payment txn
      //then we won't have to mess with setting expires_at as it was already handled
      if($this->is_subscr_trial_payment($sub)) {
        $txn = $first_txn; //For use below in send notices
        $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
        $txn->gateway    = $this->id;
        $txn->set_gross($_POST['mc_gross']);
        $txn->trans_num  = $_POST['txn_id'];
        $txn->txn_type   = MeprTransaction::$payment_str;
        $txn->status     = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->store();
      }
      else {
        $txn = new MeprTransaction();
        $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
        $txn->user_id    = $first_txn->user_id;
        $txn->product_id = $first_txn->product_id;
        $txn->coupon_id  = $first_txn->coupon_id;
        $txn->gateway    = $this->id;
        $txn->set_gross($_POST['mc_gross']);
        $txn->trans_num  = $_POST['txn_id'];
        $txn->txn_type   = MeprTransaction::$payment_str;
        $txn->status     = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->store();

        //Check that the subscription status is still enabled
        if($sub->status != MeprSubscription::$active_str) {
          $sub->status = MeprSubscription::$active_str;
          $sub->store();
        }

        // Not waiting for an IPN here bro ... just making it happen even though
        // the total occurrences is already capped in record_create_subscription()
        $sub->limit_payment_cycles();
      }

      $this->email_status("Subscription Transaction\n" .
                          MeprUtils::object_to_string($txn->rec, true),
                          $this->settings->debug);

      MeprUtils::send_transaction_receipt_notices( $txn );

      return $txn;
    }

    return false;
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    if(isset($_POST['ipn_track_id']) && $txn_res = MeprTransaction::get_one_by_trans_num($_POST['ipn_track_id']) && isset($txn_res->id)) {
      return false; //We've already recorded this failure duh - don't send more emails
    }
    elseif(isset($_POST['txn_id']) && $txn_res = MeprTransaction::get_one_by_trans_num($_POST['txn_id']) && isset($txn_res->id)) {
      $txn = new MeprTransaction($txn_res->id);
      $txn->status = MeprTransaction::$failed_str;
      $txn->store();
    }
    elseif((isset($_POST['recurring_payment_id']) &&
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']) ) ||
           (isset($_POST['subscr_id']) &&
            $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']))) {
      $first_txn = $sub->first_txn();

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $coupon_id = $sub->coupon_id;
      }
      else {
        $coupon_id = $first_txn->coupon_id;
      }

      $txn = new MeprTransaction();
      $txn->set_gross(isset($_POST['mc_gross'])?$_POST['mc_gross']:(isset($_POST['amount'])?$_POST['amount']:0.00));
      $txn->user_id = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->coupon_id = $coupon_id;
      $txn->txn_type = MeprTransaction::$payment_str;
      $txn->status = MeprTransaction::$failed_str;
      $txn->subscription_id = $sub->id;
      // if ipn_track_id isn't set then just use uniqid
      $txn->trans_num = ( isset($_POST['ipn_track_id']) ? $_POST['ipn_track_id'] : uniqid() );
      $txn->gateway = $this->id;
      $txn->store();

      $sub->expire_txns(); //Expire associated transactions for the old subscription
      $sub->store();
    }
    else {
      return false; // Nothing we can do here ... so we outta here
    }

    MeprUtils::send_failed_txn_notices($txn);

    return $txn;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($txn) {
    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else {
      throw new MeprGatewayException(__('Payment was unsuccessful, please check your payment details and try again.', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();

    $prd = $txn->product();
    $sub = $txn->subscription();
    $usr = $txn->user();

    $args = MeprHooks::apply_filters('mepr_paypal_pro_payment_args', array(
      // Pass payment amount and action
      'AMT' => MeprUtils::format_float($txn->total),
      'CURRENCYCODE' => $mepr_options->currency_code,
      'PAYMENTACTION' => 'Sale',
      'CREDITCARDTYPE' => $_REQUEST['mepr-cc-type'],
      'INVNUM' => $txn->id,

      // Pass Credit Card Info
      'ACCT' => $_REQUEST['mepr_cc_num'],
      'EXPDATE' => sprintf('%02d',$_REQUEST['mepr_cc_exp_month']).sprintf('%04d',$_REQUEST['mepr_cc_exp_year']),
      'CVV2' => $_REQUEST['mepr_cvv_code'],

      // Pass User Info
      'EMAIL' => $usr->user_email,
      'FIRSTNAME' => $_REQUEST['mepr_first_name'],
      'LASTNAME' => $_REQUEST['mepr_last_name'],
      'STREET' => $_REQUEST['mepr-address-one'].' '.$_REQUEST['mepr-address-two'],
      'CITY' => $_REQUEST['mepr-address-city'],
      'STATE' => $_REQUEST['mepr-address-state'],
      'ZIP' => $_REQUEST['mepr-address-zip'],
      'COUNTRYCODE' => $_REQUEST['mepr-address-country'],
      'IPADDRESS' => $_SERVER['REMOTE_ADDR'],
      'BUTTONSOURCE' => 'Caseproof_SP'
    ), $txn);

    if(empty($usr->first_name) || empty($usr->last_name)) {
      $usr->first_name = sanitize_text_field(wp_unslash($_REQUEST['mepr_first_name']));
      $usr->last_name = sanitize_text_field(wp_unslash($_REQUEST['mepr_last_name']));
      update_user_meta($usr->ID, 'first_name', $user->first_name);
      update_user_meta($usr->ID, 'last_name', $user->last_name);
    }

    if(!$usr->address_is_set()) { $usr->set_address($_REQUEST); }

    //$this->email_status("DoDirectPayment Request:\n".MeprUtils::object_to_string($args,true)."\n", $this->settings->debug);
    $res = $this->send_nvp_request('DoDirectPayment', $args);
    //$this->email_status("DoDirectPayment Response:\n".MeprUtils::object_to_string($res,true)."\n", $this->settings->debug);

    if(!isset($res['ACK']) || strtoupper($res['ACK']) != 'SUCCESS') {
      throw new MeprGatewayException(sprintf(__('The payment was unsuccessful. %s', 'memberpress'),$this->error_str($res)));
    }

    $_REQUEST['paypal_response'] = $res;
    $_REQUEST['transaction'] = $txn;

    return $this->record_payment();
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment() {
    if(!isset($_REQUEST['paypal_response']) || !isset($_REQUEST['transaction'])) { return false; }

    $res = $_REQUEST['paypal_response'];
    $txn = $_REQUEST['transaction'];

    if($txn->status==MeprTransaction::$complete_str) { return false; }

    if(strtolower($res['ACK'])=='success') {
      $txn->trans_num  = $res['TRANSACTIONID'];
      $txn->txn_type   = MeprTransaction::$payment_str;
      $txn->status     = MeprTransaction::$complete_str;

      // This will only work before maybe_cancel_old_sub is run
      $upgrade = $txn->is_upgrade();
      $downgrade = $txn->is_downgrade();

      $event_txn = $txn->maybe_cancel_old_sub();
      $txn->store();

      $this->email_status("Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

      $prd = $txn->product();

      if( $prd->period_type=='lifetime' ) {
        if( $upgrade ) {
          $this->upgraded_sub($txn, $event_txn);
        }
        else if( $downgrade ) {
          $this->downgraded_sub($txn, $event_txn);
        }
        else {
          $this->new_sub($txn);
        }

        MeprUtils::send_signup_notices( $txn );
      }

      MeprUtils::send_transaction_receipt_notices( $txn );

      return $txn;
    }

    return false;
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    $mepr_options = MeprOptions::fetch();

    $args = MeprHooks::apply_filters('mepr_paypal_pro_refund_args', array(
      'TRANSACTIONID' => $txn->trans_num,
      'REFUNDTYPE' => 'Full',
      'CURRENCYCODE' => $mepr_options->currency_code
    ), $txn);

    $this->email_status("RefundTransaction Request:\n".MeprUtils::object_to_string($args,true)."\n", $this->settings->debug);

    $res = $this->send_nvp_request('RefundTransaction', $args);

    $this->email_status("RefundTransaction Response:\n".MeprUtils::object_to_string($res,true)."\n", $this->settings->debug);

    if(!isset($res['ACK']) || strtoupper($res['ACK']) != 'SUCCESS') {
      throw new MeprGatewayException(sprintf(__('The refund was unsuccessful. %s', 'memberpress'),$this->error_str($res)));
    }

    $_POST['parent_txn_id'] = $txn->id;
    return $this->record_refund();
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() {
    $obj = MeprTransaction::get_one_by_trans_num($_POST['parent_txn_id']);

    if(!is_null($obj) && (int)$obj->id > 0)
    {
      $txn = new MeprTransaction($obj->id);

      // Seriously ... if txn was already refunded what are we doing here?
      if($txn->status == MeprTransaction::$refunded_str) { return $txn; }

      $txn->status = MeprTransaction::$refunded_str;

      $this->email_status("Processing Refund: \n" . MeprUtils::object_to_string($_POST) . "\n Affected Transaction: \n" . MeprUtils::object_to_string($txn), $this->settings->debug);

      $txn->store();

      MeprUtils::send_refunded_txn_notices($txn);

      return $txn;
    }

    return false;
  }

  //Not needed in PayPal since PayPal supports the trial payment inclusive of the Subscription
  public function process_trial_payment($transaction) { }
  public function record_trial_payment($transaction) { }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    $mepr_options = MeprOptions::fetch();
    $prd = $txn->product();
    $sub = $txn->subscription();
    $usr = $txn->user();
    $tkn = $sub->token; //Pretty sure this isn't used

    //IMPORTANT - PayPal txn will fail if the descriptions do not match exactly
    //so if you change the description here you also need to mirror it
    //inside of process_signup_form().
    $desc = $this->paypal_desc($txn);

    // Default to 0 for infinite occurrences
    $total_occurrences = $sub->limit_cycles ? $sub->limit_cycles_num : 0;

    //Having issues with subscription start times for our friends in Australia and New Zeland
    //There doesn't appear to be any fixes available from PayPal -- so we'll have to allow them to modify
    //the start time via this filter if it comes to that.
    $gmt_utc_time = MeprHooks::apply_filters('mepr-paypal-website-payments-pro-subscr-start-ts', current_time('timestamp', 1), $this);

    $args = array(
      'PROFILESTARTDATE' => gmdate('Y-m-d\TH:i:s\Z', $gmt_utc_time),
      'DESC' => $desc,
      'BILLINGPERIOD' => $this->paypal_period($prd->period_type),
      'BILLINGFREQUENCY' => $prd->period,
      'TOTALBILLINGCYCLES' => $total_occurrences,
      'AMT' => MeprUtils::format_float($txn->amount),
      'CURRENCYCODE' => $mepr_options->currency_code,
      'EMAIL' => $usr->user_email,
      'ACCT' => sanitize_text_field($_REQUEST['mepr_cc_num']),
      'EXPDATE' => sanitize_text_field($_REQUEST['mepr_cc_exp_month']).sanitize_text_field($_REQUEST['mepr_cc_exp_year']),
      'CVV2' => sanitize_text_field($_REQUEST['mepr_cvv_code']),
      'IPADDRESS' => $_SERVER['REMOTE_ADDR'],
      'FIRSTNAME' => sanitize_text_field($_REQUEST['mepr_first_name']),
      'LASTNAME' => sanitize_text_field($_REQUEST['mepr_last_name']),
      'STREET' => sanitize_text_field($_REQUEST['mepr-address-one']),
      'CITY' => sanitize_text_field($_REQUEST['mepr-address-city']),
      'STATE' => sanitize_text_field($_REQUEST['mepr-address-state']),
      'ZIP' => sanitize_text_field($_REQUEST['mepr-address-zip']),
      'COUNTRYCODE' => sanitize_text_field($_REQUEST['mepr-address-country'])
    );

    if($sub->trial) {
      $args = array_merge(
        array(
          'TRIALBILLINGPERIOD' => 'Day',
          'TRIALBILLINGFREQUENCY' => $sub->trial_days,
          'TRIALAMT' => $sub->trial_amount,
          'TRIALTOTALBILLINGCYCLES' => 1
        ),
        $args
      );
    }

    $args = MeprHooks::apply_filters('mepr_paypal_pro_create_subscriptions_args', $args, $txn, $sub);

    if(!$usr->address_is_set()) { $usr->set_address($_REQUEST); }

    $this->email_status("Paypal Create Subscription \$args:\n".MeprUtils::object_to_string($args,true)."\n", $this->settings->debug);

    $res = $this->send_nvp_request('CreateRecurringPaymentsProfile', $args);

    $_REQUEST['paypal_response'] = $res;
    $_REQUEST['transaction'] = $txn;
    $_REQUEST['subscription'] = $sub;

    return $this->record_create_subscription();
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    $res = $_REQUEST['paypal_response'];
    $sub = $_REQUEST['subscription'];
    $this->email_status("Paypal Create Subscription Response \$res:\n".MeprUtils::object_to_string($res,true)."\n", $this->settings->debug);

    if( isset($res['L_ERRORCODE0']) and intval($res['L_ERRORCODE0'])==10004 ) {
      $this->send_digital_goods_error_message();
      return false;
    }

    if(isset($res['PROFILESTATUS']) and strtolower($res['PROFILESTATUS'])=='activeprofile') {
      $timestamp = isset($_POST['TIMESTAMP']) ? strtotime($_POST['TIMESTAMP']) : time();

      $txn = $sub->first_txn();
      if($txn == false || !($txn instanceof MeprTransaction)) {
        $txn = new MeprTransaction();
        $txn->user_id = $sub->user_id;
        $txn->product_id = $sub->product_id;
      }

      $old_total = $txn->total;
      $txn->trans_num  = $res['PROFILEID'];
      $txn->status     = MeprTransaction::$confirmed_str;
      $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
      $txn->set_subtotal(0.00); // Just a confirmation txn

      // At the very least the subscription confirmation transaction gives
      // the user a 24 hour grace period so they can log in even before the
      // paypal transaction goes through (paypal batches txns at night)
      $mepr_options = MeprOptions::fetch();

      $trial_days = ( $sub->trial ? $sub->trial_days : $mepr_options->grace_init_days );
      $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
      $txn->expires_at = MeprUtils::ts_to_mysql_date($timestamp + MeprUtils::days($trial_days), 'Y-m-d H:i:s');
      $txn->store();

      $sub->subscr_id=$res['PROFILEID'];
      $sub->status=MeprSubscription::$active_str;
      $sub->created_at = gmdate('c',$timestamp);

      $sub->cc_last4 = substr($_REQUEST['mepr_cc_num'],-4);
      $sub->cc_exp_month = $_REQUEST['mepr_cc_exp_month'];
      $sub->cc_exp_year = $_REQUEST['mepr_cc_exp_year'];

      // This will only work before maybe_cancel_old_sub is run
      $upgrade = $sub->is_upgrade();
      $downgrade = $sub->is_downgrade();

      $event_txn = $sub->maybe_cancel_old_sub();
      $sub->store();

      $this->email_status( "Subscription Transaction\n" .
                           MeprUtils::object_to_string($txn->rec, true),
                           $this->settings->debug );

      // $txn->set_gross($old_total); // Artificially set the old amount for notices

      if($upgrade) {
        $this->upgraded_sub($sub, $event_txn);
      }
      else if($downgrade) {
        $this->downgraded_sub($sub, $event_txn);
      }
      else {
        $this->new_sub($sub, true);
      }

      MeprUtils::send_signup_notices( $txn );

      return array('subscription' => $sub, 'transaction' => $txn);
    }
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    *
    * With PayPal, we bill the outstanding amount of the previous subscription,
    * cancel the previous subscription and create a new subscription
    */
  public function process_update_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);

    $args = MeprHooks::apply_filters('mepr_paypal_pro_update_subscription_args', array(
      'PROFILEID' => $sub->subscr_id,
      'CREDITCARDTYPE' => $_REQUEST['mepr-cc-type'],
      'ACCT' => $_REQUEST['update_cc_num'],
      'EXPDATE' => sprintf('%02d',$_REQUEST['update_cc_exp_month']).sprintf('%04d',$_REQUEST['update_cc_exp_year']),
      'CVV2' => $_REQUEST['update_cvv_code'],
    ), $sub);

    $this->email_status( "PayPal Update Subscription request: \n" . MeprUtils::object_to_string($args, true) . "\n",
                           $this->settings->debug );

    $res = $this->send_nvp_request('UpdateRecurringPaymentsProfile', $args);

    if( !isset($res['ACK']) || strtoupper($res['ACK']) != 'SUCCESS' ) {
      throw new MeprGatewayException(sprintf(__('Updating the Credit Card was unsuccessful. %s', 'memberpress'),$this->error_str($res)));
    }

    $_REQUEST['res'] = $res;
    $_REQUEST['sub_id'] = $sub_id;

    return $this->record_update_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    $sub = new MeprSubscription($_REQUEST['sub_id']);
    $sub->cc_last4 = substr($_REQUEST['update_cc_num'],-4);
    $sub->cc_exp_month = $_REQUEST['update_cc_exp_month'];
    $sub->cc_exp_year = $_REQUEST['update_cc_exp_year'];
    $sub->store();

    return $_REQUEST['res'];
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);
    $this->update_paypal_payment_profile($sub_id,'Suspend');

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    $this->record_suspend_subscription();
  }

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {
    $subscr_id = $_REQUEST['recurring_payment_id'];
    $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

    if(!$sub) { return false; }

    // Seriously ... if sub was already suspended what are we doing here?
    if($sub->status == MeprSubscription::$suspended_str) { return $sub; }

    $sub->status = MeprSubscription::$suspended_str;
    $sub->store();

    MeprUtils::send_suspended_sub_notices($sub);

    return $sub;
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);
    $this->update_paypal_payment_profile($sub_id,'Reactivate');

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    $this->record_resume_subscription();
  }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {
    $subscr_id = $_REQUEST['recurring_payment_id'];
    $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

    if(!$sub) { return false; }

    // Seriously ... if sub was already active what are we doing here?
    if($sub->status == MeprSubscription::$active_str) { return $sub; }

    $sub->status = MeprSubscription::$active_str;
    $sub->store();

    //Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
    $prior_txn = $sub->latest_txn();
    if($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
      $txn = new MeprTransaction();
      $txn->subscription_id = $sub->id;
      $txn->trans_num  = $sub->subscr_id . '-' . uniqid();
      $txn->status     = MeprTransaction::$confirmed_str;
      $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
      $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(1), 'Y-m-d H:i:s');
      $txn->set_subtotal(0.00); // Just a confirmation txn
      $txn->store();
    }

    MeprUtils::send_resumed_sub_notices($sub);

    return $sub;
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);

    // Should already expire naturally at paypal so we have no need
    // to do this when we're "cancelling" because of a natural expiration
    if(!isset($_REQUEST['expire']))
      $this->update_paypal_payment_profile($sub_id,'Cancel');

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    $subscr_id = $_REQUEST['recurring_payment_id'];
    $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

    if(!$sub) { return false; }

    // Seriously ... if sub was already cancelled what are we doing here?
    if($sub->status == MeprSubscription::$cancelled_str) { return $sub; }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if(isset($_REQUEST['expire']))
      $sub->limit_reached_actions();

    if(!isset($_REQUEST['silent']) || ($_REQUEST['silent']==false))
      MeprUtils::send_cancelled_sub_notices($sub);

    return $sub;
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
  */
  public function process_signup_form($txn) {
    //if($txn->amount <= 0.00) {
    //  MeprTransaction::create_free_transaction($txn);
    //  return;
    //}
  }

  public function display_payment_page($txn) {
    // Nothing here yet
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    wp_enqueue_script('mepr-gateway-checkout', MEPR_JS_URL . '/gateway/checkout.js', array('mepr-checkout-js'), MEPR_VERSION);
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    * Can be overridden if custom scripts are necessary.
    */
  public function enqueue_user_account_scripts() {
    if( MeprUtils::valid_url_param('action','update','GET') && // (routing) Are we on the update credit card page?
        MeprUtils::valid_url_param('sub', null, 'GET') && // (routing) Do we have a sub url parameter?
        MeprSubscription::exists((int)$_GET['sub']) ) { // Does the subscription exist?
      $sub = new MeprSubscription((int)$_GET['sub']);

      // Ensure that the gateway associated with the subscription we're updating is for PayPalPro
      if($sub->gateway == $this->id) {
        wp_enqueue_script('mepr-default-gateway-checkout-js');
      }
    }
  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;

    $txn = new MeprTransaction($txn_id);
    $usr = $txn->user();

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;

    ?>
    <div class="mp_wrapper mp_payment_form_wrapper">
      <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
      <form action="" method="post" id="payment-form" class="mepr-checkout-form mepr-form mepr-card-form" novalidate>
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn_id; ?>" />
        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('Credit Card Number', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid Credit Card Number', 'memberpress'); ?></span>
          </div>
          <input type="tel" class="mepr-form-input cc-number validation" pattern="\d*" autocomplete="cc-number" required />
          <input type="hidden" class="mepr-cc-num" name="mepr_cc_num"/>
          <script>
            jQuery(document).ready(function($) {
              $('input.cc-number').on('change blur', function (e) {
                var num = $(this).val().replace(/ /g, '');
                $('input.mepr-cc-num').val( num );
              });
            });
          </script>
        </div>

        <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('Expiration', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid Expiration', 'memberpress'); ?></span>
          </div>
          <input type="tel" class="mepr-form-input cc-exp validation" pattern="\d*" autocomplete="cc-exp" placeholder="mm/yy" required>
          <input type="hidden" class="cc-exp-month" name="mepr_cc_exp_month"/>
          <input type="hidden" class="cc-exp-year" name="mepr_cc_exp_year"/>
          <script>
            jQuery(document).ready(function($) {
              $('input.cc-exp').on('change blur', function (e) {
                var exp = $(this).payment('cardExpiryVal');
                $( 'input.cc-exp-month' ).val( exp.month );
                $( 'input.cc-exp-year' ).val( exp.year );
              });
            });
          </script>
        </div>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('CVC', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid CVC Code', 'memberpress'); ?></span>
          </div>
          <input type="tel" name="mepr_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
        </div>

        <?php if(empty($usr->first_name) || empty($usr->last_name)): ?>
          <div class="mp-form-row">
            <label><?php _e('First Name', 'memberpress'); ?></label>
            <input type="text" name="mepr_first_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_first_name']))?$_POST['mepr_first_name']:$usr->first_name; ?>" />
          </div>

          <div class="mp-form-row">
            <label><?php _e('Last Name', 'memberpress'); ?></label>
            <input type="text" name="mepr_last_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_last_name']))?$_POST['mepr_last_name']:$usr->last_name; ?>" />
          </div>
        <?php else: ?>
          <input type="hidden" name="mepr_first_name" value="<?php echo $usr->first_name; ?>" />
          <input type="hidden" name="mepr_last_name" value="<?php echo $usr->last_name; ?>" />
        <?php endif; ?>

        <?php if( !$usr->address_is_set() ): MeprUsersHelper::render_address_fields(); ?>
        <?php else: ?>
          <input type="hidden" name="mepr-address-one" value="<?php echo $usr->address('one'); ?>" />
          <input type="hidden" name="mepr-address-two" value="<?php echo $usr->address('two'); ?>" />
          <input type="hidden" name="mepr-address-city" value="<?php echo $usr->address('city'); ?>" />
          <input type="hidden" name="mepr-address-state" value="<?php echo $usr->address('state'); ?>" />
          <input type="hidden" name="mepr-address-zip" value="<?php echo $usr->address('zip'); ?>" />
          <input type="hidden" name="mepr-address-country" value="<?php echo $usr->address('country'); ?>" />
        <?php endif; ?>

        <div class="mepr_spacer">&nbsp;</div>

        <input type="submit" class="mepr-submit" value="<?php _e('Submit', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />
        <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
      </form>
      </div>
    <?php

    MeprHooks::do_action('mepr-paypal-pro-payment-form', $txn);
  }

  public function process_payment_form($txn) {
    //We're just here to update the user's name if they changed it
    $user = $txn->user();
    $first_name = sanitize_text_field(wp_unslash($_POST['mepr_first_name']));
    $last_name = sanitize_text_field(wp_unslash($_POST['mepr_last_name']));

    if($user->first_name != $first_name) {
      update_user_meta($user->ID, 'first_name', $first_name);
    }

    if($user->last_name != $last_name) {
      update_user_meta($user->ID, 'last_name', $last_name);
    }

    //Call the parent to handle the rest of this
    parent::process_payment_form($txn);
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST['mepr_transaction_id']) || !is_numeric($_POST['mepr_transaction_id']))
      $errors[] = __('An unknown error has occurred.', 'memberpress');

    if( !$mepr_options->show_fname_lname &&
        ( !isset($_POST['mepr_first_name']) || empty($_POST['mepr_first_name']) ||
          !isset($_POST['mepr_last_name']) || empty($_POST['mepr_last_name']) ) ) {
      $errors[] = __('Your first name and last name must not be blank.', 'memberpress');
    }

    if(!isset($_POST['mepr_cc_num']) || empty($_POST['mepr_cc_num'])) {
      $errors[] = __('You must enter your Credit Card number.', 'memberpress');
    }
    elseif(!$this->is_credit_card_valid($_POST['mepr_cc_num'])) {
      $errors[] = __('Your credit card number is invalid.', 'memberpress');
    }

    if(!isset($_POST['mepr_cvv_code']) || empty($_POST['mepr_cvv_code'])) {
      $errors[] = __('You must enter your CVV code.', 'memberpress');
    }

    return $errors;
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $api_username = trim($this->settings->api_username);
    $api_password = trim($this->settings->api_password);
    $signature    = trim($this->settings->signature);
    $sandbox      = ($this->settings->sandbox=='on' or $this->settings->sandbox==true);
    $debug        = ($this->settings->debug=='on' or $this->settings->debug==true);
    $force_ssl    = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);

    ?>
    <table>
      <tr>
        <td><?php _e('API Username*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_username]" value="<?php echo $api_username; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('API Password*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_password]" value="<?php echo $api_password; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('Signature*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][signature]" value="<?php echo $signature; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][sandbox]"<?php echo checked($sandbox); ?> />&nbsp;<?php _e('Use PayPal Sandbox', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][debug]"<?php echo checked($debug); ?> />&nbsp;<?php _e('Send PayPal Debug Emails', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][force_ssl]"<?php checked($force_ssl); ?> />&nbsp;<?php _e('Force SSL', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td><?php _e('PayPal IPN URL:', 'memberpress'); ?></td>
        <td><input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" class="clippy_input" value="<?php echo $this->notify_url('ipn'); ?>" /><span class="clippy"><?php echo $this->notify_url('ipn'); ?></span></td>
      </tr>
      <?php MeprHooks::do_action('mepr-paypal-express-options-form', $this); ?>
    </table>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if( !isset($_POST[$mepr_options->integrations_str][$this->id]['api_username']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['api_username']) )
      $errors[] = __("PayPal API Username field can't be blank.", 'memberpress');
    else if( !isset($_POST[$mepr_options->integrations_str][$this->id]['api_password']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['api_password']) )
      $errors[] = __("PayPal API Password field can't be blank.", 'memberpress');
    else if( !isset($_POST[$mepr_options->integrations_str][$this->id]['signature']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['signature']) )
      $errors[] = __("PayPal Signature field can't be blank.", 'memberpress');

    return $errors;
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    $sub = new MeprSubscription($sub_id);

    $last4 = isset($_POST['update_cc_num']) ? substr($_POST['update_cc_num'],-4) : $sub->cc_last4;
    $exp_month = isset($_POST['update_cc_exp_month']) ? $_POST['update_cc_exp_month'] : $sub->cc_exp_month;
    $exp_year = isset($_POST['update_cc_exp_year']) ? $_POST['update_cc_exp_year'] : $sub->cc_exp_year;

    // Only include the full cc number if there are errors
    if(strtolower($_SERVER['REQUEST_METHOD'])=='post' and empty($errors)) {
      $sub->cc_last4 = $last4;
      $sub->cc_exp_month = $exp_month;
      $sub->cc_exp_year = $exp_year;
      $sub->store();

      unset($_POST['update_cvv_code']); // Unset this for security
    }
    else { // If there are errors then show the full cc num ... if it's there
      $last4 = isset($_POST['update_cc_num']) ? $_POST['update_cc_num'] : $sub->cc_last4;
    }

    $ccv_code = isset($_POST['update_cvv_code'])?$_POST['update_cvv_code']:'';
    $exp = sprintf('%02d / %d', $exp_month, $exp_year);

    ?>
    <div class="mp_wrapper">
      <form action="" method="post" id="mepr_paypal_pro_update_cc_form" class="mepr-checkout-form mepr-form" novalidate>
        <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
        <div class="mepr_update_account_table">
          <div><strong><?php _e('Update your Credit Card information below', 'memberpress'); ?></strong></div>
          <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
          <div class="mp-form-row">
            <label><?php _e('Credit Card Number', 'memberpress'); ?></label>
            <input type="text" class="mepr-form-input cc-number validation" pattern="\d*" autocomplete="cc-number" placeholder="<?php echo MeprUtils::cc_num($last4); ?>" required />
            <input type="hidden" class="mepr-cc-num" name="update_cc_num"/>
            <script>
              jQuery(document).ready(function($) {
                $('input.cc-number').on('change blur', function (e) {
                  var num = $(this).val().replace(/ /g, '');
                  $('input.mepr-cc-num').val( num );
                });
              });
            </script>
          </div>

          <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('Expiration', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid Expiration', 'memberpress'); ?></span>
            </div>
            <input type="text" class="mepr-form-input cc-exp validation" value="<?php echo $exp; ?>" pattern="\d*" autocomplete="cc-exp" placeholder="mm/yy" required>
            <input type="hidden" class="cc-exp-month" name="update_cc_exp_month"/>
            <input type="hidden" class="cc-exp-year" name="update_cc_exp_year"/>
            <script>
              jQuery(document).ready( function($) {
                $('input.cc-exp').on('change blur', function (e) {
                  var exp = $(this).payment('cardExpiryVal');
                  $( 'input.cc-exp-month' ).val( exp.month );
                  $( 'input.cc-exp-year' ).val( exp.year );
                });
              });
            </script>
          </div>

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('CVC', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid CVC Code', 'memberpress'); ?></span>
            </div>
            <input type="text" name="update_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
          </div>
        </div>

        <div class="mepr_spacer">&nbsp;</div>

        <input type="submit" class="mepr-submit" value="<?php _e('Update Credit Card', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />
        <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
      </form>
    </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    if( !isset($_POST['_mepr_nonce']) or empty($_POST['_mepr_nonce']) or
        !wp_verify_nonce($_POST['_mepr_nonce'], 'mepr_process_update_account_form') )
      $errors[] = __('An unknown error has occurred. Please try again.', 'memberpress');

    if(!isset($_POST['update_cc_num']) || empty($_POST['update_cc_num']))
      $errors[] = __('You must enter your Credit Card number.', 'memberpress');
    elseif(!$this->is_credit_card_valid($_POST['update_cc_num']))
      $errors[] = __('Your credit card number is invalid.', 'memberpress');

    if(!isset($_POST['update_cvv_code']) || empty($_POST['update_cvv_code']))
      $errors[] = __('You must enter your CVV code.', 'memberpress');

    return $errors;
  }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form($sub_id) {
    return $this->process_update_subscription($sub_id);
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return (isset($this->settings->sandbox) and $this->settings->sandbox);
  }

  public function force_ssl() {
    return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
  }

  private function send_nvp_request($method_name, $args, $method='post', $blocking=true) {
    $args = array_merge(
      array(
        'VERSION'   => $this->settings->api_version,
        'SIGNATURE' => $this->settings->signature,
        'USER'      => $this->settings->api_username,
        'PWD'       => $this->settings->api_password,
        'METHOD'    => $method_name
      ),
      $args
    );

    $args = MeprHooks::apply_filters('mepr_paypal_pro_send_request_args', $args);

    $arg_array = MeprHooks::apply_filters('mepr_paypal_pro_send_request', array(
      'method'      => strtoupper($method),
      'headers'     => array('PAYPAL-NVP' => 'Y'),
      'body'        => $args,
      'timeout'     => 70,
      'blocking'    => $blocking,
      'sslverify'   => false, // We assume the cert on paypal is trusted
      'user-agent'  => 'MemberPress',
      'httpversion' => '1.1' //PayPal is now requiring this
    ));

    $this->email_status("Sending Paypal Request to {$this->settings->api_url}\n" . MeprUtils::object_to_string($arg_array, true) . "\n" . json_encode($arg_array), $this->settings->debug);

    $resp = wp_remote_post($this->settings->api_url, $arg_array);

    $this->email_status("Got Paypal Response\n" . MeprUtils::object_to_string($resp, true) . "\n", $this->settings->debug);

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if( $blocking==false ) { return true; }

    if( is_wp_error( $resp ) ) {
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s' , 'memberpress'), $this->name ) );
    }
    else {
      $resp_args = wp_parse_args($resp['body']);

      if(isset($resp_args['ACK']) && strtolower($resp_args['ACK']) != 'success') {
        throw new MeprHttpException( $resp_args['L_LONGMESSAGE0'] );
      }

      return $resp_args;
    }

    return false;
  }

  public function return_handler() {
    // Handled with a GET REQUEST by PayPal
    $this->email_status("Paypal Return \$_REQUEST:\n".MeprUtils::object_to_string($_REQUEST,true)."\n", $this->settings->debug);

    $mepr_options = MeprOptions::fetch();

    if( ( isset($_REQUEST['token']) and ($token = $_REQUEST['token']) ) ||
        ( isset($_REQUEST['TOKEN']) and ($token = $_REQUEST['TOKEN']) ) ) {
      $obj = MeprTransaction::get_one_by_trans_num($token);

      $txn = new MeprTransaction();
      $txn->load_data($obj);

      $this->email_status("Paypal Transaction \$txn:\n".MeprUtils::object_to_string($txn,true)."\n", $this->settings->debug);

      try {
        $this->process_payment_form($txn);
        $txn = new MeprTransaction($txn->id); //Grab the txn again, now that we've updated it
        $product = new MeprProduct($txn->product_id);
        $sanitized_title = sanitize_title($product->post_title);
        $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $product->ID);
        if($txn->subscription_id > 0) {
          $sub = $txn->subscription();
          $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
        }
        MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
      }
      catch( Exception $e ) {
        $prd = $txn->product();
        MeprUtils::wp_redirect($prd->url( '?action=payment_form&txn='.$txn->trans_num.'&message='.$e->getMessage().'&_wpnonce='.wp_create_nonce('mepr_payment_form')));
      }
    }
  }

  public function cancel_handler() {
    // Handled with a GET REQUEST by PayPal
    $this->email_status("Paypal Cancel \$_REQUEST:\n".MeprUtils::object_to_string($_REQUEST,true)."\n", $this->settings->debug);

    if(isset($_REQUEST['token']) and ($token = $_REQUEST['token'])) {
      $txn = MeprTransaction::get_one_by_trans_num($token);
      $txn = new MeprTransaction($txn->id);

      // Make sure the txn status is pending
      $txn->status = MeprTransaction::$pending_str;
      $txn->store();

      if( $sub = $txn->subscription() ) {
        $sub->status = MeprSubscription::$pending_str;
        $sub->store();
      }

      if($txn) {
        $prd = new MeprProduct($txn->product_id);
        // TODO: Send an abandonment email
        MeprUtils::wp_redirect($this->message_page_url($prd,'cancel'));
      }
      else
        MeprUtils::wp_redirect(home_url());
    }
  }

  public function cancel_message() {
    $mepr_options = MeprOptions::fetch();
    ?>
      <h4><?php _e('Your payment at PayPal was cancelled.', 'memberpress'); ?></h4>
      <p><?php echo MeprHooks::apply_filters('mepr_paypal_website_payments_pro_cancel_message', sprintf(__('You can retry your purchase by %1$sclicking here%2$s.', 'memberpress'), '<a href="'.MeprUtils::get_permalink().'">', '</a>')); ?><br/></p>
    <?php
  }

  public function paypal_period($period_type) {
    if($period_type=='months')
      return 'Month';
    else if($period_type=='years')
      return 'Year';
    else if($period_type=='weeks')
      return 'Week';
    else
      return $period_type;
  }

  private function send_digital_goods_error_message() {
    $subject = sprintf( __( '** PayPal Payment ERROR on %s' , 'memberpress'), get_option('blogname') );
    $body = __( 'Your PayPal account isn\'t setup to sell Digital Goods.

Your recurring billing profiles and transactions won\'t complete properly until this problem is fixed.

Follow these instructions to enable Digital Goods:
1) Sign in to your PayPal account (must be signed in before visiting the link below)
2) Visit https://www.paypal.com/us/webapps/mpp/digital in your browser
3) Follow the steps PayPal provides here to add Digital Goods to your account

If you still have issues getting this to work, please contact customer support at http://memberpress.com/support.

Thanks,

The MemberPress Team
' , 'memberpress');

    MeprUtils::wp_mail_to_admin($subject, $body);
  }

  private function paypal_desc($txn) {
    $prd = new MeprProduct($txn->product_id);

    if($prd->register_price_action == 'hidden' && !empty($prd->post_title))
      return $prd->post_title;
    elseif($prd->register_price_action == 'custom' && !empty($prd->register_price) && !$txn->coupon_id && !$txn->prorated)
      return "{$prd->post_title} - " . stripslashes($prd->register_price);
    else
      return "{$prd->post_title} - " . MeprTransactionsHelper::format_currency($txn);
  }

  private function update_paypal_payment_profile($sub_id,$action='cancel') {
    $sub = new MeprSubscription($sub_id);

    $args = MeprHooks::apply_filters('mepr_paypal_pro_update_payment_profile_args', array(
      'PROFILEID' => $sub->subscr_id,
      'ACTION' => $action
    ), $sub);

    $this->email_status("PayPal Update subscription request: \n" . MeprUtils::object_to_string($args, true) . "\n",
                        $this->settings->debug);

    $res = $this->send_nvp_request('ManageRecurringPaymentsProfileStatus', $args);

    $this->email_status("PayPal Update subscription response: \n" . MeprUtils::object_to_string($res, true) . "\n",
                        $this->settings->debug);

    if(strtolower($res['ACK']) != 'success') {
      throw new MeprGatewayException(sprintf(__('There was a problem cancelling. %s', 'memberpress'),$this->error_str($res)));
    }

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
  }

  private function error_str($res) {
    $error_str = isset($res['L_SEVERITYCODE0']) ? $res['L_SEVERITYCODE0'] : '';
    $error_str .= ' ';
    $error_str .= isset($res['L_ERRORCODE0']) ? $res['L_ERRORCODE0'] : '';
    $error_str .= ' ';
    $error_str .= isset($res['L_SHORTMESSAGE0']) ? $res['L_SHORTMESSAGE0'] : '';

    return $error_str;
  }
}
