<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAdminSignupEmail extends MeprBaseOptionsAdminEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();
    $this->to = $mepr_options->admin_email_addresses;

    $this->title = __('<b>New Signup</b> Notice','memberpress');
    $this->description = __('This email is sent to you when a user registers for your membership site and their first transaction completes.', 'memberpress');
    $this->ui_order = 0;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** New Signup: {$username}', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

