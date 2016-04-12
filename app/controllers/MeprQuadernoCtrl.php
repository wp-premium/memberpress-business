<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprQuadernoCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_filter('mepr_stripe_payment_args', array($this, 'stripe_payment_args'), 10, 2);
    add_filter('mepr_stripe_subscription_args', array($this, 'stripe_subscription_args'), 10, 3);
    add_filter('mepr_stripe_customer_args', array($this, 'stripe_customer_args'), 10, 2);
    add_filter('mepr_paypal_std_custom_payment_vars', array($this, 'paypal_std_custom_payment_vars'), 10, 2);
  }

  public function stripe_payment_args($args, $txn) {
    $usr = $txn->user();

    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    $args['metadata']['tax_rate']       = $txn->tax_rate;
    $args['metadata']['tax_name']       = $txn->tax_desc;
    $args['metadata']['vat_number']     = get_user_meta($usr->ID, 'mepr_vat_number', true);
    $args['metadata']['invoice_email']  = $usr->user_email;

    return $args;
  }

  public function stripe_subscription_args($args, $txn, $sub) {
    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    $args['metadata']['tax_rate'] = $txn->tax_rate;
    $args['metadata']['tax_name'] = $txn->tax_desc;

    return $args;
  }

  public function stripe_customer_args($args, $sub) {
    $usr = $sub->user();

    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    $args['metadata']['vat_number'] = get_user_meta($usr->ID, 'mepr_vat_number', true);

    return $args;
  }

  public function paypal_std_custom_payment_vars($custom, $txn) {
    $usr = $txn->user();

    if(!is_array($custom)) { $custom = array(); }

    $custom['vat_number']   = get_user_meta($usr->ID, 'mepr_vat_number', true);
    $custom['tax']['rate']  = $txn->tax_rate; //Added for Javier
    $custom['tax']['name']  = $txn->tax_desc; //Added for Javier

    return $custom;
  }
}
