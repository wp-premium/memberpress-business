<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprRemindersCtrl extends MeprCptCtrl {
  public function load_hooks() {
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('save_post', array($this, 'save_postdata'));

    $disable_reminder_crons = get_option('mepr_disable_reminder_crons');
    if(!$disable_reminder_crons) {
      $r = new MeprReminder();
      foreach($r->event_actions as $e) {
        add_action($e, array($this, 'send_reminders'));
      }

      // Set up cron schedules
      add_filter('cron_schedules', array($this, 'intervals'));
      add_action('mepr_reminders_worker', array($this, 'worker'));
      add_action('wp', array($this, 'schedule_reminders'));
    }
    else {
      $this->unschedule_reminders();
    }

    // Clean up crons and possibly other stuff when a reminder is deleted or trashed
    add_action('before_delete_post', array($this, 'delete'));
    add_action('wp_trash_post', array($this, 'delete'));

    // Add some cols
    add_action('manage_posts_custom_column', array( $this, 'custom_columns'), 10, 2);
    add_filter('manage_edit-mp-reminder_columns', array( $this, 'columns'));
  }

  public function schedule_reminder($id) {
    //Stop zombie cron jobs in their tracks here
    $reminder = $this->get_valid_reminder($id);

    if($reminder===false) {
      $this->unschedule_reminder($id);
      return;
    }

    $args = array($id);

    if(!wp_next_scheduled('mepr_reminders_worker', $args)) {
       wp_schedule_event(
         time(),
         'mepr_reminders_worker_interval',
         'mepr_reminders_worker',
         $args
       );
    }
  }

  public function schedule_reminders() {
    $reminders = MeprCptModel::all('MeprReminder');

    if(!empty($reminders)) {
      foreach($reminders as $r) {
        $vr = $this->get_valid_reminder($r->ID);

        if($vr!==false) {
          $this->schedule_reminder($r->ID);
        }
        else {
          $this->unschedule_reminder($r->ID);
        }
      }
    }
  }

  public function unschedule_reminder($id) {
    $args = array($id);
    $timestamp = wp_next_scheduled('mepr_reminders_worker', $args);
    wp_unschedule_event($timestamp, 'mepr_reminders_worker', $args);
  }

  public function unschedule_reminders() {
    $reminders = MeprCptModel::all('MeprReminder');

    if(!empty($reminders)) {
      foreach($reminders as $r) {
        $this->unschedule_reminder($r->ID);
      }
    }
  }

  public function columns($columns) {
    $columns = array(
      'cb'                => '<input type="checkbox" />',
      'title'             => __('Membership Title', 'memberpress'),
      'send_to_admin'     => __('Send Notice to Admin', 'memberpress'),
      'send_to_member'    => __('Send Reminder to Member', 'memberpress'),
      'reminder_products' => __('Memberships', 'memberpress')
    );

    return $columns;
  }

  public function custom_columns($column, $post_id) {
    $reminder = $this->get_valid_reminder($post_id);

    if($reminder!==false) {
      switch( $reminder->trigger_event ) {
        case 'sub-expires':
          $uclass = 'MeprUserSubExpiresReminderEmail';
          $aclass = 'MeprAdminSubExpiresReminderEmail';
          break;
        case 'sub-renews':
          $uclass = 'MeprUserSubRenewsReminderEmail';
          $aclass = 'MeprAdminSubRenewsReminderEmail';
          break;
        case 'signup-abandoned':
          $uclass = 'MeprUserSignupAbandonedReminderEmail';
          $aclass = 'MeprAdminSignupAbandonedReminderEmail';
          break;
        case 'member-signup':
          $uclass = 'MeprUserMemberSignupReminderEmail';
          $aclass = 'MeprAdminMemberSignupReminderEmail';
          break;
        case 'cc-expires':
          $uclass = 'MeprUserCcExpiresReminderEmail';
          $aclass = 'MeprAdminCcExpiresReminderEmail';
          break;
        default:
          echo '';
          return;
      }

      // TODO: Yah, not pretty but works ... change at some point
      $cval = '<span style="color: %s; font-size: 120%%;"><strong>%s</strong></span>';

      if("send_to_admin" == $column) {
        (int)$reminder->emails[$aclass]['enabled'] > 0 ? printf( $cval, 'limegreen', '✔︎' ) : printf( $cval, 'red', '✖︎' );
      }
      elseif("send_to_member" == $column) {
        (int)$reminder->emails[$uclass]['enabled'] > 0 ? printf( $cval, 'limegreen', '✔︎' ) : printf( $cval, 'red', '✖︎' );
      }
      elseif("reminder_products" == $column) {
        echo implode(', ', $reminder->get_formatted_products());
      }
    }
  }

  public function register_post_type() {
    $this->cpt = (object)array(
      'slug' => MeprReminder::$cpt,
      'config' => array(
        'labels' => array(
          'name' => __('Reminders', 'memberpress'),
          'singular_name' => __('Reminder', 'memberpress'),
          'add_new_item' => __('Add New Reminder', 'memberpress'),
          'edit_item' => __('Edit Reminder', 'memberpress'),
          'new_item' => __('New Reminder', 'memberpress'),
          'view_item' => __('View Reminder', 'memberpress'),
          'search_items' => __('Search Reminders', 'memberpress'),
          'not_found' => __('No Reminders found', 'memberpress'),
          'not_found_in_trash' => __('No Reminders found in Trash', 'memberpress'),
          'parent_item_colon' => __('Parent Reminder:', 'memberpress')
        ),
        'public' => false,
        'show_ui' => true, //MeprUpdateCtrl::is_activated(),
        'show_in_menu' => 'memberpress',
        'capability_type' => 'post',
        'hierarchical' => false,
        'register_meta_box_cb' => array( $this, 'add_meta_boxes' ),
        'rewrite' => false,
        'supports' => array('none')
      )
    );
    register_post_type( $this->cpt->slug, $this->cpt->config );
  }

  public function add_meta_boxes() {
    add_meta_box( "mp-reminder-trigger", __("Trigger", "memberpress"),
                  array( $this, 'trigger_meta_box' ), MeprReminder::$cpt, "normal" );
    add_meta_box( "mp-reminder-emails", __("Emails", "memberpress"),
                  array( $this, 'emails_meta_box' ), MeprReminder::$cpt, "normal" );
  }

  public function enqueue_scripts($hook) {
    global $current_screen;

    $wp_scripts = new WP_Scripts();
    $ui = $wp_scripts->query('jquery-ui-core');
    $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

    if($current_screen->post_type == MeprReminder::$cpt)
    {
      wp_enqueue_style('mepr-jquery-ui-smoothness', $url);
      wp_dequeue_script('autosave'); //Disable auto-saving
      wp_enqueue_style('mepr-emails-css', MEPR_CSS_URL.'/admin-emails.css', array(), MEPR_VERSION);
      wp_enqueue_script('mepr-emails-js', MEPR_JS_URL.'/admin_emails.js', array('jquery'), MEPR_VERSION);
      wp_enqueue_style('mepr-reminders-css', MEPR_CSS_URL.'/admin-reminders.css', array('mepr-emails-css'), MEPR_VERSION);
      wp_enqueue_script('mepr-reminders-js', MEPR_JS_URL.'/admin_reminders.js', array('jquery','jquery-ui-spinner','mepr-emails-js'), MEPR_VERSION);
    }
  }

  public function trigger_meta_box() {
    global $post_id;

    $reminder = new MeprReminder($post_id);
    $nonce = wp_create_nonce( md5(MeprReminder::$nonce_str.wp_salt()) );

    MeprView::render("/admin/reminders/trigger", get_defined_vars());
  }

  public function emails_meta_box() {
    global $post_id;

    $reminder = new MeprReminder($post_id);

    MeprView::render("/admin/reminders/emails", get_defined_vars());
  }

  public function save_postdata($post_id) {
    $post = get_post($post_id);

    if(!wp_verify_nonce( (isset($_POST[MeprReminder::$nonce_str]))?$_POST[MeprReminder::$nonce_str]:'',
                         md5(MeprReminder::$nonce_str.wp_salt()) )) {
      return $post_id; //Nonce prevents meta data from being wiped on move to trash
    }

    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }
    if(defined('DOING_AJAX')) { return; }

    if(!empty($post) && $post->post_type == MeprReminder::$cpt) {
      $reminder = new MeprReminder($post_id);

      $reminder->trigger_length   = sanitize_text_field($_POST[MeprReminder::$trigger_length_str]);
      $reminder->trigger_interval = sanitize_text_field($_POST[MeprReminder::$trigger_interval_str]);
      $reminder->trigger_timing   = sanitize_text_field($_POST[MeprReminder::$trigger_timing_str]);
      $reminder->trigger_event    = sanitize_text_field($_POST[MeprReminder::$trigger_event_str]);
      $reminder->filter_products  = false;
      $reminder->products         = array();

      //Override filter by products vars
      if(isset($_POST[MeprReminder::$filter_products_str]) && !empty($_POST[MeprReminder::$products_str])) {
        $reminder->filter_products  = true;
        $reminder->products         = array_map('sanitize_text_field', $_POST[MeprReminder::$products_str]);
      }

      // Notification Settings
      $emails = array();
      foreach( $_POST[MeprReminder::$emails_str] as $email => $vals ) {
        $emails[$email] = array( 'enabled'      => isset( $vals['enabled'] ),
                                 'use_template' => isset( $vals['use_template'] ),
                                 'subject'      => sanitize_text_field( wp_unslash($vals['subject']) ),
                                 'body'         => wp_kses_post( wp_unslash($vals['body']) )
                          );
      }

      $reminder->emails = $emails;

      // Don't quite need this yet
      //$reminder = $this->validate($reminder);

      $reminder->store_meta(); // only storing metadata here

      MeprHooks::do_action( 'mepr-reminder-save-meta', $reminder );
    }
  }

  /** CRON SPECIFIC METHODS **/
  public function intervals( $schedules ) {
    $schedules[ "mepr_reminders_worker_interval" ] = array(
       'interval' => MeprUtils::minutes(15),
       'display' => __( 'MemberPress Reminders Worker' , 'memberpress')
    );

    return $schedules;
  }

  public function get_valid_reminder($id) {
    // if the remider_id is empty then forget it
    if(empty($id)) { return false; }

    $post = get_post($id);

    // Post not found? fail
    if(empty($post)) { return false; }

    // not the right post type? fail
    if($post->post_type!==MeprReminder::$cpt) { return false; }

    // not a published post? fail
    if($post->post_status!=='publish') { return false; }

    $reminder = new MeprReminder($id);

    // ID is empty? fail
    if(empty($reminder->ID)) { return false; }

    return $reminder;
  }

  public function worker($reminder_id) {
    $reminder = $this->get_valid_reminder($reminder_id);

    if($reminder !== false) {
      @set_time_limit(0); // unlimited run time
      $run_limit = MeprUtils::minutes(10); // limit to 10 minutes

      // Event name will be the same no matter what we're doing here
      $event = "{$reminder->trigger_timing}-{$reminder->trigger_event}-reminder";

      if( in_array( $reminder->trigger_event, array( 'sub-expires', 'sub-renews' ) ) ) {
        while( $this->run_time() < $run_limit &&
               ($txn = $reminder->get_next_expiring_txn()) ) {
          $txn = new MeprTransaction($txn->id); // we need the actual model

          // We just catch the hooks from these events
          MeprEvent::record($event, $txn, $reminder_id);
        }
      }
      else if( $reminder->trigger_event === 'member-signup' ) {
        while( $this->run_time() < $run_limit &&
               ($txn_id = $reminder->get_next_member_signup()) &&
               !empty($txn_id) ) {
          $txn = new MeprTransaction($txn_id);

          // We just catch the hooks from these events
          MeprEvent::record($event, $txn, $reminder_id);
        }
      }
      else if( $reminder->trigger_event === 'signup-abandoned' ) {
        while( $this->run_time() < $run_limit &&
               ($txn_id = $reminder->get_next_abandoned_signup()) &&
               !empty($txn_id) ) {
          $txn = new MeprTransaction($txn_id);

          // We just catch the hooks from these events
          MeprEvent::record($event, $txn, $reminder_id);
        }
      }
      else if( $reminder->trigger_event === 'cc-expires' ) {
        while( $this->run_time() < $run_limit &&
               ($sub_id = $reminder->get_next_expired_cc()) &&
               !empty($sub_id) ) {
          $sub = new MeprSubscription($sub_id);
          $args = "{$reminder_id}|{$sub->cc_exp_month}|{$sub->cc_exp_year}";

          // We just catch the hooks from these events
          MeprEvent::record($event, $sub, $args);
        }
      }
    }
    else {
      $this->unschedule_reminder($reminder_id);
    }
  }

  private function run_time() {
    static $start_time;

    if(!isset($start_time)) {
      $start_time = time();
    }

    return ( time() - $start_time );
  }

  private function send_emails($usr, $uclass, $aclass, $params, $args) {
    try {
      $uemail = MeprEmailFactory::fetch( $uclass, 'MeprBaseReminderEmail', $args );
      $uemail->to = $usr->formatted_email();
      $uemail->send_if_enabled($params);

      $aemail = MeprEmailFactory::fetch( $aclass, 'MeprBaseReminderEmail', $args );
      $aemail->send_if_enabled($params);
    }
    catch( Exception $e ) {
      // Fail silently for now
    }
  }

  public function send_reminders($event) {
    //Now that we support renewals on one-time purchases -- we need to make sure they don't get reminded of expirations
    //if they have already renewed their one-time subscription again before the expiring sub reminder is sent out
    $disable_email = false; //Do not send the emails if this gets set to true

    if($event->evt_id_type == 'transactions' && ($txn = new MeprTransaction($event->evt_id))) {
      $usr      = $txn->user();
      $prd      = new MeprProduct($txn->product_id);
      $reminder = $this->get_valid_reminder($event->args);

      if($reminder===false) { return; } // fail silently if reminder is invalid

      $params = array_merge(MeprRemindersHelper::get_email_params($reminder), MeprTransactionsHelper::get_email_params($txn));

      switch($reminder->trigger_event) {
        case 'sub-expires':
          //Don't send a reminder if the user has already renewed either a one-time or an offline subscription
          if($reminder->trigger_timing == 'before') { //Handle when the reminder should go out before
            $txn_count = count($usr->transactions_for_product($txn->product_id, false, true));

            //txn_count > 1 works well for both renewals and offline subs actually because transactions_for_product
            //should only ever return a count of currently active (payment type) transactions and no expired transactions
            if($txn_count > 1) {
              $disable_email = true;
            }
          }
          else { //Handle when the reminder should go out after
            //Don't send to folks if they have an active txn on this subscription already yo
            if(in_array($txn->product_id, $usr->active_product_subscriptions('ids'), false)) {
              $disable_email = true;
            }
          }

          $uclass = 'MeprUserSubExpiresReminderEmail';
          $aclass = 'MeprAdminSubExpiresReminderEmail';
          break;
        case 'sub-renews':
          $uclass = 'MeprUserSubRenewsReminderEmail';
          $aclass = 'MeprAdminSubRenewsReminderEmail';
          break;
        case 'signup-abandoned':
          //Make sure the user is not active on another membership
          $active_subs = $usr->active_product_subscriptions('ids');

          if(!empty($active_subs)) {
            $disable_email = true;
          }

          $uclass = 'MeprUserSignupAbandonedReminderEmail';
          $aclass = 'MeprAdminSignupAbandonedReminderEmail';
          break;
        case 'member-signup':
          $uclass = 'MeprUserMemberSignupReminderEmail';
          $aclass = 'MeprAdminMemberSignupReminderEmail';
          break;
        default:
          $uclass=$aclass='';
      }

      $args = array(array('reminder_id'=>$event->args));

      $disable_email = MeprHooks::apply_filters("mepr-{$reminder->trigger_event}-reminder-disable", $disable_email, $reminder, $usr, $prd);
      if(!$disable_email) {
        $this->send_emails($usr, $uclass, $aclass, $params, $args);
      }
    }
    else if( $event->evt_id_type == 'subscriptions' && ($sub = new MeprSubscription($event->evt_id)) ) {
      $usr = $sub->user();
      $reminder = $this->get_valid_reminder($event->args);

      if($reminder===false) { return; } // fail silently if reminder is invalid

      $params = array_merge( MeprRemindersHelper::get_email_params($reminder),
                             MeprSubscriptionsHelper::get_email_params($sub) );

      // Only 1 type of subscription reminder for now
      $uclass = 'MeprUserCcExpiresReminderEmail';
      $aclass = 'MeprAdminCcExpiresReminderEmail';

      $args = array(array('reminder_id'=>$reminder->ID));

      $this->send_emails( $usr, $uclass, $aclass, $params, $args );
    }
  }

  public function delete( $id ) {
    global $post_type;
    if ( $post_type != MeprReminder::$cpt ) return;
    $this->unschedule_reminder($id);
  }

} //End class
