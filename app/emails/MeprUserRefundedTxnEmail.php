<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserRefundedTxnEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Refunded Transaction</b> Notice','memberpress');
    $this->description = __('This email is sent to the user when a transaction is refunded.', 'memberpress');
    $this->ui_order = 7;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Your Transaction Was Refunded', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

