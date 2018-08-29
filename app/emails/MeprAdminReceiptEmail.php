<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAdminReceiptEmail extends MeprBaseOptionsAdminEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();
    $this->to = $mepr_options->admin_email_addresses;

    $this->title = __('<b>Payment Receipt</b> Notice','memberpress');
    $this->description = __('This email is sent to you when a payment comes through on your membership site', 'memberpress');
    $this->ui_order = 2;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Payment of {$payment_amount} from {$user_full_name}', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

