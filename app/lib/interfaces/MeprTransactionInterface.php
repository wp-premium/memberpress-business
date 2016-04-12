<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

interface MeprTransactionInterface {
  public function user();
  public function product();
  public function coupon();
  public function payment_method();
  public function is_expired($offset = 0);
  public function maybe_cancel_old_sub();
  public function set_gross($gross);
  public function set_subtotal($subtotal);
  public function apply_tax($subtotal, $num_decimals=2);
}

