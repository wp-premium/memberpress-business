<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAdminResumedSubEmail extends MeprBaseOptionsAdminEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();
    $this->to = $mepr_options->admin_email_addresses;

    $this->title = __('<b>Resumed Subscription</b> Notice','memberpress');
    $this->description = __('This email is sent to you when a subscription is resumed.', 'memberpress');
    $this->ui_order = 7;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Subscription {$subscr_num} Resumed', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprSubscriptionsHelper::get_email_vars();
  }
}

