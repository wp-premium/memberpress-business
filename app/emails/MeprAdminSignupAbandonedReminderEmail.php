<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAdminSignupAbandonedReminderEmail extends MeprBaseReminderEmail {
  /** Set the default enabled, title, subject & body */
  public function set_defaults($args=array()) {
    $mepr_options = MeprOptions::fetch();
    $this->to = $mepr_options->admin_email_addresses;

    $this->title = __('Signup Abandoned Reminder Email to Admin','memberpress');
    $this->description = __('This email is sent to the admin when triggered for a user.', 'memberpress');
    $this->ui_order = 1;

    $enabled = $use_template = $this->show_form = true;
    $subject = sprintf( __('** %1$s Reminder Sent to %2$s', 'memberpress'), '{$reminder_name}', '{$username}' );
    $body = $this->body_partial();

    $this->defaults = compact( 'enabled', 'subject', 'body', 'use_template' );
    $this->variables = array_unique(
      array_merge(
        MeprRemindersHelper::get_email_vars(),
        MeprTransactionsHelper::get_email_vars()
      )
    );

    $this->test_vars = array(
       'reminder_id'               => 28,
       'reminder_trigger_length'   => 2,
       'reminder_trigger_interval' => 'days',
       'reminder_trigger_timing'   => 'after',
       'reminder_trigger_event'    => 'signup-abandoned',
       'reminder_name'             => __('Sign Up Abandoned', 'memberpress'),
       'reminder_description'      => __('Sign Up Abandoned 2 days ago', 'memberpress'),
    );
  }
}

