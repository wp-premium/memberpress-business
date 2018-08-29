<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseExclusiveRecurringGateway extends MeprBaseRealGateway {
  abstract public function display_plans_terms( $product );
  abstract public function is_one_time_payment( $plan_code );
  abstract public function subscription_attributes( $plan_code );

  public function process_payment_form( $txn ) {
    // We're ready to create the subscription
    // One time payments are handled as subscriptions
    $this->process_create_subscription( $txn );
  }

  // Nothing to do here. See process_payment_form
  public function process_payment( $transaction ) {}
}
