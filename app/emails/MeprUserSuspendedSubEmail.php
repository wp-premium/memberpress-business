<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserSuspendedSubEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Paused Subscription</b> Notice','memberpress');
    $this->description = __('This email is sent to the user when one of their subscriptions is paused.', 'memberpress');
    $this->ui_order = 5;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** You\'ve paused your subscription', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprSubscriptionsHelper::get_email_vars();
  }
}

