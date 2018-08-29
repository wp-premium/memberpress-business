<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprPayPalStandardGateway extends MeprBasePayPalGateway {
  public static $has_spc_form = false;
  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = __("PayPal Standard", 'memberpress');
    $this->set_defaults();

    // Setup the notification actions for this gateway
    $this->notifiers = array( 'ipn' => 'listener',
                              'cancel' => 'cancel_handler',
                              'return' => 'return_handler' );
    $this->message_pages = array('cancel' => 'cancel_message');
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  protected function set_defaults() {
    if(!isset($this->settings))
      $this->settings = array();

    $this->settings =
      (object)array_merge(
        array(
          'gateway' => 'MeprPayPalStandardGateway',
          'id' => $this->generate_id(),
          'label' => '',
          'use_label' => true,
          'icon' => MEPR_IMAGES_URL . '/checkout/paypal.png',
          'use_icon' => true,
          'desc' => __('Pay via your PayPal account', 'memberpress'),
          'use_desc' => true,
          'paypal_email' => '',
          'advanced_mode' => false,
          'api_username' => '',
          'api_password' => '',
          'signature' => '',
          'sandbox' => false,
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

    if($this->settings->advanced_mode == 'on' or $this->settings->advanced_mode == true) {
      $this->capabilities = array(
        'process-payments',
        'process-refunds',
        'create-subscriptions',
        'cancel-subscriptions',
        'update-subscriptions',
        'suspend-subscriptions',
        'resume-subscriptions',
        'subscription-trial-payment' //The trial payment doesn't have to be processed as a separate one-off like Authorize.net & Stripe
      );
    }
    else {
      $this->capabilities = array(
        'process-payments',
        'create-subscriptions',
        'update-subscriptions',
        'subscription-trial-payment' //The trial payment doesn't have to be processed as a separate one-off like Authorize.net & Stripe
      );
    }

    // An attempt to correct people who paste in spaces along with their credentials
    $this->settings->paypal_email = trim($this->settings->paypal_email);
  }

  /** Listens for an incoming connection from PayPal and then handles the request appropriately. */
  public function listener() {
    $_POST = wp_unslash($_POST);
    $this->email_status("PayPal IPN Recieved\n" . MeprUtils::object_to_string($_POST, true) . "\n", $this->settings->debug);

    if($this->validate_ipn()) { return $this->process_ipn(); }

    return false;
  }

  private function process_ipn() {
    if(isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'web_accept') {
      if($this->is_ipn_for_me()) {
        $this->record_payment();
      }
    }
    elseif(isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'subscr_signup') {
      //We're only going to use subscr_signup here for free trial periods
      //Otherwise the record_create_subscription will be called during subscr_payment
      //because PayPal decided it would be great to send the subscr_payment webhook before the subscr_signup DOH!
      if(!$this->is_ipn_for_me() || !isset($_POST['item_number'])) { //Need a txn ID
        return;
      }

      $txn = new MeprTransaction($_POST['item_number']);

      if(!isset($txn->id) || empty($txn->id) || (int)$txn->id <= 0) //No txn
        return;

      $sub = new MeprSubscription($txn->subscription_id);

      if($sub && $sub->id > 0 && $sub->trial && $sub->trial_amount <= 0.00) {
        $this->record_create_subscription();
      }
    }
    elseif(isset($_POST['txn_type']) && (strtolower($_POST['txn_type']) == 'subscr_payment' || strtolower($_POST['txn_type']) == 'recurring_payment')) {
      if(!isset($_POST['recurring_payment_id']) && !isset($_POST['subscr_id']))
        return;

      //First see if the subscription has already been setup with the correct I- or S- number
      if(isset($_POST['subscr_id']) && !empty($_POST['subscr_id']))
        $sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id']);
      else
        $sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id']);

      //If no $sub at this point it's safe to assume this is a new signup so let's get the $sub from the $txn instead
      //This order of operations to get the $sub will prevent issues between multiple sites sharing the same IPN URL (via IPN FWD ADDON)
      if($sub === false && isset($_POST['item_number'])) {
        //CANNOT DO IPN CHECK UNTIL HERE OR IT WILL MESS STUFF UP
        if(!$this->is_ipn_for_me()) { return; } //This isn't for us, so let's bail

        $txn = new MeprTransaction($_POST['item_number']);
        $sub = $txn->subscription();
      }

      if($sub !== false) {
        //The subscription hasn't been setup yet so let's set it up first
        if(strpos($sub->subscr_id, 'S-') === false && strpos($sub->subscr_id, 'I-') === false) {
          $this->record_create_subscription();
        }

        //Recrod recurring payment
        $this->record_subscription_payment();
      }
    }
    elseif(isset($_POST['parent_txn_id']) && !isset($_POST['txn_type'])) {
      if(in_array(strtolower($_POST['payment_status']), array('refunded','reversed','voided'))) {
        return $this->record_refund();
      }
    }
    elseif(isset($_POST['txn_type']) && strtolower($_POST['txn_type']) == 'recurring_payment_suspended') {
      $this->record_suspend_subscription();
    }
    elseif(isset($_POST['txn_type']) && (strtolower($_POST['txn_type']) == 'recurring_payment_profile_cancel' || strtolower($_POST['txn_type']) == 'subscr_cancel' || strtolower($_POST['txn_type']) == 'recurring_payment_suspended_due_to_max_failed_payment')) {
      $this->record_cancel_subscription();
    }
    elseif( ( isset($_POST['txn_type']) &&
              in_array( strtolower($_POST['txn_type']), array('recurring_payment_skipped', 'subscr_failed') ) ) ||
            ( isset($_POST['payment_status']) &&
              in_array( strtolower($_POST['payment_status']), array('denied', 'expired', 'failed') ) ) ) {
      $this->record_payment_failure();
    }
  }

  public function is_ipn_for_me() {
    if(isset($_POST['custom']) && !empty($_POST['custom'])) {
      $custom_vars = (array)json_decode($_POST['custom']);

      if(isset($custom_vars['gateway_id']) && $custom_vars['gateway_id'] == $this->id) {
        return true;
      }
    }

    return false;
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
      $first_txn = new MeprTransaction($sub->first_txn_id);

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $first_txn = new MeprTransaction();
        $first_txn->user_id = $sub->user_id;
        $first_txn->product_id = $sub->product_id;
        $first_txn->coupon_id = $sub->coupon_id;
      }

      //If this is a trial payment, let's just convert the confirmation txn into a payment txn
      if($this->is_subscr_trial_payment($sub)) {
        $txn = $first_txn; //For use below in send notices
        $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
        $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
        $txn->gateway    = $this->id;
        $txn->trans_num  = $_POST['txn_id'];
        $txn->txn_type   = MeprTransaction::$payment_str;
        $txn->status     = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->set_gross($_POST['mc_gross']);
        $txn->store();
      }
      else {
        $existing = MeprTransaction::get_one_by_trans_num($_POST['txn_id']);

        //There's a chance this may have already happened during the return handler, if so let's just get everything up to date on the existing one
        if($existing != null && isset($existing->id) && (int)$existing->id > 0) {
          $txn = new MeprTransaction($existing->id);
        }
        else {
          $txn = new MeprTransaction();
        }

        $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);
        $txn->user_id    = $first_txn->user_id;
        $txn->product_id = $first_txn->product_id;
        $txn->coupon_id  = $first_txn->coupon_id;
        $txn->gateway    = $this->id;
        $txn->trans_num  = $_POST['txn_id'];
        $txn->txn_type   = MeprTransaction::$payment_str;
        $txn->status     = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->set_gross($_POST['mc_gross']);
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

      $this->email_status("Subscription Transaction\n" . MeprUtils::object_to_string($txn->rec, true), $this->settings->debug);

      MeprUtils::send_transaction_receipt_notices($txn);

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
    elseif( ( isset($_POST['recurring_payment_id']) &&
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['recurring_payment_id'])) ) ||
            ( isset($_POST['subscr_id']) &&
              ($sub = MeprSubscription::get_one_by_subscr_id($_POST['subscr_id'])) ) ) {
      $first_txn = $sub->first_txn();
      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $coupon_id = $sub->coupon_id;
      }
      else {
        $coupon_id = $first_txn->coupon_id;
      }

      $txn = new MeprTransaction();
      $txn->user_id = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->coupon_id = $coupon_id;
      $txn->txn_type = MeprTransaction::$payment_str;
      $txn->status = MeprTransaction::$failed_str;
      $txn->subscription_id = $sub->id;
      // if ipn_track_id isn't set then just use uniqid
      $txn->trans_num = ( isset($_POST['ipn_track_id']) ? $_POST['ipn_track_id'] : uniqid() );
      $txn->gateway = $this->id;
      $txn->set_gross((isset($_POST['mc_gross']))?$_POST['mc_gross']:((isset($_POST['amount']))?$_POST['amount']:0.00));
      $txn->store();

      $sub->expire_txns(); //Expire associated transactions for the old subscription
      $sub->store();
    }
    else
      return false; // Nothing we can do here ... so we outta here

    MeprUtils::send_failed_txn_notices($txn);

    return $txn;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($txn) {
    //Handled in the IPN, only record_payment is needed here
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment() {
    if(!isset($_POST['item_number']) || empty($_POST['item_number'])) { return false; }

    $txn = new MeprTransaction($_POST['item_number']);

    //The amount can be fudged in the URL with PayPal Standard - so let's make sure no fudgyness is goin' on
    if(isset($_POST['mc_gross']) && (float)$_POST['mc_gross'] < (float)$txn->total) {
      $txn->amount     = (float)$_POST['mc_gross'];
      $txn->total      = (float)$_POST['mc_gross'];
      $txn->tax_amount = 0.00;
      $txn->tax_rate   = 0.00;
      $txn->status     = MeprTransaction::$pending_str;
      $txn->txn_type   = MeprTransaction::$payment_str;
      $txn->trans_num  = $_POST['txn_id'];
      $txn->store();

      return false;
    }

    //Already been here somehow?
    if($txn->status == MeprTransaction::$complete_str && $txn->trans_num == $_POST['txn_id']) { return false; }

    if(isset($_POST['payment_status']) && strtolower($_POST['payment_status']) == 'completed') {
      $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();

      $txn->trans_num  = $_POST['txn_id'];
      $txn->txn_type   = MeprTransaction::$payment_str;
      $txn->status     = MeprTransaction::$complete_str;
      $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);

      // This will only work before maybe_cancel_old_sub is run
      $upgrade = $txn->is_upgrade();
      $downgrade = $txn->is_downgrade();

      $event_txn = $txn->maybe_cancel_old_sub();
      $txn->store();

      $this->email_status("Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

      $prd = $txn->product();

      if($prd->period_type == 'lifetime') {
        if($upgrade) {
          $this->upgraded_sub($txn, $event_txn);
        }
        elseif($downgrade) {
          $this->downgraded_sub($txn, $event_txn);
        }
        else {
          $this->new_sub($txn);
        }

        MeprUtils::send_signup_notices($txn);
      }

      MeprUtils::send_transaction_receipt_notices($txn);

      return $txn;
    }

    return false;
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    $mepr_options = MeprOptions::fetch();

    $args = MeprHooks::apply_filters('mepr_paypal_std_refund_args', array(
      'TRANSACTIONID' => $txn->trans_num,
      'REFUNDTYPE'    => "Full",
      'CURRENCYCODE'  => $mepr_options->currency_code
    ), $txn);

    $this->email_status("RefundTransaction Request:\n".MeprUtils::object_to_string($args,true)."\n", $this->settings->debug);
    $res = $this->send_nvp_request('RefundTransaction', $args);
    $this->email_status("RefundTransaction Response:\n".MeprUtils::object_to_string($res,true)."\n", $this->settings->debug);

    if(!isset($res['ACK']) or strtoupper($res['ACK']) != 'SUCCESS')
      throw new MeprGatewayException(__('The refund was unsuccessful. Please login at PayPal and refund the transaction there.', 'memberpress'));

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
    //This all happens in the IPN so record_created_subscription is all that's needed
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    $mepr_options = MeprOptions::fetch();

    $this->email_status("Paypal Create Subscription Response \$_POST:\n".MeprUtils::object_to_string($_POST, true)."\n", $this->settings->debug);

    $temp_txn = new MeprTransaction($_POST['item_number']);

    if((int)$temp_txn->id <= 0) { return; }

    $sub = $temp_txn->subscription();

    if((int)$sub->id > 0) {
      $txn = $sub->first_txn();
      if($txn == false || !($txn instanceof MeprTransaction)) {
        $txn = new MeprTransaction();
        $txn->user_id = $sub->user_id;
        $txn->product_id = $sub->product_id;
      }

      $old_total = $txn->total;

      $timestamp = isset($_POST['payment_date']) ? strtotime($_POST['payment_date']) : time();

      $txn->created_at = MeprUtils::ts_to_mysql_date($timestamp);

      //Only set the trans_num on free trial periods (silly, but necessary if the IPN comes in before the return URL is hit)
      if($sub->trial && $sub->trial_amount <= 0.00) {
        $txn->trans_num = uniqid();
      }

      $txn->status      = MeprTransaction::$confirmed_str;
      $txn->txn_type    = MeprTransaction::$subscription_confirmation_str;
      $txn->set_subtotal(0.00); // Just a confirmation txn
      $trial_days       = ($sub->trial)?$sub->trial_days:$mepr_options->grace_init_days;
      $txn->expires_at  = MeprUtils::ts_to_mysql_date($timestamp + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
      $txn->store();

      $sub->subscr_id   = $_POST['subscr_id'];
      $sub->status      = MeprSubscription::$active_str;
      $sub->created_at  = gmdate('c',$timestamp);

      // This will only work before maybe_cancel_old_sub is run
      $upgrade          = $sub->is_upgrade();
      $downgrade        = $sub->is_downgrade();

      $event_txn = $sub->maybe_cancel_old_sub();
      $sub->store();

      $this->email_status("Subscription Transaction\n" . MeprUtils::object_to_string($txn->rec, true), $this->settings->debug);

      if($upgrade) {
        $this->upgraded_sub($sub, $event_txn);
      }
      else if($downgrade) {
        $this->downgraded_sub($sub, $event_txn);
      }
      else {
        $this->new_sub($sub, true);
      }

      // $txn->set_gross($old_total); // Artificially set the old amount for notices

      MeprUtils::send_signup_notices($txn);

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
    // Account info updated on PayPal.com
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // Account info updated on PayPal.com
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);
    $this->update_paypal_payment_profile($sub_id, 'Suspend');

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
    $this->update_paypal_payment_profile($sub_id, 'Reactivate');

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    $this->record_resume_subscription();
  }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {
    //APPARENTLY PAYPAL DOES NOT SEND OUT AN IPN FOR THIS -- SO WE CAN'T ACTUALLY RECORD THIS HERE UGH
    //BUT WE DO SET THE SUBSCR STATUS BACK TO ACTIVE WHEN THE NEXT PAYMENT CLEARS
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
      $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(1), 'Y-m-d 23:59:59');
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
    //Mostly not possible with PayPal Standard -- 2009 issues with subscriptions starting with S-
    //http://thereforei.am/2012/07/03/cancelling-subscriptions-created-with-paypal-standard-via-the-express-checkout-api/
    $sub = new MeprSubscription($sub_id);

    // Should already expire naturally at paypal so we have no need
    // to do this when we're "cancelling" because of a natural expiration
    if(!isset($_REQUEST['expire']))
      $this->update_paypal_payment_profile($sub_id, 'Cancel');

    $_REQUEST['subscr_id'] = $sub->subscr_id;
    $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    // Not sure how/why this would happen but fail silently if it does
    if(!isset($_REQUEST['subscr_id']) && !isset($_REQUEST['recurring_payment_id'])) { return false; }

    $subscr_id = (isset($_REQUEST['subscr_id']))?$_REQUEST['subscr_id']:$_REQUEST['recurring_payment_id'];
    $sub = MeprSubscription::get_one_by_subscr_id($subscr_id);

    if(!$sub) { return false; }

    // Seriously ... if sub was already cancelled what are we doing here?
    if($sub->status == MeprSubscription::$cancelled_str) { return $sub; }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if(isset($_REQUEST['expire']))
      $sub->limit_reached_actions();

    if(!isset($_REQUEST['silent']) || ($_REQUEST['silent'] == false))
      MeprUtils::send_cancelled_sub_notices($sub);

    return $sub;
  }

  public function process_signup_form($txn) {
    // Nothing here yet
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
  */
  public function display_payment_page($txn) {
    $mepr_options = MeprOptions::fetch();

    if(isset($txn) && ($txn instanceof MeprTransaction)) {
      $prd = $txn->product();
    }
    else {
      throw new Exception(__('Sorry, we couldn\'t complete the transaction. Try back later.', 'memberpress'));
    }

    if($txn->amount <= 0.00) {
      MeprTransaction::create_free_transaction($txn);
      return;
    }

    if($txn->gateway == $this->id) {
      //Txn vars
      $custom = MeprHooks::apply_filters('mepr_paypal_std_custom_payment_vars', array(
        'gateway_id' => $this->id,
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ), $txn);

      $cancel_url   = $this->notify_url('cancel');
      $cancel_delim = MeprUtils::get_delim($cancel_url);

      $payment_vars = MeprHooks::apply_filters('mepr_paypal_std_payment_vars', array(
        'cmd'           => '_xclick',
        'business'      => $this->settings->paypal_email,
        'lc'            => $mepr_options->language_code,
        'currency_code' => $mepr_options->currency_code,
        'item_name'     => $prd->post_title,
        'item_number'   => $txn->id,
        'amount'        => $this->format_currency($txn->total), //not used in subscriptions, may be unset below
        'tax_rate'      => MeprUtils::format_float(0.000, 3),
        'return'        => $this->notify_url('return'),
        'cancel_return' => $cancel_url.$cancel_delim.'txn_id='.$txn->id,
        // 'no_note'       => 1, //Deprecated
        'no_shipping'   => 1,
        'custom'        => json_encode($custom),
        'bn'            => 'Caseproof_SP'
      ), $txn);

      if(!$prd->is_one_time_payment() && ($sub = $txn->subscription())) {
        $period_type_map = array(
          'days'   => 'D',
          'weeks'  => 'W',
          'months' => 'M',
          'years'  => 'Y'
        );

        //Build the subscription vars
        $sub_vars = array(
          'cmd' => '_xclick-subscriptions',
          'src' => 1,
          'sra' => 1, //Attempt to rebill failed txns
          'a3'  => $this->format_currency($sub->total),
          'p3'  => $sub->period,
          't3'  => $period_type_map[$sub->period_type]
        );

        //Handle the limiting of cycles - this is messy with PayPal Standard
        if($sub->limit_cycles) {
          if($sub->limit_cycles_num > 1) {
            $sub_vars['srt'] = $sub->limit_cycles_num; //srt MUST be > 1
          }
          else {
            $sub_vars['src'] = 0; //Tell PayPal not to bill after the first cycle
          }
        }

        //Handle Trial period stuff
        if($sub->trial) {
          $sub_vars = array_merge($sub_vars, $this->calculate_subscription_trial_vars($sub));

          //Set the RETURN differently since we DON'T get an ITEM NUMBER from PayPal on free trial periods doh!
          if($sub->trial_amount <= 0.00) {
            $return_url   = $this->notify_url('return');
            $return_delim = MeprUtils::get_delim($return_url);
            $sub_vars['return'] = $return_url.$return_delim.'free_trial_txn_id='.$txn->id;
          }
        }

        $sub_vars = MeprHooks::apply_filters('mepr_paypal_std_subscription_vars', $sub_vars, $txn, $sub);

        //Merge payment vars with subscr vars overriding payment vars
        $payment_vars = array_merge($payment_vars, $sub_vars);

        //Unset amount from payment vars as it's not used for subscriptions
        unset($payment_vars['amount']);
      }

      //Build the URL
      // No longer doing this yo, GET was deprecated in 2017 by PayPal.
      // $query_str = http_build_query($payment_vars);
      // $url = $this->settings->url . '?' . $query_str;
      // MeprUtils::wp_redirect(str_replace('&amp;', '&', $url));

      $_REQUEST['pp_standard_payment_vars'] = $payment_vars;
      return; //Uh yeah - don't forget this or we'll trigger the exception below
    }

    throw new Exception(__('Sorry, we couldn\'t complete the transaction. Try back later.', 'memberpress'));
  }

  public function calculate_subscription_trial_vars($sub) {
    $sub_vars = array();

    //Trial Amount
    $sub_vars['a1'] = $this->format_currency($sub->trial_amount);

    //Trial Days, Weeks, Months, or Years
    if($sub->trial_days <= 90) {
      $sub_vars['p1'] = $sub->trial_days;
      $sub_vars['t1'] = 'D';
    }
    else {
      if($sub->trial_days % 30 == 0) { //30 days in a month
        $sub_vars['p1'] = (int)($sub->trial_days / 30);
        $sub_vars['t1'] = 'M';
      }
      elseif($sub->trial_days % 365 == 0) { //365 days in a year
        $sub_vars['p1'] = (int)($sub->trial_days / 365);
        $sub_vars['t1'] = 'Y';
      }
      else { //force a round to the nearest week - that's the best we can do here
        $sub_vars['p1'] = round((int)$sub->trial_days / 7);
        $sub_vars['t1'] = 'W';
        $sub->trial_days = (int)($sub_vars['p1'] * 7);
        $sub->store();
      }
    }

    return $sub_vars;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    // No need, handled on the PayPal side
  }

  /** This gets called on the_content and just renders the payment form
      For PayPal Standard we're loading up a hidden form and submitting it with JS
    */
  public function display_payment_form($amount, $user, $product_id, $transaction_id) {
    $payment_vars = isset($_REQUEST['pp_standard_payment_vars'])?$_REQUEST['pp_standard_payment_vars']:array();

    if(empty($payment_vars)) {
      echo '<p id="pp_standard_oops_message">';
      _ex('Woops, someting went wrong. Please try your purchase again.', 'ui', 'memberpress');
      echo '</p>';
    }

    //Show a message?
    ?>
      <p id="pp_standard_redirecting_message"><img src="<?php echo includes_url('js/thickbox/loadingAnimation.gif'); ?>" width="250" />
      <br/>
      <?php _ex('You are being redirected to PayPal now. Please wait...', 'ui', 'memberpress'); ?></p>
    <?php

    //Output the form YO
    echo '<form id="mepr_pp_standard_form" action="'.$this->settings->url.'" method="post">';
    foreach($payment_vars as $key => $val) {
      if($key == 'custom'):
        ?>
          <textarea name="<?php echo $key; ?>" style="display:none;"><?php echo esc_textarea($val); ?></textarea>
        <?php
      else:
        ?>
          <input type="hidden" name="<?php echo $key; ?>" value="<?php echo esc_attr($val); ?>" />
        <?php
      endif;
    }
    echo '</form>';

    //Javascript to force the form to submit
    ?>
    <script type="text/javascript">
      setTimeout(function() {
        document.getElementById("mepr_pp_standard_form").submit();
      }, 1000); //Let's wait one second to let some stuff load up
    </script>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // PayPal does this on their own form
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $paypal_email = trim($this->settings->paypal_email);
    $api_username = trim($this->settings->api_username);
    $api_password = trim($this->settings->api_password);
    $signature    = trim($this->settings->signature);
    $advanced     = ($this->settings->advanced_mode == 'on' or $this->settings->advanced_mode == true);
    $sandbox      = ($this->settings->sandbox == 'on' or $this->settings->sandbox == true);
    $debug        = ($this->settings->debug == 'on' or $this->settings->debug == true);

    ?>
    <table>
      <tr>
        <td><?php _e('Primary PayPal Email*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][paypal_email]" value="<?php echo $paypal_email; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2">
          <input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][advanced_mode]" id="advanced-mode-<?php echo $this->id; ?>" class="advanced_mode_checkbox" data-value="<?php echo $this->id;?>" <?php checked($advanced); ?> />
          <label for="advanced-mode-<?php echo $this->id; ?>"><?php _e('Advanced Mode', 'memberpress'); ?></label>
          &nbsp;&nbsp;
          <em style="vertical-align:middle;">(<?php _e('Only works for PayPal accounts created after 2009', 'memberpress'); ?>)</em>
        </td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('API Username:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_username]" value="<?php echo $api_username; ?>" /></td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('API Password:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][api_password]" value="<?php echo $api_password; ?>" /></td>
      </tr>
      <tr class="advanced_mode_row-<?php echo $this->id;?> mepr_hidden">
        <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<em><?php _e('Signature:', 'memberpress'); ?></em></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][signature]" value="<?php echo $signature; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][sandbox]"<?php echo checked($sandbox); ?> />&nbsp;<?php _e('Use PayPal Sandbox', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][debug]"<?php echo checked($debug); ?> />&nbsp;<?php _e('Send PayPal Debug Emails', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td><?php _e('PayPal IPN URL:', 'memberpress'); ?></td>
        <td><input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" class="clippy_input" value="<?php echo $this->notify_url('ipn'); ?>" /><span class="clippy"><?php echo $this->notify_url('ipn'); ?></span></td>
      </tr>
      <!-- THIS IS NOT ACTUALLY USED ANY LONGER - BUT IT IS REQUIRED FOR THE RETURN DATA TO BE SENT SO LEAVING IT IN PLACE FOR NOW -->
      <tr>
        <td><?php _e('Return URL:', 'memberpress'); ?></td>
        <td><input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" class="clippy_input" value="<?php echo $this->notify_url('return'); ?>" /><span class="clippy"><?php echo $this->notify_url('return'); ?></span></td>
      </tr>
      <?php MeprHooks::do_action('mepr-paypal-standard-options-form', $this); ?>
    </table>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if( !isset($_POST[$mepr_options->integrations_str][$this->id]['paypal_email']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['paypal_email']) or
        !is_email(stripslashes($_POST[$mepr_options->integrations_str][$this->id]['paypal_email'])) ) {
      $errors[] = __("Primary PayPal Email field can't be blank.", 'memberpress');
    }

    return $errors;
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    ?>
    <h3><?php _e('Updating your PayPal Account Information', 'memberpress'); ?></h3>
    <div><?php printf(__('To update your PayPal Account Information, please go to %sPayPal.com%s, login and edit your account information there.', 'memberpress'), '<a href="http://paypal.com" target="blank">', '</a>'); ?></div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    // We'll have them update their cc info on paypal.com
  }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form($sub_id) {
    // We'll have them update their cc info on paypal.com
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return (isset($this->settings->sandbox) and $this->settings->sandbox);
  }

  public function force_ssl() {
    return false; // redirects off site where ssl is installed
  }

  private function send_nvp_request($method_name, $args, $method = 'post', $blocking = true) {
    $args = array_merge(
      array(
        'METHOD'    => $method_name,
        'VERSION'   => $this->settings->api_version,
        'PWD'       => $this->settings->api_password,
        'USER'      => $this->settings->api_username,
        'SIGNATURE' => $this->settings->signature
      ),
      $args
    );

    $args = MeprHooks::apply_filters('mepr_paypal_std_send_request_args', $args);

    $arg_array = MeprHooks::apply_filters('mepr_paypal_std_send_request', array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'httpversion' => '1.1', //PayPal is now requiring this
      'blocking'  => $blocking,
      'sslverify' => false, // We assume the cert on paypal is trusted
      'headers'   => array()
    ));

    $resp = wp_remote_request($this->settings->api_url, $arg_array);

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if($blocking == false)
      return true;

    if(is_wp_error($resp))
      throw new MeprHttpException(sprintf(__( 'You had an HTTP error connecting to %s' , 'memberpress'), $this->name));
    else
      return wp_parse_args($resp['body']);

    return false;
  }

  private function update_paypal_payment_profile($sub_id, $action = 'cancel') {
    $sub = new MeprSubscription($sub_id);

    $args = MeprHooks::apply_filters('mepr_paypal_std_update_payment_profile_args', array(
      'PROFILEID' => $sub->subscr_id,
      'ACTION' => $action
    ), $sub);

    $this->email_status("PayPal Update subscription request: \n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);

    $res = $this->send_nvp_request('ManageRecurringPaymentsProfileStatus', $args);

    $this->email_status("PayPal Update subscription response: \n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

    if(strtolower($res['ACK']) != 'success')
      throw new MeprGatewayException(__('There was a problem cancelling, try logging in directly at PayPal to update the status of your recurring profile.', 'memberpress'));

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
  }

  public function return_handler() {
    $this->email_status("Paypal Return \$_REQUEST:\n".MeprUtils::object_to_string($_REQUEST, true)."\n", $this->settings->debug);

    $mepr_options = MeprOptions::fetch();

    //If PayPal gives us an item_number let's setup this txn now
    if(isset($_GET['item_number']) && is_numeric($_GET['item_number'])) {
      $txn      = new MeprTransaction((int)$_GET['item_number']);
      $sub      = $txn->subscription();
      $product  = new MeprProduct($txn->product_id);

      //Did the IPN already beat us here?
      if(strpos($txn->trans_num, 'mp-txn') === false) {
        $sanitized_title = sanitize_title($product->post_title);
        $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $product->ID);
        if($txn->subscription_id > 0) {
          $sub = $txn->subscription();
          $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
        }
        MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
      }

      //If $sub let's set this up as a confirmation txn until the IPN comes in later so the user can have access now
      if($sub) {
        $txn->trans_num   = uniqid();
        $txn->txn_type    = MeprTransaction::$subscription_confirmation_str;
        $txn->status      = MeprTransaction::$confirmed_str;
        $txn->expires_at  = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(1), 'Y-m-d 23:59:59');
        $txn->store();

        $sub->status      = MeprSubscription::$active_str;
        $sub->created_at  = $txn->created_at; //Set the created at too
        $sub->store();
      }
      else {
        //The amount can be fudged in the URL with PayPal Standard - so let's make sure no fudgyness is goin' on
        if(isset($_GET['amt']) && (float)$_GET['amt'] < (float)$txn->total) {
          $txn->status    = MeprTransaction::$pending_str;
          $txn->txn_type  = MeprTransaction::$payment_str;
          $txn->store();
          wp_die(_x('Your payment amount was lower than expected. Please contact us for assistance if necessary.', 'ui', 'memberpress') . ' <br/><a href="'.$mepr_options->account_page_url('action=subscriptions').'">View my Subscriptions</a>');
        }

        //Don't set a trans_num here - it will get updated when the IPN comes in
        $txn->txn_type    = MeprTransaction::$payment_str;
        $txn->status      = MeprTransaction::$complete_str;
        $txn->store();
      }

      $this->email_status("Paypal Transaction \$txn:\n".MeprUtils::object_to_string($txn, true)."\n", $this->settings->debug);

      $sanitized_title = sanitize_title($product->post_title);
      $query_params = array('membership' => $sanitized_title, 'trans_num' => $txn->trans_num, 'membership_id' => $product->ID);
      if($txn->subscription_id > 0) {
        $sub = $txn->subscription();
        $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
      }
      MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
    }

    //Handle free trial periods here YO
    if(isset($_GET['free_trial_txn_id']) and is_numeric($_GET['free_trial_txn_id'])) {
      $free_trial_txn = new MeprTransaction((int)$_GET['free_trial_txn_id']);
      $fsub           = $free_trial_txn->subscription();
      $product        = new MeprProduct($free_trial_txn->product_id);

      //Did the IPN already beat us here?
      if(strpos($free_trial_txn->trans_num, 'mp-txn') === false) {
        $sanitized_title = sanitize_title($product->post_title);
        $query_params = array('membership' => $sanitized_title, 'trans_num' => $free_trial_txn->trans_num, 'membership_id' => $product->ID);
        if($free_trial_txn->subscription_id > 0) {
          $sub = $free_trial_txn->subscription();
          $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
        }
        MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
      }

      //confirmation txn so the user can have access right away, instead of waiting for the IPN
      $free_trial_txn->set_subtotal(0.00);
      $free_trial_txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
      $free_trial_txn->trans_num  = uniqid();
      $free_trial_txn->status     = MeprTransaction::$confirmed_str;
      $free_trial_txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(1), 'Y-m-d 23:59:59');
      $free_trial_txn->store();

      $fsub->status     = MeprSubscription::$active_str;
      $fsub->created_at = $free_trial_txn->created_at; //Set the created at too
      $fsub->store();

      $this->email_status("Paypal Transaction \$free_trial_txn:\n".MeprUtils::object_to_string($free_trial_txn, true)."\n", $this->settings->debug);

      $sanitized_title = sanitize_title($product->post_title);
      $query_params = array('membership' => $sanitized_title, 'trans_num' => $free_trial_txn->trans_num, 'membership_id' => $product->ID);
      if($free_trial_txn->subscription_id > 0) {
        $sub = $free_trial_txn->subscription();
        $query_params = array_merge($query_params, array('subscr_id' => $sub->subscr_id));
      }
      MeprUtils::wp_redirect($mepr_options->thankyou_page_url(build_query($query_params)));
    }

    //If all else fails, just send them to their account page
    MeprUtils::wp_redirect($mepr_options->account_page_url('action=subscriptions'));
  }

  public function cancel_handler() {
    // Handled with a GET REQUEST by PayPal
    $this->email_status("Paypal Cancel \$_REQUEST:\n".MeprUtils::object_to_string($_REQUEST,true)."\n", $this->settings->debug);

    if(isset($_REQUEST['txn_id']) && is_numeric($_REQUEST['txn_id'])) {
      $txn = new MeprTransaction($_REQUEST['txn_id']);

      // Make sure the txn status is pending
      $txn->status = MeprTransaction::$pending_str;
      $txn->store();

      if($sub = $txn->subscription()) {
        $sub->status = MeprSubscription::$pending_str;
        $sub->store();
      }

      if(isset($txn->product_id) && $txn->product_id > 0) {
        $prd = new MeprProduct($txn->product_id);
        MeprUtils::wp_redirect($this->message_page_url($prd, 'cancel'));
      }
    }

    //If all else fails, just send them to their account page
    MeprUtils::wp_redirect($mepr_options->account_page_url('action=subscriptions'));
  }

  public function cancel_message() {
    $mepr_options = MeprOptions::fetch();
    ?>
      <h4><?php _e('Your payment at PayPal was cancelled.', 'memberpress'); ?></h4>
      <p><?php echo MeprHooks::apply_filters('mepr_paypal_cancel_message', sprintf(__('You can retry your purchase by %1$sclicking here%2$s.', 'memberpress'), '<a href="'.MeprUtils::get_permalink().'">', '</a>')); ?><br/></p>
    <?php
  }
}
