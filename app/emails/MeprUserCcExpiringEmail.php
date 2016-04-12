<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserCcExpiringEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Credit Card Expiring</b> Notice','memberpress');
    $this->description = __('This email is sent to a member when their credit card is expiring', 'memberpress');
    $this->ui_order = 9;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Your Credit Card is Expiring', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprSubscriptionsHelper::get_email_vars();
  }
}

