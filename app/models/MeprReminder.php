<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprReminder extends MeprCptModel
{
  public static $trigger_length_str   = 'mepr_trigger_length';
  public static $trigger_interval_str = 'mepr_trigger_interval';
  public static $trigger_timing_str   = 'mepr_trigger_timing';
  public static $trigger_event_str    = 'mepr_trigger_event';
  public static $emails_str           = '_mepr_emails';

  public static $nonce_str    = 'mepr_reminders_nonce';
  public static $last_run_str = 'mepr_reminders_db_cleanup_last_run';

  public static $cpt = 'mp-reminder';

  public $trigger_intervals, $trigger_timings, $trigger_events, $event_actions;

  /*** Instance Methods ***/
  public function __construct($id = null) {
    $this->load_cpt(
      $id,
      self::$cpt,
      array(
        'trigger_length' => 1,
        'trigger_interval' => 'days',
        'trigger_timing' => 'before',
        'trigger_event' => 'sub-expires',
        'emails' => array()
      )
    );

    $this->trigger_intervals = array('hours','days','weeks','months','years');
    $this->trigger_timings = array('before','after');
    $this->trigger_events = array(
      'sub-expires',
      'sub-renews',
      'cc-expires',
      'member-signup',
      'signup-abandoned'
    );

    $this->event_actions = array();
    foreach($this->trigger_events as $e) {
      foreach($this->trigger_timings as $t) {
        $this->event_actions[] = "mepr-event-{$t}-{$e}-reminder";
      }
    }
  }

  public function validate() {
    $this->validate_is_numeric($this->trigger_length, 0, null, 'trigger_length');
    $this->validate_is_in_array($this->trigger_interval, $this->trigger_intervals, 'trigger_interval');
    $this->validate_is_in_array($this->trigger_timing, $this->trigger_timings, 'trigger_timings');
    $this->validate_is_in_array($this->trigger_event, $this->trigger_events, 'trigger_events');
    $this->validate_is_array($this->emails, 'emails');
  }

  public function events() {
  }

  public function trigger_event_name() {
    switch ($this->trigger_event) {
      case 'sub-expires': return __('Subscription Expires', 'memberpress');
      case 'sub-renews':  return __('Subscription Renews', 'memberpress');
      case 'cc-expires':  return __('Credit Card Expires', 'memberpress');
      case 'member-signup':  return __('Member Signs Up', 'memberpress');
      case 'signup-abandoned': return __('Sign Up Abandoned', 'memberpress');
      default: return $this->trigger_event;
    }
  }

  public function get_trigger_interval_str() {
    return MeprUtils::period_type_name( $this->trigger_interval, $this->trigger_length );
  }

  public function store_meta() {
    global $wpdb;
    $id = $this->ID;
    $title = sprintf( __( '%d %s %s %s' , 'memberpress'),
                      $this->trigger_length,
                      strtolower($this->get_trigger_interval_str()),
                      $this->trigger_timing,
                      $this->trigger_event_name() );

    // Direct SQL so we don't issue any actions / filters
    // in WP itself that could get us in an infinite loop
    $sql = "UPDATE {$wpdb->posts} SET post_title=%s WHERE ID=%d";
    $sql = $wpdb->prepare( $sql, $title, $id );
    $wpdb->query($sql);

    update_post_meta( $id, self::$trigger_length_str,   $this->trigger_length );
    update_post_meta( $id, self::$trigger_interval_str, $this->trigger_interval );
    update_post_meta( $id, self::$trigger_timing_str,   $this->trigger_timing );
    update_post_meta( $id, self::$trigger_event_str,    $this->trigger_event );
    update_post_meta( $id, self::$emails_str,           $this->emails );
  }

  public static function all() {
    $res = get_posts( array( 'post_type' => MeprReminder::$cpt, 'posts_per_page' => -1 ) );

    $all = array();

    foreach($res as $r) {
      $all[] = new MeprReminder($r->ID);
    }

    if(empty($all)) {
      return false;
    }
    else {
      return $all;
    }
  }

  // Singularize and capitalize
  private function db_trigger_interval() {
    return strtoupper( substr( $this->trigger_interval, 0, -1 ) );
  }

  // Used for Subscription Renewals and Expiration Reminders
  public function get_next_expiring_txn() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();
    $op = ( $this->trigger_timing=='before' ? 'DATE_SUB' : 'DATE_ADD' );

    // We want to get expiring subscriptions
    $not = ( ( $this->trigger_event == 'sub-expires' ) ? ' ' : ' NOT ' );
    //$sub_or = ( ( $this->trigger_event == 'sub-expires' ) ? ' OR ' : ' AND ' );
    $sub_or = 'OR';

    $query = $wpdb->prepare(
      // Get all info about expiring / renewing transactions
      "SELECT tr.* FROM {$mepr_db->transactions} AS tr\n" .

       // Lifetimes don't expire or renew
       "WHERE tr.expires_at <> %s\n" .

         // Make sure that only transactions that are
         // complete or confirmed and in a trial get picked up
         "AND ( tr.status = %s
                OR ( tr.status = %s
                     AND ( SELECT pm_sub_trial.meta_value
                             FROM {$wpdb->postmeta} AS pm_sub_trial
                            WHERE pm_sub_trial.post_id = tr.subscription_id
                              AND pm_sub_trial.meta_key = %s ) = 1 ) )\n" .

         // Make sure we're past 1 day after transaction completed
         // This is to account for the 24 hour possible grace period
         // ... we don't want new subscriptions getting a reminder like this
         // XXX: UH, I'm pretty sure we don't need this now that we're only allowing
         //      Reminder emails on confirmed transactions that are also in a trial
         //"AND DATE_ADD( tr.created_at, INTERVAL 1 DAY ) <= NOW()\n" .

         // Determine if expiration / renewal is accurate based on the subscription
         // If sub_id is 0 then treat as expiration
         "AND{$not}( tr.subscription_id = 0 {$sub_or}
                     ( SELECT pm_sub_status.meta_value
                         FROM {$wpdb->postmeta} AS pm_sub_status
                        WHERE pm_sub_status.post_id = tr.subscription_id
                          AND pm_sub_status.meta_key = %s ) <> %s )\n" .

         // Ensure that we're in the 2 day window after the expiration / trigger
         "AND {$op}( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ) <= %s
          AND DATE_ADD(
                {$op}( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ),
                INTERVAL 2 DAY
              ) >= %s\n" .

         // Make sure that if our timing is beforehand
         // then we don't send after the expiration
         ( $this->trigger_timing=='before' ? $wpdb->prepare("AND tr.expires_at >= %s\n", MeprUtils::mysql_now()) : '' ) .

         // Let's make sure the reminder event hasn't already fired ...
         // This will ensure that we don't send a second reminder
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=tr.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%d
                 LIMIT 1 ) IS NULL\n" .

         // Let's make sure we're not sending expire / renew reminders
         // when your subscription is being upgraded or downgraded
         "AND ( SELECT ev2.id
                  FROM {$mepr_db->events} AS ev2
                 WHERE ev2.evt_id=tr.id
                   AND ev2.evt_id_type='transactions'
                   AND ev2.event='subscription-changed'
                 LIMIT 1 ) IS NULL\n" .

       // We're just getting one of these at a time ... we need the oldest one first
       "ORDER BY tr.expires_at
        LIMIT 1\n",

      MeprUtils::mysql_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprSubscription::$trial_str,
      MeprSubscription::$status_str,
      MeprSubscription::$active_str,
      MeprUtils::mysql_now(),
      MeprUtils::mysql_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    $res = $wpdb->get_row($query);
    return $res;
  }

  public function get_next_member_signup() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    // Sorry, don't want to incur any temporal paradoxes here
    if( $this->trigger_timing==='before' ) { return false; }

    // Find transactions where
    // status = complete or confirmed
    // no other complete & unexpired txns for this user
    $query = $wpdb->prepare(
      // Just select the actual transaction id
      "SELECT txn.id FROM {$mepr_db->transactions} AS txn " .

       // Make sure this thing is complete or confirmed
       "WHERE txn.status IN (%s,%s) " .

         // Ensure we grab transactions that are after the trigger period
         "AND DATE_ADD(
                txn.created_at,
                INTERVAL {$this->trigger_length} {$unit}
              ) <= %s " .

         // Give it a 2 day buffer period so we don't send for really old transactions
         "AND DATE_ADD(
                DATE_ADD(
                  txn.created_at,
                  INTERVAL {$this->trigger_length} {$unit}
                ),
                INTERVAL 2 DAY
              ) >= %s " .

         // Make sure this is the *first* complete transaction
         "AND ( SELECT txn2.id
                  FROM {$mepr_db->transactions} AS txn2
                 WHERE txn2.user_id = txn.user_id
                   AND txn2.status IN (%s,%s)
                   AND txn2.created_at < txn.created_at
                 LIMIT 1
              ) IS NULL " .

         // Don't send this twice yo ... for this user
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=txn.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%s
                 LIMIT 1
              ) IS NULL " .

       // Select the oldest transaction
       "ORDER BY txn.created_at ASC LIMIT 1",

      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprUtils::mysql_now(),
      MeprUtils::mysql_now(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    $res = $wpdb->get_var($query);
    return $res;
  }

  public function get_next_abandoned_signup() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    // Sorry, don't want to incur any temporal paradoxes here
    if( $this->trigger_timing==='before' ) { return false; }

    // Find transactions where
    // status = pending
    // no other complete & unexpired membership for this user
    $query = $wpdb->prepare(

      // Just grab the transaction id
      "SELECT txn.id FROM {$mepr_db->transactions} AS txn " .

       // Ensure that we only select 'pending' transactions
       "WHERE txn.status=%s " .

         // Make sure the alotted time has passed
         // before allowing to be selected
         "AND DATE_ADD(
                txn.created_at,
                INTERVAL {$this->trigger_length} {$unit}
              ) <= %s " .

         // Add in the 2 day buffer period
         "AND DATE_ADD(
                DATE_ADD(
                  txn.created_at,
                  INTERVAL {$this->trigger_length} {$unit}
                ),
                INTERVAL 2 DAY
              ) >= %s " .

         // Ensure that there's no completed or confirmed transaction that
         // was created after the pending one ... if they came back and
         // completed their transaction then it's not abandoned ... hahaha
         "AND ( SELECT txn2.id
                  FROM {$mepr_db->transactions} AS txn2
                 WHERE txn2.user_id = txn.user_id
                   AND txn2.product_id = txn.product_id
                   AND txn2.status IN (%s,%s)
                   AND txn2.created_at > txn.created_at
                 LIMIT 1
              ) IS NULL " .

         // Don't want to send this reminder twice so make sure there's no
         // reminder that has already been sent for this bro
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=txn.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%s
                 LIMIT 1
              ) IS NULL " .

       // Get the *oldest* applicable transaction
       "ORDER BY txn.created_at ASC LIMIT 1",

      MeprTransaction::$pending_str,
      MeprUtils::mysql_now(),
      MeprUtils::mysql_now(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    //echo "User Abandoned Query:\n\n";
    //echo $query . "\n\n";

    $res = $wpdb->get_var($query);
    return $res;
  }

  public function get_next_expired_cc() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();
    $op = ( $this->trigger_timing=='before' ? 'DATE_SUB' : 'DATE_ADD' );

    // We want to get expiring subscriptions
    $not = ( ( $this->trigger_event == 'sub-expires' ) ? ' ' : ' NOT ' );

    $exp_month  = $wpdb->prepare('%s', MeprSubscription::$cc_exp_month_str);
    $exp_year   = $wpdb->prepare('%s', MeprSubscription::$cc_exp_year_str);
    $status_str = $wpdb->prepare('%s', MeprSubscription::$status_str);
    $active_str = $wpdb->prepare('%s', MeprSubscription::$active_str);
    $post_type  = $wpdb->prepare('%s', MeprSubscription::$cpt);
    $evt_name   = $wpdb->prepare('%s', "{$this->trigger_timing}-{$this->trigger_event}-reminder");
    $rmd_id     = $wpdb->prepare('%s', "{$this->ID}");

    // TODO: Purely for reverse compatibility, remove after a few releases
    $old_evt_name = $wpdb->prepare('%s', "{$this->trigger_event}-reminder");

    // Expiring Transactions
    $query =
      // Just grab the sub.ID for any subscription with an expiring transaction
      "SELECT sub.ID FROM {$wpdb->posts} AS sub " .

      // Grab the exp month and exp year so we can select based on it
      "INNER JOIN {$wpdb->postmeta} AS pm_cc_exp_month
          ON pm_cc_exp_month.post_id = sub.ID
         AND pm_cc_exp_month.meta_key = {$exp_month}
       INNER JOIN {$wpdb->postmeta} AS pm_cc_exp_year
          ON pm_cc_exp_year.post_id = sub.ID
         AND pm_cc_exp_year.meta_key = {$exp_year} " .

      // Grab the status so we can select based on it
      "INNER JOIN {$wpdb->postmeta} AS pm_sub_status
          ON pm_sub_status.post_id = sub.ID
         AND pm_sub_status.meta_key = {$status_str} " .

      // Only select posts that are of the subscription type
      "WHERE sub.post_type = {$post_type} " .

        // Make sure we only send out reminders for folks with ACTIVE subscriptions
        "AND pm_sub_status.meta_value = {$active_str} " .

        // Subtract or add if the reminder is before or after
        // The concat is just to piece together the date
        // The add_date is because we actually want the first
        // day of the month *after* the expiration month
        // LPAD is just there to ensure the month is a
        // 2 digit zero padded number
        "AND {$op}(
               DATE_ADD(
                 CONCAT(
                   pm_cc_exp_year.meta_value, '-',
                   LPAD(pm_cc_exp_month.meta_value, 2, '0'),
                   '-01 00:00:00'
                 ),
                 INTERVAL 1 MONTH
               ),
               INTERVAL {$this->trigger_length} {$unit}
             ) <= %s " .

        // Basically the same thing as we're doing here but let's give it
        // an entire month period to send these reminders ... seeing as
        // there will probably be fewer of these emails and they're pretty
        // dang critical so that people's subscriptions don't lapse.
        "AND DATE_ADD(
               {$op}(
                 DATE_ADD(
                   CONCAT(
                     pm_cc_exp_year.meta_value, '-',
                     LPAD(pm_cc_exp_month.meta_value, 2, '0'),
                     '-01 00:00:00'
                   ),
                   INTERVAL 1 MONTH
                 ),
                 INTERVAL {$this->trigger_length} {$unit}
               ),
               INTERVAL 1 MONTH
             ) >= %s " .

        // check that we haven't already sent a reminder for this
        // subscription *and* specific expiration date
        "AND ( SELECT ev.id
                 FROM {$mepr_db->events} AS ev
                WHERE ev.evt_id=sub.ID
                  AND ev.evt_id_type='subscriptions'
                  AND ev.event={$evt_name}
                  AND ev.args=CONCAT({$rmd_id}, '|', pm_cc_exp_month.meta_value, '|', pm_cc_exp_year.meta_value )
                LIMIT 1
             ) IS NULL " .

     // Get the *oldest* valid cc expiration first
     "ORDER BY CAST(pm_cc_exp_year.meta_value AS UNSIGNED) ASC,
               CAST(pm_cc_exp_month.meta_value AS UNSIGNED) ASC
      LIMIT 1";

    //echo "{$query}\n";

    $query = $wpdb->prepare($query, MeprUtils::mysql_now(), MeprUtils::mysql_now());

    $res = $wpdb->get_var($query);
    return $res;
  }

  // Used for drips and expirations
  //public function get_next_drip( $rule, $timing, $length, $interval, $event='sub-expires' ) {
  //  global $wpdb;
  //
  //  $mepr_db = new MeprDb();
  //
  //  if($reminder->trigger_interval=='days')
  //    $unit = 'DAY';
  //  else if($reminder->trigger_interval=='weeks')
  //    $unit = 'WEEK';
  //  else if($reminder->trigger_interval=='months')
  //    $unit = 'MONTH';
  //  else if($reminder->trigger_interval=='years')
  //    $unit = 'YEAR';
  //
  //  $op = ($reminder->trigger_timing='before')?'DATE_SUB':'DATE_ADD';
  //
  //  // We want to get expiring subscriptions
  //  $not = $expiring ? ' ' : ' NOT ';

  //  $query = "SELECT (SELECT user) as user, " .
  //                  "(SELECT products) as products, " .
  //                  "(Calculate date) as date " .
  //             "FROM {$wpdb->posts} AS p " .
  //            "WHERE p.post_status='publish' " .
  //              "AND p.post_type=%s " .
  //              "AND p.ID=%d";

  //  // Rule:

  //  // drip_enabled
  //  // drip_sequential
  //  // drip_amount
  //  // drip_unit
  //  // drip_after
  //  // drip_after_fixed

  //  // User registers
  //  // Fixed date
  //  // Transaction for specific membership

  //  // Expiring Transactions

  //  $res = $wpdb->get_row($query);
  //
  //  return $res;
  //}
} //End class

