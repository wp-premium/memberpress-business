<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprJobs
{
  public $config;

  public function __construct() {
    // Setup job configuration
    $this->config = MeprHooks::apply_filters('mepr-jobs-config', (object)array(
      'status'  => (object)array(
        'pending'  => 'pending',
        'complete' => 'complete',
        'failed'   => 'failed',
        'working'  => 'working'
      ),
      'worker' => (object)array(
        'interval' => MeprUtils::minutes(1)
      ),
      'cleanup' => (object)array(
        'num_retries' => 5, // "num_retries" before transactions fail
        'interval'    => MeprUtils::hours(1),
        'retry_after' => MeprUtils::hours(1),
        'delete_completed_after' => MeprUtils::days(2),
        'delete_failed_after'    => MeprUtils::days(2)
      )
    ));

    // Setup the options page
    add_action('mepr_display_general_options', array($this,'display_option_fields'));
    add_action('mepr-process-options', array($this,'store_option_fields'));

    // Set a wp-cron
    add_filter( 'cron_schedules', array($this,'intervals') );
    add_action( 'mepr_jobs_worker', array($this,'worker') );
    add_action( 'mepr_jobs_cleanup', array($this,'cleanup') );

    if( !wp_next_scheduled( 'mepr_jobs_worker' ) ) {
       wp_schedule_event( time(), 'mepr_jobs_interval', 'mepr_jobs_worker' );
    }

    if( !wp_next_scheduled( 'mepr_jobs_cleanup' ) ) {
       wp_schedule_event( time(), 'mepr_jobs_cleanup_interval', 'mepr_jobs_cleanup' );
    }
  }

  public function intervals( $schedules ) {
    $schedules['mepr_jobs_interval'] = array(
      'interval' => $this->config->worker->interval,
      'display' => __('MemberPress Jobs Worker', 'memberpress')
    );

    $schedules['mepr_jobs_cleanup_interval'] = array(
      'interval' => $this->config->cleanup->interval,
      'display' => __('MemberPress Jobs Cleanup', 'memberpress')
    );

    return $schedules;
  }

  public function worker() {
    $max_run_time = 45;
    $start_time = time();

    // We want to allow for at least 15 seconds of buffer
    while( ( ( time() - $start_time ) <= $max_run_time ) and
           ( $job = $this->next_job() ) )
    {
      try {
        $this->work($job);
        if(isset($job->class)) {
          $obj = MeprJobFactory::fetch($job->class, $job);
          MeprUtils::debug_log(sprintf(__('Starting Job - %1$s (%2$s): %3$s', 'memberpress'), $job->id, $job->class, MeprUtils::object_to_string($obj)));
          $obj->perform(); // Run the job's perform method
          MeprUtils::debug_log(sprintf(__('Job Completed - %1$s (%2$s)', 'memberpress'), $job->id, $job->class));
          $this->complete($job); // When we're successful we complete the job
        }
        else {
          $this->fail($job, __('No class was specified in the job config', 'memberpress'));
          MeprUtils::debug_log(__('Job Failed: No class', 'memberpress'));
        }
      }
      catch(Exception $e) {
        $this->fail($job, $e->getMessage());
        MeprUtils::debug_log(sprintf(__('Job Failed: %s', 'memberpress'), $e->getMessage()));
      }
    }
  }

  public function cleanup() {
    global $wpdb;
    $mepr_db = new MeprDb();

    // Retry lingering jobs
    $query = "UPDATE {$mepr_db->jobs}
                 SET status = %s
               WHERE status IN (%s,%s)
                 AND tries <= %d
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
    $query = $wpdb->prepare( $query,
      $this->config->status->pending, // Set status to pending
      $this->config->status->working, // if status = working or
      $this->config->status->failed, // status = failed and
      $this->config->cleanup->num_retries, // number of tries <= num_retries
      MeprUtils::db_now(),
      $this->config->cleanup->retry_after // and the correct number of seconds since lastrun has elapsed
    );
    $wpdb->query($query);

    // Delete completed jobs that have been in the system for over a day?
    $query = "DELETE FROM {$mepr_db->jobs}
               WHERE status = %s
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
    $query = $wpdb->prepare( $query, // Delete jobs
      $this->config->status->complete, // which have a status = complete
      MeprUtils::db_now(),
      $this->config->cleanup->delete_completed_after // and the correct number of seconds since lastrun has elapsed
    );
    $wpdb->query($query);

    // Delete jobs that have been retried and are still in a working state
    $query = "DELETE FROM {$mepr_db->jobs}
               WHERE tries > %d
                 AND TIMESTAMPDIFF(SECOND,lastrun,%s) >= %d";
    $query = $wpdb->prepare( $query, // Delete jobs
      $this->config->cleanup->num_retries, // which have only been 'n' retries
      MeprUtils::db_now(),
      $this->config->cleanup->delete_failed_after // and the correct number of seconds since lastrun has elapsed
    );
    $wpdb->query($query);
  }

  /** Returns a full list of all the pending jobs in the queue */
  public function queue() {
    global $wpdb;

    $mepr_db = new MeprDb();

    $query = "
      SELECT * FROM {$mepr_db->jobs}
       WHERE status = %s
         AND runtime <= %s
       ORDER BY priority ASC, runtime ASC
    ";
    $query = $wpdb->prepare( $query, $this->config->status->pending, MeprUtils::db_now() );

    return $wpdb->get_results($query,OBJECT);
  }

  public function next_job() {
    global $wpdb;

    $mepr_db = new MeprDb();

    $query = "SELECT * FROM {$mepr_db->jobs}
               WHERE status = %s
                 AND runtime <= %s
               ORDER BY priority ASC, runtime ASC
               LIMIT 1";
    $query = $wpdb->prepare( $query, $this->config->status->pending, MeprUtils::db_now() );

    return $wpdb->get_row($query,OBJECT);
  }

  public function enqueue_in($in, $classname, $args=array(), $priority=10) {
    $when = time() + $this->interval2seconds($in);
    $this->enqueue($classname,$args,$when,$priority);
  }

  public function enqueue_at($at, $classname, $args=array(), $priority=10) {
    $when = $at;
    $this->enqueue($classname,$args,$when,$priority);
  }

  public function enqueue($classname, $args=array(), $when='now', $priority=10) {
    global $wpdb;
    $mepr_db = new MeprDb();

    if($when==='now') { $when = time(); }

    $config = array(
      'runtime' => gmdate('c', $when),
      'firstrun' => gmdate('c', $when),
      'priority' => $priority,
      'tries' => 0,
      'class' => $classname,
      'args' => json_encode($args),
      'reason' => '',
      'status' => $this->config->status->pending,
      'lastrun' => gmdate('c')
    );

    // returns the job id to dequeue later if necessary
    return $mepr_db->create_record($mepr_db->jobs, $config, true);
  }

  public function dequeue($job_id) {
    if($job_id==0) { return; }

    global $wpdb;
    $mepr_db = new MeprDb();
    return $mepr_db->delete_records($mepr_db->jobs, array('id' => $job_id));
  }

  public function work($job) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $args = array( 'status' => $this->config->status->working,
                   'tries' => $job->tries + 1,
                   'lastrun' => gmdate('c') );

    $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
  }

  public function retry($job, $reason='') {
    global $wpdb;
    $mepr_db = new MeprDb();

    $args = array( 'status' => $this->config->status->pending,
                   'runtime' => gmdate('c'),
                   'reason' => $reason );

    $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
  }

  public function complete($job) {
    global $wpdb;
    $mepr_db = new MeprDb();

    $args = array( 'status' => $this->config->status->complete );

    $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
  }

  public function fail($job, $reason='') {
    global $wpdb;
    $mepr_db = new MeprDb();

    // We fail and then re-enqueue for an hour later 5 times before giving up
    if($job->tries >= $this->config->cleanup->num_retries) {
      $args = array( 'status' => $this->config->status->failed, 'reason' => $reason );
      $mepr_db->update_record($mepr_db->jobs, $job->id, $args);
    }
    else {
      $this->retry($job,$reason);
    }
  }

  private function interval2seconds($interval) {
    $units = array('m','h','d','w','M','y');
    $seconds = 0;

    foreach($units as $u) {
      preg_match_all("/(\d+){$u}/", $interval, $matches);
      if(isset($matches[1])) {
        foreach($matches[1] as $m) {
          if($u=='m') { $seconds += MeprUtils::minutes($m); }
          elseif($u=='h') { $seconds += MeprUtils::hours($m); }
          elseif($u=='d') { $seconds += MeprUtils::days($m); }
          elseif($u=='w') { $seconds += MeprUtils::weeks($m); }
          elseif($u=='M') { $seconds += MeprUtils::months($m); }
          elseif($u=='y') { $seconds += MeprUtils::years($m); }
        }
      }
    }

    return $seconds;
  }

  public function unschedule_events() {
    $timestamp = wp_next_scheduled( 'mepr_jobs_worker' );
    wp_unschedule_event( $timestamp, 'mepr_jobs_worker' );

    $timestamp = wp_next_scheduled( 'mepr_jobs_cleanup' );
    wp_unschedule_event( $timestamp, 'mepr_jobs_cleanup' );
  }

  public function display_option_fields() {
    $enabled = get_option('mp-bkg-email-jobs-enabled',isset($_POST['bkg_email_jobs_enabled']));

    ?>
    <div id="mp-bkg-email-jobs">
      <br/>
      <h3><?php _e('Background Jobs', 'memberpress'); ?></h3>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="bkg_email_jobs_enabled"><?php _e('Asynchronous Emails', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip( 'mepr-asynchronous-emails',
                                                 __('Send Emails Asynchronously in the Background', 'memberpress'),
                                                 __('This option will allow you to send all MemberPress emails asynchronously. This option can increase the speed & performance of the checkout process but may also result in a delay in when emails are recieved. <strong>Note:</strong> This option requires wp-cron to be enabled and working.', 'memberpress') ); ?>
            </th>
            <td>
              <input type="checkbox" name="bkg_email_jobs_enabled" id="bkg_email_jobs_enabled" <?php checked($enabled); ?> />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
  }

  public function validate_option_fields($errors)
  {
    // Nothing to validate yet -- if ever
  }

  public function update_option_fields()
  {
    // Nothing to do yet -- if ever
  }

  public function store_option_fields()
  {
    update_option('mp-bkg-email-jobs-enabled',isset($_POST['bkg_email_jobs_enabled']));
  }
}

