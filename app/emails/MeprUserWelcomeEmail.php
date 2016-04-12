<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserWelcomeEmail extends MeprBaseOptionsUserEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $this->title = __('<b>Welcome Email</b>','memberpress');
    $this->description = __('This email is sent welcome a new user when she initially signs up for your membership site with a completed purchase.', 'memberpress');
    $this->ui_order = 0;

    $enabled = $use_template = $this->show_form = true;
    $subject = __('** Welcome to {$blog_name}', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

