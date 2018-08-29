<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUsageCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // Keeping members up to date

    $disable_senddata = get_option('mepr_disable_senddata');
    if(!$disable_senddata) {
      add_filter( 'cron_schedules', array($this,'intervals') );
      add_action( 'mepr_snapshot_worker', array($this,'snapshot') );

      if(!($snapshot_timestamp = wp_next_scheduled('mepr_snapshot_worker'))) {
         wp_schedule_event( time(), 'mepr_snapshot_interval', 'mepr_snapshot_worker' );
      }
    }

    add_action('mepr_display_general_options', array($this,'display_options'), 99);
    add_action('mepr-process-options', array($this,'save_options'));

    // Set disable senddata via AJAX for our popup
    add_action('wp_ajax_mepr_disable_senddata', array($this, 'ajax_enable_or_disable_senddata'));
    add_action('wp_ajax_mepr_enable_senddata', array($this, 'ajax_enable_or_disable_senddata'));
  }

  public function intervals( $schedules ) {
    $schedules['mepr_snapshot_interval'] = array(
      'interval' => MeprUtils::weeks(1),
      'display' => __('MemberPress Snapshot Interval', 'memberpress'),
    );

    return $schedules;
  }

  public function snapshot() {
    $disable_senddata = get_option('mepr_disable_senddata');
    if($disable_senddata) {
      return;
    }

    // This is here because we've learned through sad experience that we can't fully
    // rely on WP-CRON to wait for an entire week so we check here to ensure we're ready.
    $already_sent = MeprExpiringOption::get('sent_snapshot');
    if(!empty($already_sent)) {
      MeprUtils::debug_log(__('Your site is attempting to send too many snapshots, we\'ll put an end to that.', 'memberpress'));
      return;
    }

    $ep =
      "aHR0cHM6Ly9tZW1iZXJwcmVz".
      "cy1hbmFseXRpY3MuaGVyb2t1".
      "YXBwLmNvbS9zbmFwc2hvdA==";

    $usage = new MeprUsage();
    $body = json_encode($usage->snapshot());

    $headers = array(
      'Accept'         => 'application/json',
      'Content-Type'   => 'application/json',
      'Content-Length' => strlen($body)
    );

    // Setup variable for wp_remote_request
    $post = array(
      'method'    => 'POST',
      'headers'   => $headers,
      'body'      => $body
    );

    $resp = wp_remote_request(base64_decode($ep), $post);

    // 6 days so we don't accidentally miss the weekly cron
    MeprExpiringOption::set('sent_snapshot',1,MeprUtils::days(6));
  }

  public function display_options() {
    $disable_senddata = get_option('mepr_disable_senddata');
    MeprView::render('admin/usage/option', compact('disable_senddata'));
  }

  public function save_options($params) {
    update_option('mepr_disable_senddata',isset($params['mepr_disable_senddata']));
  }

  public function ajax_enable_or_disable_senddata() {
    if(!MeprUtils::is_mepr_admin() || !MeprUtils::is_post_request()) {
      MeprUtils::exit_with_status(403,__('Forbidden', 'memberpress'));
    }

    update_option('mepr_disable_senddata',($_POST['action']=='mepr_disable_senddata'));

    $message = __('Disable Send Data option was updated successfully', 'memberpress');
    MeprUtils::exit_with_status(200,json_encode(compact('message')));
  }

} // End class
