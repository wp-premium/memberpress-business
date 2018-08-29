<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserReceiptEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Payment Receipt</b> Notice','memberpress');
    $this->description = __('This email is sent to a user when payment completes for one of your memberships in her behalf.', 'memberpress');
    $this->ui_order = 1;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Payment Receipt', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

