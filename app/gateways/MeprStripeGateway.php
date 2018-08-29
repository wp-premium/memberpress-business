<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeGateway extends MeprBaseRealGateway {
  public static $stripe_plan_id_str = '_mepr_stripe_plan_id';
  public static $has_spc_form = true;

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = __("Stripe", 'memberpress');
    $this->icon = MEPR_IMAGES_URL . '/checkout/cards.png';
    $this->desc = __('Pay with your credit card via Stripe', 'memberpress');
    $this->set_defaults();

    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'suspend-subscriptions',
      'resume-subscriptions',
      'send-cc-expirations'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'whk' => 'listener',
      'update-billing.html' => 'churn_buster'
    );
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
        'gateway' => 'MeprStripeGateway',
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
        'email' => '',
        'sandbox' => false,
        'force_ssl' => false,
        'debug' => false,
        'test_mode' => false,
        'use_stripe_checkout' => false,
        'churn_buster_enabled' => false,
        'churn_buster_uuid' => '',
        'api_keys' => array(
          'test' => array(
            'public' => '',
            'secret' => ''
          ),
          'live' => array(
            'public' => '',
            'secret' => ''
          )
        )
      ),
      (array)$this->settings
    );

    $this->id = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->use_icon = $this->settings->use_icon;
    $this->use_desc = $this->settings->use_desc;
    //$this->recurrence_type = $this->settings->recurrence_type;

    if($this->is_test_mode()) {
      $this->settings->public_key = trim($this->settings->api_keys['test']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['test']['secret']);
    }
    else {
      $this->settings->public_key = trim($this->settings->api_keys['live']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['live']['secret']);
    }
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
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
    }

    $mepr_options = MeprOptions::fetch();

    //Handle zero decimal currencies in Stripe
    $amount = (MeprUtils::is_zero_decimal_currency())?MeprUtils::format_float(($txn->total), 0):MeprUtils::format_float(($txn->total * 100), 0);

    // create the charge on Stripe's servers - this will charge the user's card
    $args = MeprHooks::apply_filters('mepr_stripe_payment_args', array(
      'amount' => $amount,
      'currency' => $mepr_options->currency_code,
      'description' => sprintf(__('%s (transaction: %s)', 'memberpress'), $prd->post_title, $txn->id ),
      'receipt_email' => $usr->user_email,
      'metadata' => array(
        'ip_address' => $_SERVER['REMOTE_ADDR']
      )
    ), $txn);

    // get the credit card details submitted by the form
    if(isset($_REQUEST['stripeToken'])) {
      $args['card'] = $_REQUEST['stripeToken'];
    }
    else if(isset($_REQUEST['stripe_customer'])) {
      $args['customer'] = $_REQUEST['stripe_customer'];
    }
    else if(isset($_REQUEST['mepr_cc_num'])) {
      $args['card'] = array(
        'number'    => $_REQUEST['mepr_cc_num'],
        'exp_month' => $_REQUEST['mepr_cc_exp_month'],
        'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
        'cvc'       => $_REQUEST['mepr_cvv_code']
      );
    }
    else {
      ob_start();
      print_r($_REQUEST);
      $err = ob_get_clean();
      throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.' , 'memberpress') . ' 1 ' . $err );
    }

    $this->email_status('Stripe Charge Happening Now ... ' . MeprUtils::object_to_string($args), $this->settings->debug);

    $charge = (object)$this->send_stripe_request( 'charges', $args, 'post' );
    $this->email_status('Stripe Charge: ' . MeprUtils::object_to_string($charge), $this->settings->debug);

    $txn->trans_num = $charge->id;
    $txn->store();

    $this->email_status('Stripe Charge Happening Now ... 2', $this->settings->debug);

    $_REQUEST['data'] = $charge;

    return $this->record_payment();
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    if(isset($_REQUEST['data'])) {
      $charge = (object)$_REQUEST['data'];

      // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
      if( !isset($charge) || !isset($charge->customer) ||
          !($sub = MeprSubscription::get_one_by_subscr_id($charge->customer)) ||
          ( isset($charge->id) && MeprTransaction::txn_exists($charge->id) ) ) {
        return false;
      }

      $sub->subscr_id = $charge->customer; //Needs to be here to get around some funky GoDaddy caching issue
      $first_txn = $txn = $sub->first_txn();

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $coupon_id = $sub->coupon_id;
      }
      else {
        $coupon_id = $first_txn->coupon_id;
      }

      $this->email_status( "record_subscription_payment:" .
                           "\nSubscription: " . MeprUtils::object_to_string($sub, true) .
                           "\nTransaction: " . MeprUtils::object_to_string($txn, true),
                           $this->settings->debug);

      $txn = new MeprTransaction();
      $txn->user_id    = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->status     = MeprTransaction::$complete_str;
      $txn->coupon_id  = $coupon_id;
      $txn->trans_num  = $charge->id;
      $txn->gateway    = $this->id;
      $txn->subscription_id = $sub->id;

      if(MeprUtils::is_zero_decimal_currency()) {
        $txn->set_gross((float)$charge->amount);
      }
      else {
        $txn->set_gross((float)$charge->amount / 100);
      }

      $sdata = $this->send_stripe_request("customers/{$sub->subscr_id}", array(), 'get');

      // 'subscription' attribute went away in 2014-01-31
      //$txn->expires_at = MeprUtils::ts_to_mysql_date($sdata['subscription']['current_period_end'], 'Y-m-d 23:59:59');

      $this->email_status( "/customers/{$sub->subscr_id}\n" .
                           MeprUtils::object_to_string($sdata, true) .
                           MeprUtils::object_to_string($txn, true),
                           $this->settings->debug );

      $txn->store();

      $sub->status = MeprSubscription::$active_str;

      if($card = $this->get_card($charge)) {
        $sub->cc_exp_month = $card['exp_month'];
        $sub->cc_exp_year  = $card['exp_year'];
        $sub->cc_last4     = $card['last4'];
      }

      $sub->gateway = $this->id;
      $sub->store();
      // If a limit was set on the recurring cycles we need
      // to cancel the subscr if the txn_count >= limit_cycles_num
      // This is not possible natively with Stripe so we
      // just cancel the subscr when limit_cycles_num is hit
      $sub->limit_payment_cycles();

      $this->email_status( "Subscription Transaction\n" .
                           MeprUtils::object_to_string($txn->rec, true),
                           $this->settings->debug );

      MeprUtils::send_transaction_receipt_notices( $txn );
      MeprUtils::send_cc_expiration_notices( $txn );

      return $txn;
    }

    return false;
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    if(isset($_REQUEST['data'])) {
      $charge = (object)$_REQUEST['data'];
      $txn_res = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($txn_res) and isset($txn_res->id)) {
        $txn = new MeprTransaction($txn_res->id);
        $txn->status = MeprTransaction::$failed_str;
        $txn->store();
      }
      elseif(isset($charge) && isset($charge->customer) && ($sub = MeprSubscription::get_one_by_subscr_id($charge->customer))) {
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
        $txn->trans_num = $charge->id;
        $txn->gateway = $this->id;

        if(MeprUtils::is_zero_decimal_currency()) {
          $txn->set_gross((float)$charge->amount);
        }
        else {
          $txn->set_gross((float)$charge->amount / 100);
        }

        $txn->store();

        //If first payment fails, Stripe will not set up the subscription, so we need to mark it as cancelled in MP
        if($sub->txn_count == 0) {
          $sub->status = MeprSubscription::$cancelled_str;
        }
        else {
          $sub->status = MeprSubscription::$active_str;
        }
        $sub->gateway = $this->id;
        $sub->expire_txns(); //Expire associated transactions for the old subscription
        $sub->store();
      }
      else {
        return false; // Nothing we can do here ... so we outta here
      }

      MeprUtils::send_failed_txn_notices($txn);

      return $txn;
    }

    return false;
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment() {
    $this->email_status( "Starting record_payment: " . MeprUtils::object_to_string($_REQUEST), $this->settings->debug );
    if(isset($_REQUEST['data'])) {
      $charge = (object)$_REQUEST['data'];
      $this->email_status("record_payment: \n" . MeprUtils::object_to_string($charge, true) . "\n", $this->settings->debug);
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($obj) and isset($obj->id)) {
        $txn = new MeprTransaction();
        $txn->load_data($obj);
        $usr = $txn->user();

        // Just short circuit if the txn has already completed
        if($txn->status == MeprTransaction::$complete_str)
          return;

        $txn->status    = MeprTransaction::$complete_str;
        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();
        $txn->store();

        $this->email_status("Standard Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

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
        MeprUtils::send_cc_expiration_notices( $txn );
      }
    }

    return false;
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    $args = MeprHooks::apply_filters('mepr_stripe_refund_args', array(), $txn);
    $refund = (object)$this->send_stripe_request( "charges/{$txn->trans_num}/refund", $args );
    $this->email_status( "Stripe Refund: " . MeprUtils::object_to_string($refund), $this->settings->debug );
    $_REQUEST['data'] = $refund;
    return $this->record_refund();
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() {
    if(isset($_REQUEST['data']))
    {
      $charge = (object)$_REQUEST['data'];
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(!is_null($obj) && (int)$obj->id > 0) {
        $txn = new MeprTransaction($obj->id);

        // Seriously ... if txn was already refunded what are we doing here?
        if($txn->status == MeprTransaction::$refunded_str) { return $txn->id; }

        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();

        MeprUtils::send_refunded_txn_notices($txn);

        return $txn->id;
      }
    }

    return false;
  }

  public function process_trial_payment($txn) {
    $mepr_options = MeprOptions::fetch();
    $sub = $txn->subscription();

    // get the credit card details submitted by the form
    if(isset($_REQUEST['stripeToken']))
      $card = $_REQUEST['stripeToken'];
    elseif(isset($_REQUEST['mepr_cc_num'])) {
      $card = array( 'number'    => $_REQUEST['mepr_cc_num'],
                     'exp_month' => $_REQUEST['mepr_cc_exp_month'],
                     'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
                     'cvc'       => $_REQUEST['mepr_cvv_code'] );
    }
    else {
      throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.', 'memberpress') );
    }

    $customer = $this->stripe_customer($txn->subscription_id, $card);

    //Prepare the $txn for the process_payment method
    $txn->set_subtotal($sub->trial_amount);
    $txn->status = MeprTransaction::$pending_str;

    unset($_REQUEST['stripeToken']);
    $_REQUEST['stripe_customer'] = $customer->id;

    //Attempt processing the payment here - the send_aim_request will throw the exceptions for us
    $this->process_payment($txn);

    return $this->record_trial_payment($txn);
  }

  public function record_trial_payment($txn) {
    $sub = $txn->subscription();

    //Update the txn member vars and store
    $txn->txn_type = MeprTransaction::$payment_str;
    $txn->status = MeprTransaction::$complete_str;
    $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
    $txn->store();

    return true;
  }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else {
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
    }

    $mepr_options = MeprOptions::fetch();
    $sub = $txn->subscription();
    //error_log("********** MeprStripeGateway::process_create_subscription Subscription:\n" . MeprUtils::object_to_string($sub));

    //Get the customer -- if the $sub had a paid trial, then the customer was already setup
    if($sub->trial && $sub->trial_amount > 0.00) {
      $customer = $this->stripe_customer($txn->subscription_id);
    }
    else {
      // get the credit card details submitted by the form
      if(isset($_REQUEST['stripeToken'])) {
        $card = $_REQUEST['stripeToken'];
      }
      elseif(isset($_REQUEST['mepr_cc_num'])) {
        $card = array( 'number'    => $_REQUEST['mepr_cc_num'],
                       'exp_month' => $_REQUEST['mepr_cc_exp_month'],
                       'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
                       'cvc'       => $_REQUEST['mepr_cvv_code'] );
      }
      else {
        throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.', 'memberpress') );
      }

      $customer = $this->stripe_customer($txn->subscription_id, $card);
    }

    $plan = $this->stripe_plan($txn->subscription(), true);

    $args = MeprHooks::apply_filters('mepr_stripe_subscription_args', array(
      'plan' => $plan->id,
      'metadata' => array(
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ),
      'tax_percent' => MeprUtils::format_float($txn->tax_rate) //Can't do 3 decimal places here for some reason
    ), $txn, $sub);

    $this->email_status("process_create_subscription: \n" . MeprUtils::object_to_string($txn, true) . "\n", $this->settings->debug);

    $subscr = $this->send_stripe_request("customers/{$customer->id}/subscriptions", $args);

    $sub->subscr_id = $customer->id;
    $sub->store();

    //error_log("********** MeprStripeGateway::process_create_subscription altered Subscription:\n" . MeprUtils::object_to_string($sub));

    $_REQUEST['data'] = $customer;

    return $this->record_create_subscription();
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    $mepr_options = MeprOptions::fetch();

    if(isset($_REQUEST['data'])) {
      $sdata = (object)$_REQUEST['data'];
      //error_log("********** MeprStripeGateway::record_create_subscription sData: \n" . MeprUtils::object_to_string($sdata));
      $sub = MeprSubscription::get_one_by_subscr_id($sdata->id);
      //error_log("********** MeprStripeGateway::record_create_subscription Subscription: \n" . MeprUtils::object_to_string($sub));
      $sub->status=MeprSubscription::$active_str;

      if($card = $this->get_default_card($sdata)) {
        $sub->cc_last4 = $card['last4'];
        $sub->cc_exp_month = $card['exp_month'];
        $sub->cc_exp_year = $card['exp_year'];
      }

      $sub->created_at = gmdate('c');
      $sub->store();

      // This will only work before maybe_cancel_old_sub is run
      $upgrade = $sub->is_upgrade();
      $downgrade = $sub->is_downgrade();

      $event_txn = $sub->maybe_cancel_old_sub();

      $txn = $sub->first_txn();
      if($txn == false || !($txn instanceof MeprTransaction)) {
        $txn = new MeprTransaction();
        $txn->user_id = $sub->user_id;
        $txn->product_id = $sub->product_id;
      }

      $old_total = $txn->total;

      // If no trial or trial amount is zero then we've got to make
      // sure the confirmation txn lasts through the trial
      if(!$sub->trial || ($sub->trial and $sub->trial_amount <= 0.00)) {
        $trial_days = ($sub->trial)?$sub->trial_days:$mepr_options->grace_init_days;

        $txn->trans_num  = $sub->subscr_id;
        $txn->status     = MeprTransaction::$confirmed_str;
        $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
        $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        $txn->set_subtotal(0.00); // Just a confirmation txn
        $txn->store();
      }

      // $txn->set_gross($old_total); // Artificially set the subscription amount

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

    return false;
  }

  public function process_update_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);

    //Fix for duplicate token errors when the_content is somehow run more than once despite our best efforts to avoid it
    static $subscr;
    if(!is_null($subscr)) { return $subscr; }

    if(!isset($_REQUEST['stripeToken'])) {
      ob_start();
      print_r($_REQUEST);
      $err = ob_get_clean();
      throw new MeprGatewayException(__('There was a problem sending your credit card details to the processor. Please try again later. 1' , 'memberpress') . ' 3 ' . $err);
    }

    // get the credit card details submitted by the form
    $token    = $_REQUEST['stripeToken'];
    $customer = $this->stripe_customer($sub_id, $token);

    //TODO check for $customer === false - do better error handling here

    $usr = $sub->user();

    $args = MeprHooks::apply_filters('mepr_stripe_update_subscription_args', array("card" => $token), $sub);

    $subscr = (object)$this->send_stripe_request("customers/{$customer->id}", $args, 'post');
    $sub->subscr_id = $subscr->id;

    if($card = $this->get_default_card($subscr)) {
      $sub->cc_last4 = $card['last4'];
      $sub->cc_exp_month = $card['exp_month'];
      $sub->cc_exp_year = $card['exp_year'];
    }

    $sub->store();

    return $subscr;
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // No need for this one with stripe
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);

    // If there's not already a customer then we're done here
    if(!($customer = $this->stripe_customer($sub_id))) { return false; }

    $args = MeprHooks::apply_filters('mepr_stripe_suspend_subscription_args', array(), $sub);

    // Yeah ... we're cancelling here bro ... with stripe we should be able to restart again
    $res = $this->send_stripe_request( "customers/{$customer->id}/subscription", $args, 'delete' );
    $_REQUEST['data'] = $res;

    return $this->record_suspend_subscription();
  }

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {
    if(isset($_REQUEST['data']))
    {
      $sdata = (object)$_REQUEST['data'];
      if( $sub = MeprSubscription::get_one_by_subscr_id($sdata->customer) ) {
        // Seriously ... if sub was already cancelled what are we doing here?
        if($sub->status == MeprSubscription::$suspended_str) { return $sub; }

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);
      }
    }

    return false;
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    MeprHooks::do_action('mepr-pre-stripe-resume-subscription', $sub_id); //Allow users to change the subscription programatically before resuming it
    $sub = new MeprSubscription($sub_id);

    $customer = $this->stripe_customer($sub_id);

    //Set enough of the $customer data here to get this resumed
    if(empty($customer)) { $customer = (object)array('id' => $sub->subscr_id); }

    $orig_trial        = $sub->trial;
    $orig_trial_days   = $sub->trial_days;
    $orig_trial_amount = $sub->trial_amount;

    if( $sub->is_expired() and !$sub->is_lifetime()) {
      $expiring_txn = $sub->expiring_txn();

      // if it's already expired with a real transaction
      // then we want to resume immediately
      if( $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
          $expiring_txn->status!=MeprTransaction::$confirmed_str ) {
        $sub->trial = false;
        $sub->trial_days = 0;
        $sub->trial_amount = 0.00;
        $sub->store();
      }
    }
    else {
      $sub->trial = true;
      $sub->trial_days = MeprUtils::tsdays(strtotime($sub->expires_at) - time());
      $sub->trial_amount = 0.00;
      $sub->store();
    }

    // Create new plan with optional trial in place ...
    $plan = $this->stripe_plan($sub,true);

    $sub->trial        = $orig_trial;
    $sub->trial_days   = $orig_trial_days;
    $sub->trial_amount = $orig_trial_amount;
    $sub->store();

    $args = MeprHooks::apply_filters('mepr_stripe_resume_subscription_args', array( 'plan' => $plan->id, 'tax_percent' => MeprUtils::format_float($sub->tax_rate) ), $sub);

    $this->email_status( "process_resume_subscription: \n" .
                         MeprUtils::object_to_string($sub, true) . "\n",
                         $this->settings->debug );

    $subscr = $this->send_stripe_request( "customers/{$sub->subscr_id}/subscription", $args, 'post' );

    $_REQUEST['data'] = $customer;
    return $this->record_resume_subscription();
  }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {
    if(isset($_REQUEST['data'])) {
      $mepr_options = MeprOptions::fetch();

      $sdata = (object)$_REQUEST['data'];
      $sub = MeprSubscription::get_one_by_subscr_id($sdata->id);
      $sub->status=MeprSubscription::$active_str;

      if( $card = $this->get_default_card($sdata) ) {
        $sub->cc_last4 = $card['last4'];
        $sub->cc_exp_month = $card['exp_month'];
        $sub->cc_exp_year = $card['exp_year'];
      }

      $sub->store();

      //Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
      $prior_txn = $sub->latest_txn();
      if($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
        $txn = new MeprTransaction();
        $txn->subscription_id = $sub->id;
        $txn->trans_num  = $sub->subscr_id . '-' . uniqid();
        $txn->status     = MeprTransaction::$confirmed_str;
        $txn->txn_type   = MeprTransaction::$subscription_confirmation_str;
        $txn->expires_at = MeprUtils::ts_to_mysql_date(time()+MeprUtils::days(0), 'Y-m-d 23:59:59');
        $txn->set_subtotal(0.00); // Just a confirmation txn
        $txn->store();
      }

      MeprUtils::send_resumed_sub_notices($sub);

      return array('subscription' => $sub, 'transaction' => (isset($txn))?$txn:$prior_txn);
    }

    return false;
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);

    // If there's not already a customer then we're done here
    if(!($customer = $this->stripe_customer($sub_id))) { return false; }

    $args = MeprHooks::apply_filters('mepr_stripe_cancel_subscription_args', array(), $sub);

    $res = $this->send_stripe_request( "customers/{$customer->id}/subscription", $args, 'delete' );
    $_REQUEST['data'] = $res;

    return $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    if(isset($_REQUEST['data']))
    {
      $sdata = (object)$_REQUEST['data'];
      if( $sub = MeprSubscription::get_one_by_subscr_id($sdata->customer) ) {
        // Seriously ... if sub was already cancelled what are we doing here?
        // Also, for stripe, since a suspension is only slightly different
        // than a cancellation, we kick it into high gear and check for that too
        if($sub->status == MeprSubscription::$cancelled_str or
           $sub->status == MeprSubscription::$suspended_str) { return $sub; }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if(isset($_REQUEST['expire']))
          $sub->limit_reached_actions();

        if(!isset($_REQUEST['silent']) || ($_REQUEST['silent']==false))
          MeprUtils::send_cancelled_sub_notices($sub);
      }
    }

    return false;
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
    // Nothing to do here ...
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION);
    wp_enqueue_script('stripe-create-token', MEPR_GATEWAYS_URL . '/stripe/create_token.js', array('stripe-js', 'mepr-checkout-js', 'jquery.payment'), MEPR_VERSION);
    wp_localize_script('stripe-create-token', 'MeprStripeGateway', array( 'public_key' => $this->settings->public_key ));
  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;

    $txn = new MeprTransaction($txn_id);

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;

    ?>
      <div class="mp_wrapper mp_payment_form_wrapper">
        <?php
          if($this->settings->use_stripe_checkout) {
            $this->display_stripe_checkout_form($txn);
          }
          else {
            $this->display_on_site_form($txn);
          }
        ?>
      </div>
    <?php
  }

  //In the future, this could open the door to Apple Pay and Bitcoin?
  //Bitcoin can NOT be used for auto-recurring subs though - not sure about Apple Pay
  public function display_stripe_checkout_form($txn) {
    $mepr_options = MeprOptions::fetch();
    $user         = $txn->user();
    $prd          = $txn->product();
    ?>
      <form action="" method="POST">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn->id; ?>" />
        <script
          src="https://checkout.stripe.com/checkout.js"
          class="stripe-button"
          data-key="<?php echo $this->settings->public_key; ?>"
          data-image="<?php //TODO ?>"
          data-name="<?php echo esc_attr($prd->post_title); ?>"
          data-panel-label="<?php _ex('Submit', 'ui', 'memberpress'); ?>"
          data-label="<?php _ex('Pay Now', 'ui', 'memberpress'); ?>"
          data-zip-code="true"
          data-billing-address="<?php echo ($mepr_options->show_address_fields && $mepr_options->require_address_fields)?'true':'false'; ?>"
          data-email="<?php echo esc_attr($user->user_email); ?>"
          data-currency="<?php echo $mepr_options->currency_code; ?>"
          data-locale="<?php echo $mepr_options->language_code; ?>"
          data-bitcoin="false"> <!-- Eventually we will add bitcoin for non-recurring? -->
        </script>
      </form>
    <?php
  }

  public function display_on_site_form($txn) {
    $mepr_options = MeprOptions::fetch();
    $user         = $txn->user();
    ?>
      <form action="" method="post" id="mepr-stripe-payment-form">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn->id; ?>" />
        <input type="hidden" name="card-name" value="<?php echo $user->get_full_name(); ?>" />
        <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

        <?php if($mepr_options->show_address_fields && $mepr_options->require_address_fields): ?>
          <input type="hidden" name="card-address-1" value="<?php echo get_user_meta($user->ID, 'mepr-address-one', true); ?>" />
          <input type="hidden" name="card-address-2" value="<?php echo get_user_meta($user->ID, 'mepr-address-two', true); ?>" />
          <input type="hidden" name="card-city" value="<?php echo get_user_meta($user->ID, 'mepr-address-city', true); ?>" />
          <input type="hidden" name="card-state" value="<?php echo get_user_meta($user->ID, 'mepr-address-state', true); ?>" />
          <input type="hidden" name="card-zip" value="<?php echo get_user_meta($user->ID, 'mepr-address-zip', true); ?>" />
          <input type="hidden" name="card-country" value="<?php echo get_user_meta($user->ID, 'mepr-address-country', true); ?>" />
        <?php endif; ?>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _ex('Credit Card', 'ui', 'memberpress'); ?></label>
            <span id="card-errors" role="alert" class="stripe_element_error"></span>
          </div>
          <div id="card-element" class="stripe_element_input">
            <!-- a Stripe Element will be inserted here. -->
          </div>
        </div>

        <?php MeprHooks::do_action('mepr-stripe-payment-form', $txn); ?>

        <div class="mepr_spacer">&nbsp;</div>
        <input type="submit" class="mepr-submit" value="<?php _ex('Submit', 'ui', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />

        <noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
      </form>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // This is done in the javascript with Stripe
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $test_secret_key      = trim($this->settings->api_keys['test']['secret']);
    $test_public_key      = trim($this->settings->api_keys['test']['public']);
    $live_secret_key      = trim($this->settings->api_keys['live']['secret']);
    $live_public_key      = trim($this->settings->api_keys['live']['public']);
    $force_ssl            = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);
    $debug                = ($this->settings->debug == 'on' or $this->settings->debug == true);
    $test_mode            = ($this->settings->test_mode == 'on' or $this->settings->test_mode == true);
    $use_stripe_checkout  = ($this->settings->use_stripe_checkout == 'on' or $this->settings->use_stripe_checkout == true);
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' or $this->settings->churn_buster_enabled == true);
    $churn_buster_uuid    = trim($this->settings->churn_buster_uuid);

    $test_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][secret]";
    $test_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][public]";
    $live_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][secret]";
    $live_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][public]";
    $force_ssl_str            = "{$mepr_options->integrations_str}[{$this->id}][force_ssl]";
    $debug_str                = "{$mepr_options->integrations_str}[{$this->id}][debug]";
    $test_mode_str            = "{$mepr_options->integrations_str}[{$this->id}][test_mode]";
    $use_stripe_checkout_str  = "{$mepr_options->integrations_str}[{$this->id}][use_stripe_checkout]";
    $churn_buster_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_enabled]";
    $churn_buster_uuid_str    = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_uuid]";

    ?>
    <table id="mepr-stripe-test-keys-<?php echo $this->id; ?>" class="form-table mepr-stripe-test-keys mepr-hidden">
      <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_secret_key_str; ?>"><?php _e('Test Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $test_secret_key_str; ?>" value="<?php echo $test_secret_key; ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_public_key_str; ?>"><?php _e('Test Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $test_public_key_str; ?>" value="<?php echo $test_public_key; ?>" /></td>
      </tr>
      </tbody>
    </table>
    <table id="mepr-stripe-live-keys-<?php echo $this->id; ?>" class="form-table mepr-stripe-live-keys mepr-hidden">
      <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $live_secret_key_str; ?>"><?php _e('Live Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $live_secret_key_str; ?>" value="<?php echo $live_secret_key; ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $live_public_key_str; ?>"><?php _e('Live Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $live_public_key_str; ?>" value="<?php echo $live_public_key; ?>" /></td>
      </tr>
      </tbody>
    </table>
    <table class="form-table">
      <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $use_stripe_checkout_str; ?>"><?php _e('Use Stripe Checkout (Beta)', 'memberpress'); ?></label></th>
        <td><input class="mepr-stripe-checkout" type="checkbox" name="<?php echo $use_stripe_checkout_str; ?>"<?php echo checked($use_stripe_checkout); ?> /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_mode_str; ?>"><?php _e('Test Mode', 'memberpress'); ?></label></th>
        <td><input class="mepr-stripe-testmode" data-integration="<?php echo $this->id; ?>" type="checkbox" name="<?php echo $test_mode_str; ?>"<?php echo checked($test_mode); ?> /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $force_ssl_str; ?>"><?php _e('Force SSL', 'memberpress'); ?></label></th>
        <td><input type="checkbox" name="<?php echo $force_ssl_str; ?>"<?php echo checked($force_ssl); ?> /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $debug_str; ?>"><?php _e('Send Debug Emails', 'memberpress'); ?></label></th>
        <td><input type="checkbox" name="<?php echo $debug_str; ?>"<?php echo checked($debug); ?> /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label><?php _e('Stripe Webhook URL:', 'memberpress'); ?></label></th>
        <td><input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" class="clippy_input" value="<?php echo $this->notify_url('whk'); ?>" /><span class="clippy"><?php echo $this->notify_url('whk'); ?></span></td>
      </tr>
      <tr valign="top">
        <th scope="row">
          <label for="<?php echo $churn_buster_enabled_str; ?>"><?php _e('Enable Churn Buster', 'memberpress'); ?></label>
          <?php MeprAppHelper::info_tooltip('mepr-stripe-enable-churn-buster',
             __('Enable Churn Buster', 'memberpress'),
             __('Churn Buster is a 3rd party service that allows you to automatically respond to failed payments in Stripe with email campaigns, card update pages, and real-time insights to reduce churn by up to 50%.', 'memberpress'));
          ?>
        </th>
        <td>
          <input type="checkbox" class="mepr-toggle-checkbox" data-box="mepr_stripe_churn_buster_<?php echo $this->id; ?>_box" name="<?php echo $churn_buster_enabled_str; ?>" <?php checked($churn_buster_enabled); ?> />
        </td>
      </tr>
      </tbody>
    </table>
    <div id="mepr_stripe_churn_buster_<?php echo $this->id; ?>_box" class="mepr-sub-box mepr_stripe_churn_buster_<?php echo $this->id; ?>_box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <h3 class="mepr-page-heading"><?php _e('Recover failed payments with Churn Buster', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo $churn_buster_uuid_str; ?>"><?php _e('Churn Buster Account ID', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip('mepr-stripe-churn-buster-uuid',
                __('Churn Buster Account ID', 'memberpress'),
                __('This is the account id that is linked to your Churn Buster account. If you don\'t have a Churn Buster account yet, click the \'Create a Churn Buster Account\' link below.', 'memberpress')); ?>
            </th>
            <td>
              <input type="text" name="<?php echo $churn_buster_uuid_str; ?>" class="regular-text" value="<?php echo $churn_buster_uuid; ?>" />
              <?php if(empty($churn_buster_uuid)): ?>
                <div><em><a href="https://app.churnbuster.io/accounts/new?plan=v6_m_9900_25000&coupon=memberpress"><?php _e('Use this link to start with a free trial month >>', 'memberpress'); ?></a></em></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php if(!empty($churn_buster_uuid)): /** Only show the Churn Buster Update Billing URL if a UUID has been configured */ ?>
            <tr valign="top">
              <th scope="row">
                <label><?php _e('Update Billing URL:', 'memberpress'); ?></label>
                <?php MeprAppHelper::info_tooltip('mepr-stripe-churn-buster-update-card-url',
                  __('Update Billing URL', 'memberpress'),
                  __('You\'ll give this URL to Churn Buster to use in dunning emails so that your customers can update their billing information.', 'memberpress')); ?>
              </th>
              <td>
                <input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" class="clippy_input" value="<?php echo $this->notify_url('update-billing.html', true); ?>" /><span class="clippy"><?php echo $this->notify_url('update-billing.html', true); ?></span>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    $testmode = isset($_REQUEST[$mepr_options->integrations_str][$this->id]['test_mode']);
    $testmodestr  = $testmode ? 'test' : 'live';

    if( !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
        !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) ) {
      $errors[] = __("All Stripe keys must be filled in.", 'memberpress');
    }

    return $errors;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    */
  public function enqueue_user_account_scripts() {
    $sub = (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['sub'])) ? new MeprSubscription((int)$_GET['sub']) : false;
    if($sub !== false && $sub->gateway == $this->id) {
      wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION . time());
      wp_enqueue_script('stripe-create-token', MEPR_GATEWAYS_URL . '/stripe/create_token.js', array('stripe-js'), MEPR_VERSION . time());
      wp_localize_script('stripe-create-token', 'MeprStripeGateway', array( 'public_key' => $this->settings->public_key ));
    }
  }

  /** Displays the update account form on the subscription account page **/
  public function   display_update_account_form($sub_id, $errors=array(), $message='') {
    $mepr_options = MeprOptions::fetch();
    $customer = $this->stripe_customer($sub_id);
    $sub = new MeprSubscription($sub_id);
    $user = $sub->user();

    $cc_exp_month = isset($_REQUEST['card-expiry-month'])?$_REQUEST['card-expiry-month']:$sub->cc_exp_month;
    $cc_exp_year = isset($_REQUEST['card-expiry-year'])?$_REQUEST['card-expiry-year']:$sub->cc_exp_year;

    if( $card = $this->get_default_card($customer) ) {
      $card_num = MeprUtils::cc_num($card['last4']);
      $card_name = ( isset($card['name']) and $card['name']!='undefined' ) ? $card['name'] : $user->get_full_name();
    }
    else {
      $card_num = $sub->cc_num();
      $card_name = $user->get_full_name();
    }

    ?>
      <div class="mp_wrapper">
        <form action="" method="post" id="mepr-stripe-payment-form">
          <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
          <input type="hidden" name="card-name" value="<?php echo $card_name; ?>" />
          <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

          <?php if($mepr_options->show_address_fields && $mepr_options->require_address_fields): ?>
            <input type="hidden" name="card-address-1" value="<?php echo get_user_meta($user->ID, 'mepr-address-one', true); ?>" />
            <input type="hidden" name="card-address-2" value="<?php echo get_user_meta($user->ID, 'mepr-address-two', true); ?>" />
            <input type="hidden" name="card-city" value="<?php echo get_user_meta($user->ID, 'mepr-address-city', true); ?>" />
            <input type="hidden" name="card-state" value="<?php echo get_user_meta($user->ID, 'mepr-address-state', true); ?>" />
            <input type="hidden" name="card-zip" value="<?php echo get_user_meta($user->ID, 'mepr-address-zip', true); ?>" />
            <input type="hidden" name="card-country" value="<?php echo get_user_meta($user->ID, 'mepr-address-country', true); ?>" />
          <?php endif; ?>


          <div class="mepr_update_account_table">
            <div><strong><?php _e('Update your Credit Card information below', 'memberpress'); ?></strong></div><br/>
            <div class="mepr-stripe-errors"></div>
            <?php MeprView::render('/shared/errors', get_defined_vars()); ?>

            <div class="mp-form-row">
              <div class="mp-form-label">
                <label><?php _ex('Credit Card', 'ui', 'memberpress'); ?></label>
                <span id="card-errors" role="alert" class="stripe_element_error"></span>
              </div>
              <div id="card-element" class="stripe_element_input">
                <!-- a Stripe Element will be inserted here. -->
              </div>
            </div>

            <div class="mepr_spacer">&nbsp;</div>
            <input type="submit" class="mepr-submit" value="<?php _ex('Submit', 'ui', 'memberpress'); ?>" />
            <img src="<?php echo admin_url('images/loading.gif'); ?>" style="display: none;" class="mepr-loading-gif" />

            <noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
          </div>
        </form>
      </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    return $errors;
  }

  /** Used to update the credit card information on a subscription by the given gateway.
    * This method should be used by the class to record a successful cancellation from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_account_form($sub_id) {
    $this->process_update_subscription($sub_id);
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return (isset($this->settings->test_mode) and $this->settings->test_mode);
  }

  public function force_ssl() {
    return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
  }

  /** Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate expiration dates.
    * Of course this method is meant to be overridden when a gateway requires it.
    */
  public function get_renewal_base_date(MeprSubscription $sub) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT e.created_at
          FROM {$mepr_db->events} AS e
         WHERE e.event='subscription-resumed'
           AND e.evt_id_type='subscriptions'
           AND e.evt_id=%d
         ORDER BY e.created_at DESC
         LIMIT 1
      ",
      $sub->id
    );

    $renewal_base_date = $wpdb->get_var($q);
    if(!empty($renewal_base_date)) {
      return $renewal_base_date;
    }

    return $sub->created_at;
  }

  /** STRIPE SPECIFIC METHODS **/

  public function listener() {
    // retrieve the request's body and parse it as JSON
    $body = @file_get_contents('php://input');
    $event_json = (object)json_decode($body,true);

    if(!isset($event_json->id)) return;

    // Use the id to pull the event directly from the API (purely a security measure)
    try {
      $event = (object)$this->send_stripe_request( "events/{$event_json->id}", array(), 'get' );
    }
    catch( Exception $e ) {
      http_response_code(202); //Throw a 202 here so stripe doesn't send out a billion webhook broken emails
      die($e->getMessage()); // Do nothing
    }
    //$event = $event_json;

    $_REQUEST['data'] = $obj = (object)$event->data['object'];

    if($event->type=='charge.succeeded') {
      $this->email_status("###Event: {$event->type}\n" . MeprUtils::object_to_string($event, true)."\n", $this->settings->debug);

      // Description only gets set with the txn id in a standard charge
      if(isset($obj->description)) {
        //$this->record_payment(); // done on page
      }
      elseif(isset($obj->customer))
        $this->record_subscription_payment();
    }
    else if($event->type=='charge.failed') {
      $this->record_payment_failure();
    }
    else if($event->type=='charge.refunded') {
      $this->record_refund();
    }
    else if($event->type=='charge.disputed') {
      // Not worried about this right now
    }
    else if($event->type=='customer.subscription.created') {
      //$this->record_create_subscription(); // done on page
    }
    else if($event->type=='customer.subscription.updated') {
      //$this->record_update_subscription(); // done on page
    }
    else if($event->type=='customer.subscription.deleted') {
      $this->record_cancel_subscription();
    }
    else if($event->type=='customer.subscription.trial_will_end') {
      // We may want to implement this feature at some point
    }
  }

  /** Renders a custom page where a credit card can be updated */
  public function churn_buster() {
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' || $this->settings->churn_buster_enabled == true);
    $uuid = trim($this->settings->churn_buster_uuid);

    if($churn_buster_enabled && !empty($uuid)) {
      $mepr_options = MeprOptions::fetch();
      $company = $mepr_options->attr('biz_name');

      if(empty($company)) {
        $company = get_option('blogname');
      }

      MeprView::render('account/churn_buster', compact('uuid','company'));
    }
    else {
      MeprUtils::exit_with_status(404);
    }
  }

  // Originally I thought these should be associated with
  // our membership objects but now I realize they should be
  // associated with our subscription objects
  public function stripe_plan($sub, $is_new = false) {
    $mepr_options = MeprOptions::fetch();
    $prd = $sub->product();

    try {
      if($is_new)
        $plan_id = $this->create_new_plan_id($sub);
      else
        $plan_id = $this->get_plan_id($sub);

      $stripe_plan = $this->send_stripe_request( "plans/{$plan_id}", array(), 'get' );
    }
    catch( Exception $e ) {
      // The call resulted in an error ... meaning that
      // there's no plan like that so let's create one
      if( $sub->period_type == 'months' )
        $interval = 'month';
      else if( $sub->period_type == 'years' )
        $interval = 'year';
      else if( $sub->period_type == 'weeks' )
        $interval = 'week';

      //Setup a new plan ID and store the meta with this subscription
      $new_plan_id = $this->create_new_plan_id($sub);

      //Handle zero decimal currencies in Stripe
      $amount = (MeprUtils::is_zero_decimal_currency())?MeprUtils::format_float(($sub->price), 0):MeprUtils::format_float(($sub->price * 100), 0);

      $args = MeprHooks::apply_filters('mepr_stripe_create_plan_args', array(
        'amount' => $amount,
        'interval' => $interval,
        'interval_count' => $sub->period,
        'name' => $prd->post_title,
        'currency' => $mepr_options->currency_code,
        'id' => $new_plan_id,
        'statement_descriptor' => substr(str_replace(array("'", '"', '<', '>', '$', '®'), '', get_option('blogname')), 0, 21) //Get rid of invalid chars
      ), $sub);

      if($sub->trial) {
        $args = array_merge(array("trial_period_days"=>$sub->trial_days), $args);
      }

      // Don't enclose this in try/catch ... we want any errors to bubble up
      $stripe_plan = $this->send_stripe_request( 'plans', $args );
    }

    return (object)$stripe_plan;
  }

  public function get_plan_id($sub) {
    $meta_plan_id = $sub->token;

    if(is_null($meta_plan_id)) {
      return $sub->id;
    }
    else {
      return $meta_plan_id;
    }
  }

  public function create_new_plan_id($sub) {
    $parse = parse_url(home_url());
    $new_plan_id = $sub->id . '-' . $parse['host'] . '-' . uniqid();
    $sub->token = $new_plan_id;
    $sub->store();
    return $new_plan_id;
  }

  public function stripe_customer($sub_id, $cc_token = null) {
    $mepr_options     = MeprOptions::fetch();
    $sub              = new MeprSubscription($sub_id);
    $user             = $sub->user();
    $stripe_customer  = null;
    $uid              = uniqid();

    $this->email_status("###{$uid} Stripe Customer (should be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer, true) . "\n", $this->settings->debug);
    if(strpos($sub->subscr_id, 'cus_') === 0) {
      $stripe_customer = (object)$this->send_stripe_request( 'customers/' . $sub->subscr_id );

      if(isset($stripe_customer->error)) {
        return false;
      }
    }
    elseif(!empty($cc_token)) {
      $stripe_args = MeprHooks::apply_filters('mepr_stripe_customer_args', array(
        'card' => $cc_token,
        'email' => $user->user_email,
        'description' => $user->get_full_name()
      ), $sub);
      $stripe_customer = (object)$this->send_stripe_request( 'customers', $stripe_args );
      $sub->subscr_id = $stripe_customer->id;
      $sub->store();
    }
    else {
      return false;
    }
    $this->email_status("###{$uid} Stripe Customer (should not be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer, true) . "\n", $this->settings->debug);

    return (object)$stripe_customer;
  }

  public function send_stripe_request( $endpoint,
                                       $args=array(),
                                       $method='post',
                                       $domain='https://api.stripe.com/v1/',
                                       $blocking=true,
                                       $idempotency_key=false ) {
    $uri = "{$domain}{$endpoint}";

    $args = MeprHooks::apply_filters('mepr_stripe_request_args', $args);

    $arg_array = array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'blocking'  => $blocking,
      'sslverify' => false, // We assume the cert on stripe is trusted
      'headers'   => array(
        'Authorization' => 'Basic ' . base64_encode("{$this->settings->secret_key}:"),
        'Stripe-Version' => '2018-01-23'
      )
    );

    if(false !== $idempotency_key) {
      $arg_array['headers']['Idempotency-Key'] = $idempotency_key;
    }

    $arg_array = MeprHooks::apply_filters('mepr_stripe_request', $arg_array);

    $uid = uniqid();
    // $this->email_status("###{$uid} Stripe Call to {$uri} API Key: {$this->settings->secret_key}\n" . MeprUtils::object_to_string($arg_array, true) . "\n", $this->settings->debug);

    $resp = wp_remote_request( $uri, $arg_array );

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if( $blocking==false )
      return true;

    if( is_wp_error( $resp ) ) {
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s' , 'memberpress'), $this->name ) );
    }
    else {
      if( null !== ( $json_res = json_decode( $resp['body'], true ) ) ) {
        //$this->email_status("###{$uid} Stripe Response from {$uri}\n" . MeprUtils::object_to_string($json_res, true) . "\n", $this->settings->debug);
        if( isset($json_res['error']) )
          throw new MeprRemoteException( "{$json_res['error']['message']} ({$json_res['error']['type']})" );
        else
          return $json_res;
      }
      else // Un-decipherable message
        throw new MeprRemoteException( sprintf( __( 'There was an issue with the credit card processor. Try again later.', 'memberpress'), $this->name ) );
    }

    return false;
  }

  /** Get the default card object from a subscription creation response */
  public function get_default_card($data) {
    $data = (object)$data; // ensure we're dealing with a stdClass object

    if(isset($data->default_source)) { // Added in version 2015-02-15 of stripe's API
      foreach($data->sources['data'] as $source) {
        if($source['id']==$data->default_source) { return $source; }
      }
    }
    else if(isset($data->default_card)) { // Added in version 2013-07-05 of stripe's API
      foreach($data->cards['data'] as $card) {
        if($card['id']==$data->default_card) { return $card; }
      }
    }
    else if(isset($data->active_card)) { // Removed in version 2013-07-05 of stripe's API
      return $data->active_card;
    }

    return false;
  }

  /** Get card object from a charge response */
  public function get_card($data) {
    // the card object is no longer returned as of 2015-02-18 ... instead it returns 'source'
    if(isset($data->source) && $data->source['object']=='card') {
      return $data->source;
    }
    elseif(isset($data->card)) {
      return $data->card;
    }
  }
}
