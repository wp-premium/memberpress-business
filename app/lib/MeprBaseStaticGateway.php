<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Lays down the interface for Gateways in MemberPress **/
class MeprBaseStaticGateway extends MeprBaseGateway {
  /** Used in the view to identify the gateway */
  public function __construct($id,$label,$name)
  {
    $this->id    = $id;
    $this->name  = $name;
    $this->label = $label;
    $this->use_label = true;
    $this->icon = MEPR_IMAGES_URL . '/checkout/offline.png';
    $this->use_icon = true;
    $this->desc = '';
    $this->use_desc = true;

    $this->set_defaults();

    $this->capabilities = array(
      //'process-payments',
      //'create-subscriptions',
      //'process-refunds',
      //'cancel-subscriptions',
      //'update-subscriptions',
      //'suspend-subscriptions',
      //'send-cc-expirations'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array();
  }

  public function load($settings)
  {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  protected function set_defaults() {
    if(!isset($this->settings)) { $this->settings = (object)array(); }
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($transaction) { }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment() { }

  /** This method should be used by the class to push a request to to the gateway.
    */
  public function process_refund(MeprTransaction $txn) { }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() { }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() { }

  /** Used to record a declined payment. */
  public function record_payment_failure() { }

  /** Used for processing and recording one-off subscription trial payments */
  public function process_trial_payment($transaction) { }
  public function record_trial_payment($transaction) { }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($transaction) { }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() { }

  public function process_update_subscription($subscription_id) { }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() { }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($subscription_id) { }

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() { }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($subscription_id) { }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() { }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($subscription_id) { }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() { }

  /** Gets called when the signup form is posted used for running any payment
    * method specific actions when processing the customer signup form.
    */
  public function process_signup_form($transaction) { }

  /** Gets called on the 'init' action after the signup form is submitted. If
    * we're using an offsite payment solution like PayPal then this method
    * will just redirect to it.
    */
  public function display_payment_page($transaction) { }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() { }

  /** This spits out html for the payment form on the registration / payment
    * page for the user to fill out for payment.
    */
  public function display_payment_form($amount, $user, $product_id, $transaction_id) { }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) { }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() { }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) { }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($subscription_id, $errors=array(), $message="") { }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) { }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form($subscription_id) { }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() { return false; }

  /** Returns boolean ... whether or not we should be forcing ssl */
  public function force_ssl() { }
}
