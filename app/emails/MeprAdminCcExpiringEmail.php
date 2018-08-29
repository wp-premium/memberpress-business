<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAdminCcExpiringEmail extends MeprBaseOptionsAdminEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();
    $this->to = $mepr_options->admin_email_addresses;

    $this->title = __('<b>Credit Card Expiring</b> Notice','memberpress');
    $this->description = __('This email is sent to you when a member\'s credit card is expiring', 'memberpress');
    $this->ui_order = 10;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Credit Card Expiring For {$subscr_num}', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprSubscriptionsHelper::get_email_vars();
  }
}

