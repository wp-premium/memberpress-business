<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserResumedSubEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Resumed Subscription</b> Notice','memberpress');
    $this->description = __('This email is sent to the user when they resume a subscription.', 'memberpress');
    $this->ui_order = 6;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Your subscription has resumed', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprSubscriptionsHelper::get_email_vars();
  }
}

