<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUserProductWelcomeEmail extends MeprBaseProductEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();

    $this->title = __('Membership-Specific Welcome Email to User','memberpress');
    $this->description = __('This email is sent when this membership is purchased.', 'memberpress');
    $this->ui_order = 1;

    $enabled = false;
    $use_template = $this->show_form = true;
    $subject = __('** Thanks for Purchasing {$product_name}', 'memberpress');
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = MeprTransactionsHelper::get_email_vars();
  }
}

