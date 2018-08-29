<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprDbCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    // DB upgrades will happen here, as a non-blocking process hopefully
    add_action('admin_init', array($this,'upgrade_needed'));
    add_action('wp_ajax_mepr_db_upgrade', array($this,'ajax_db_upgrade'));
    add_action('wp_ajax_mepr_db_upgrade_success', array($this,'ajax_db_upgrade_success'));
    add_action('wp_ajax_mepr_db_upgrade_not_needed', array($this,'ajax_db_upgrade_not_needed'));
    add_action('wp_ajax_mepr_db_upgrade_in_progress', array($this,'ajax_db_upgrade_in_progress'));
    add_action('wp_ajax_mepr_db_upgrade_error', array($this,'ajax_db_upgrade_error'));

    // Cleanup soft db migrate for now
    // TODO: Remove soon
    if(($timestamp = wp_next_scheduled('mepr_migration_worker'))) {
      wp_unschedule_event($timestamp, 'mepr_migration_worker');
    }

    // Small UI to check on some table records
    add_action('mepr_activate_license_page', array($this, 'activate_license_page'));
  }

  public function upgrade_needed() {
    if(!MeprUtils::is_mepr_admin()) { return; }
    if(defined('DOING_AJAX')) { return; }
    if(isset($_GET['page']) && $_GET['page']=='mepr-rollback') { return; }
    if(isset($_GET['mepraction']) && $_GET['mepraction']=='cancel-migration') {
      delete_transient('mepr_migrating');
      delete_transient('mepr_migration_error');
      delete_transient('mepr_current_migration');
      update_option('mepr_db_version',MEPR_VERSION);
      //wp_redirect(admin_url('index.php'));
      return;
    }

    $mepr_db = new MeprDb();

    if($mepr_db->show_upgrade_ui()) {
      MeprView::render('admin/db/upgrade_needed');
      exit;
    }
    else {
      try {
        delete_transient('mepr_migration_error'); // Reset migration error transient
        $this->upgrade();
      }
      catch(MeprDbMigrationException $e) {
        // We just log the error?
      }
    }
  }

  public function ajax_db_upgrade() {
    check_ajax_referer('db_upgrade', 'mepr_db_upgrade_nonce');

    // Network super admin and non-network admins will be the only ones dealing with upgrades
    if(!MeprUtils::is_mepr_admin()) {
      header('HTTP/1.1 403 Forbidden');
      exit(json_encode(array('error'=>__('You\'re unauthorized to access this resource.', 'memberpress'))));
    }

    $mepr_db = MeprDb::fetch();

    header('Content-Type: application/json');

    if($mepr_db->do_upgrade()) {
      try {
        delete_transient('mepr_migration_error'); // Reset migration error transient
        $this->upgrade();
        exit(json_encode(array('status'=>'complete', 'message'=>__('Your Upgrade has completed successfully', 'memberpress'))));
      }
      catch(MeprDbMigrationException $e) {
        header('HTTP/1.1 500 Internal Error');
        exit(json_encode(array('status' => 'error', 'message' => $e->getMessage())));
      }
    }

    exit(json_encode(array('status'=>'already_migrated', 'message'=>__('No need to upgrade your database', 'memberpress'))));
  }

  public function ajax_db_upgrade_in_progress() {
    check_ajax_referer('db_upgrade_in_progress', 'mepr_db_upgrade_nonce');

    $mig = new MeprDbMigrations();

    // Network super admin and non-network admins will be the only ones dealing with upgrades
    if(!MeprUtils::is_mepr_admin()) {
      header('HTTP/1.1 403 Forbidden');
      exit(json_encode(array('error'=>__('You\'re unauthorized to access this resource.', 'memberpress'))));
    }

    $version = get_transient('mepr_migrating');

    if(!empty($version)) {
      $current_migration = get_transient('mepr_current_migration');

      if(!isset($current_migration['check']) || $current_migration['check'] == false) {
        $check = array('completed' => 0, 'total' => 0, 'progress' => 0);
      }
      else {
        $check = call_user_func(array($mig, $current_migration['check']));
      }

      $message  = ((!isset($current_migration['message']) || empty($current_migration['message'])) ? __('MemberPress is currently upgrading your database', 'memberpress') : $current_migration['message']);

      extract($check);

      header('HTTP/1.1 200 OK');
      $status = 'in_progress';
      exit(
        json_encode(
          compact('status','version','progress','message','completed','total')
        )
      );
    }
    else {
      $error = get_transient('mepr_migration_error');
      if($error) {
        header('HTTP/1.1 500 Internal Error');
        exit(json_encode(array('error'=>$error)));
      }
      else {
        header('HTTP/1.1 200 OK');
        exit(json_encode(array('status'=>'not_in_progress','message'=>__('No MemberPress database upgrade is in progress', 'memberpress'))));
      }
    }

    exit;
  }

  public function ajax_db_upgrade_success() {
    check_ajax_referer('db_upgrade_success', 'mepr_db_upgrade_nonce');

    $error = get_transient('mepr_migration_error');
    if($error) {
      MeprView::render('admin/db/upgrade_error',compact('error'));
    }
    else {
      MeprView::render('admin/db/upgrade_success');
    }
    exit;
  }

  public function ajax_db_upgrade_not_needed() {
    check_ajax_referer('db_upgrade_not_needed', 'mepr_db_upgrade_nonce');

    MeprView::render('admin/db/upgrade_not_needed');
    exit;
  }

  public function ajax_db_upgrade_error() {
    check_ajax_referer('db_upgrade_error', 'mepr_db_upgrade_nonce');

    $error = get_transient('mepr_migration_error');
    MeprView::render('admin/db/upgrade_error',compact('error'));
    exit;
  }

  /** INSTALL PLUGIN
    * Handled in the same way wp-cron does it ...
    * fast, non-blocking post with an ignore_user_abort
    */
  public function upgrade() {
    if(!MeprUtils::is_mepr_admin()) { return; }

    global $wpdb;
    $mepr_db = MeprDb::fetch();

    // This is Async now so if we're already migrating this version then let's bail
    $already_migrating = get_transient('mepr_migrating');
    if(!empty($already_migrating)) { return; }

    if($mepr_db->do_upgrade()) {
      @ignore_user_abort(true);
      @set_time_limit(0);

      set_transient('mepr_migrating', MEPR_VERSION, MeprUtils::hours(4));

      // Leave this up to the individual migrations now
      //$wpdb->query('START TRANSACTION');

      if(is_multisite() && is_super_admin()) {
        global $blog_id;

        // If we're on the root blog then let's upgrade every site on the network
        if($blog_id==1) {
          $mepr_db->upgrade_multisite();
        }
        else {
          $mepr_db->upgrade();
        }
      }
      else {
        $mepr_db->upgrade();
      }

      //$wpdb->query('COMMIT');

      delete_transient('mepr_migrating');
    }
  }

  public function activate_license_page() {
    $mepr_db = MeprDb::fetch();

    if(isset($_GET['db-status']) && $mepr_db->table_exists($mepr_db->members) && $mepr_db->table_exists($mepr_db->subscriptions)) {
      $mepr_db_migration = new MeprDbMigrations();

      $subs = (object)$mepr_db_migration->check_create_and_migrate_subscriptions_table_001();
      $mbrs = (object)$mepr_db_migration->check_create_and_migrate_members_table_002();

      ?>
      <br/>
      <h3><?php _e('Database Upgrade Progress', 'memberpress'); ?></h3>
      <table>
        <tr><td><strong><?php _e('Members', 'memberpress'); ?></strong></td><td><?php echo "{$mbrs->progress}% ({$mbrs->completed} / {$mbrs->total})"; ?></td></tr>
        <tr><td><strong><?php _e('Subscriptions', 'memberpress'); ?></strong></td><td><?php echo "{$subs->progress}% ({$subs->completed} / {$subs->total})"; ?></td></tr>
      </table>
      <br/>
      <?php
    }
  }

} //End class

