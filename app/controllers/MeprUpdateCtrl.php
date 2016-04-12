<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUpdateCtrl extends MeprBaseCtrl {

  public function load_hooks() {
    add_filter('pre_set_site_transient_update_plugins', 'MeprUpdateCtrl::queue_update');
    add_filter('plugins_api', 'MeprUpdateCtrl::plugin_info', 11, 3);
    add_action('admin_enqueue_scripts', 'MeprUpdateCtrl::enqueue_scripts');
    add_action('admin_notices', 'MeprUpdateCtrl::activation_warning');
    //add_action('mepr_display_options', 'MeprUpdateCtrl::queue_button');
    add_action('admin_init', 'MeprUpdateCtrl::activate_from_define');
    add_action('admin_init', 'MeprUpdateCtrl::maybe_activate');
    add_action('wp_ajax_mepr_edge_updates', 'MeprUpdateCtrl::mepr_edge_updates');
  }

  public static function route() {
    if(strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
      return self::process_form();
    }
    else {
      if( isset($_GET['action']) &&
          $_GET['action'] == 'deactivate' &&
          isset($_GET['_wpnonce']) &&
          wp_verify_nonce($_GET['_wpnonce'], 'memberpress_deactivate') ) {
        return self::deactivate();
      }
      else {
        return self::display_form();
      }
    }
  }

  public static function display_form($message='', $errors=array()) {
    $mepr_options = MeprOptions::fetch();

    // We just force the queue to update when this page is visited
    // that way we ensure the license info transient is set
    self::manually_queue_update();

    if(!empty($mepr_options->mothership_license) && empty($errors)) {
      $li = get_site_transient( 'mepr_license_info' );
    }

    MeprView::render('/admin/update/ui', get_defined_vars());
  }

  public static function process_form() {
    if(!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'activation_form')) {
      wp_die(_e('Why you creepin\'?', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST[$mepr_options->mothership_license_str])) {
      self::display_form();
      return;
    }

    $message = '';
    $errors = array();
    $mepr_options->mothership_license = stripslashes($_POST[$mepr_options->mothership_license_str]);
    $domain = urlencode(MeprUtils::site_domain());

    try {
      $args = compact('domain');
      $act = self::send_mothership_request("/license_keys/activate/{$mepr_options->mothership_license}", $args, 'post');
      self::manually_queue_update();
      $mepr_options->store(false);
      $message = $act['message'];
    }
    catch(Exception $e) {
      $errors[] = $e->getMessage();
    }

    self::display_form($message, $errors);
  }

  public static function is_activated() {
    $mepr_options = MeprOptions::fetch();
    $activated = get_option('mepr_activated');
    return (!empty($mepr_options->mothership_license) && !empty($activated));
  }

  public static function check_license_activation() {
    $aov = get_option('mepr_activation_override');

    if(!empty($aov)) { return update_option('mepr_activated', true); }

    $mepr_options = MeprOptions::fetch();
    $domain = urlencode(MeprUtils::site_domain());

    $args = compact('domain');

    try {
      $act = self::send_mothership_request("/license_keys/check/{$mepr_options->mothership_license}", $args, 'get');

      if(!empty($act) && is_array($act) && isset($act['status'])) {
        update_option('mepr_activated', ($act['status']=='enabled'));
      }
    }
    catch(Exception $e) {
      // TODO: For now do nothing if the server can't be reached
    }
  }

  public static function maybe_activate() {
    $activated = get_option('mepr_activated');

    if($activated==='') {
      self::check_license_activation();
    }
  }

  public static function activate_from_define() {
    $mepr_options = MeprOptions::fetch();

    if( defined('MEMBERPRESS_LICENSE_KEY') &&
        $mepr_options->mothership_license != MEMBERPRESS_LICENSE_KEY ) {
      $message = '';
      $errors = array();
      $mepr_options->mothership_license = stripslashes(MEMBERPRESS_LICENSE_KEY);
      $domain = urlencode(MeprUtils::site_domain());

      try {
        $args = compact('domain');

        if(!empty($mepr_options->mothership_license)) {
          $act = self::send_mothership_request("/license_keys/deactivate/{$mepr_options->mothership_license}", $args, 'post');
          delete_site_transient('mepr_addons');
        }

        $act = self::send_mothership_request("/license_keys/activate/".MEMBERPRESS_LICENSE_KEY, $args, 'post');

        self::manually_queue_update();

        // If we're using defines then we have to do this with defines too
        $mepr_options->edge_updates = false;
        $mepr_options->store(false);

        $message = $act['message'];
        $callback = create_function( '', '$message = "'.$message.'"; ' .
                                     'MeprView::render("/admin/errors", get_defined_vars());' );
      }
      catch(Exception $e) {
        $callback = create_function( '', '$error = "'.$e->getMessage().'"; ' .
                                     'MeprView::render("/admin/update/activation_warning", get_defined_vars());' );
      }

      add_action( 'admin_notices', $callback );
    }
  }

  public static function deactivate() {
    $mepr_options = MeprOptions::fetch();
    $domain = urlencode(MeprUtils::site_domain());

    try {
      $args = compact('domain');
      $act = self::send_mothership_request("/license_keys/deactivate/{$mepr_options->mothership_license}", $args, 'post');

      self::manually_queue_update();

      $mepr_options->mothership_license = '';
      $mepr_options->store(false);

      // Don't need to check the mothership for this one ... we just deactivated
      update_option('mepr_activated', false);

      $message = $act['message'];
    }
    catch(Exception $e) {
      $errors[] = $e->getMessage();
    }

    self::display_form($message);
  }

  public static function queue_update($transient, $force=false) {
    $mepr_options = MeprOptions::fetch();

    if($force || (false === ($update_info = get_site_transient('mepr_update_info')))) {
      if(empty($mepr_options->mothership_license)) {
        // Just here to query for the current version
        $args = array();
        if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
          $args['edge'] = 'true';
        }

        $version_info = self::send_mothership_request( "/versions/latest/developer", $args );
        $curr_version = $version_info['version'];
        $download_url = '';
      }
      else {
        try {
          $domain = urlencode(MeprUtils::site_domain());
          $args = compact('domain');

          if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
            $args['edge'] = 'true';
          }

          $license_info = self::send_mothership_request("/versions/info/{$mepr_options->mothership_license}", $args, 'post');
          $curr_version = $license_info['version'];
          $download_url = $license_info['url'];

          set_site_transient(
            'mepr_license_info',
            $license_info,
            MeprUtils::hours(12)
          );
        }
        catch(Exception $e) {
          try {
            // Just here to query for the current version
            $args = array();
            if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
              $args['edge'] = 'true';
            }

            $version_info = self::send_mothership_request("/versions/latest/developer", $args);
            $curr_version = $version_info['version'];
            $download_url = '';
          }
          catch(Exception $e) {
            if(isset($transient->response[MEPR_PLUGIN_SLUG])) {
              unset($transient->response[MEPR_PLUGIN_SLUG]);
            }

            self::check_license_activation();
            return $transient;
          }
        }
      }

      set_site_transient(
        'mepr_update_info',
        compact('curr_version', 'download_url'),
        MeprUtils::hours(12)
      );

      self::addons(false, true);
    }
    else {
      extract( $update_info );
    }

    if(isset($curr_version) && version_compare($curr_version, MEPR_VERSION, '>')) {
      $transient->response[MEPR_PLUGIN_SLUG] = (object)array(
        'id'          => $curr_version,
        'slug'        => 'memberpress',
        'new_version' => $curr_version,
        'url'         => 'http://memberpress.com',
        'package'     => $download_url
      );
    }
    else {
      unset( $transient->response[MEPR_PLUGIN_SLUG] );
    }

    self::check_license_activation();
    return $transient;
  }

  public static function manually_queue_update() {
    $transient = get_site_transient('update_plugins');
    set_site_transient('update_plugins', self::queue_update($transient, true));
  }

  public static function queue_button() {
    ?>
    <a href="<?php echo admin_url('admin.php?page=memberpress-options&action=queue&_wpnonce=' . wp_create_nonce('MeprUpdateCtrl::manually_queue_update')); ?>" class="button"><?php _e('Check for Update', 'memberpress')?></a>
    <?php
  }

  // Return up-to-date addon info for memberpress & its addons
  public static function plugin_info($api, $action, $args) {
    global $wp_version;

    if(!isset($action) ||
       $action != 'plugin_information' ||
       (isset($args->slug) &&
        !preg_match("#^(developer|business|basic|memberpress-|affiliate-royale-mp)#", $args->slug))) {
      return $api;
    }

    $mepr_options = MeprOptions::fetch();

    // Any addons should accept the memberpress license for now
    if(!empty($mepr_options->mothership_license)) {
      try {
        $domain = urlencode(MeprUtils::site_domain());
        $params = compact('domain');

        if($mepr_options->edge_updates || (defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE)) {
          $params['edge'] = 'true';
        }

        $plugin_info = MeprUpdateCtrl::send_mothership_request(
          "/versions/plugin_information/{$args->slug}/{$mepr_options->mothership_license}",
          $params,
          'get'
        );

        if(isset($plugin_info['requires'])) { $plugin_info['requires'] = $wp_version; }
        if(isset($plugin_info['tested']))   { $plugin_info['tested']   = $wp_version; }
        if(isset($plugin_info['compatibility'])) { $plugin_info['compatibility'] = array($wp_version => array($wp_version => array(100, 0, 0))); }

        return (object)$plugin_info;
      }
      catch(Exception $e) {
        // Fail silently for now
      }
    }

    return $api;
  }

  public static function send_mothership_request( $endpoint,
                                                  $args=array(),
                                                  $method='get',
                                                  $domain='https://mothership.caseproof.com',
                                                  $blocking=true ) {
    $uri = "{$domain}{$endpoint}";

    $arg_array = array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'blocking'  => $blocking,
      'sslverify' => false
    );

    $resp = wp_remote_request($uri, $arg_array);

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if($blocking == false) {
      return true;
    }

    if(is_wp_error($resp)) {
      throw new Exception(__('You had an HTTP error connecting to Caseproof\'s Mothership API', 'memberpress'));
    }
    else {
      if(null !== ($json_res = json_decode($resp['body'], true))) {
        if(isset($json_res['error'])) {
          throw new Exception($json_res['error']);
        }
        else {
          return $json_res;
        }
      }
      else {
        throw new Exception(__('Your License Key was invalid', 'memberpress'));
      }
    }

    return false;
  }

  public static function enqueue_scripts($hook) {
    // toplevel_page_memberpress will only be accessible if the plugin is not enabled
    if($hook == 'memberpress_page_memberpress-updates' ||
       (!MeprUpdateCtrl::is_activated() && $hook == 'toplevel_page_memberpress')) {
      wp_register_style('mepr-settings-table', MEPR_CSS_URL.'/settings_table.css', array(), MEPR_VERSION);
      wp_enqueue_style('mepr-activate-css', MEPR_CSS_URL.'/admin-activate.css', array('mepr-settings-table'), MEPR_VERSION);

      wp_register_script('mepr-settings-table', MEPR_JS_URL.'/settings_table.js', array(), MEPR_VERSION);
      wp_enqueue_script('mepr-activate-js', MEPR_JS_URL.'/admin_activate.js', array('mepr-settings-table'), MEPR_VERSION);
    }
  }

  public static function activation_warning() {
    $mepr_options = MeprOptions::fetch();

    if(empty($mepr_options->mothership_license) &&
       (!isset($_REQUEST['page']) ||
         !($_REQUEST['page']=='memberpress-updates' ||
           (!self::is_activated() && $_REQUEST['page']=='memberpress')))) {
      MeprView::render('/admin/update/activation_warning', get_defined_vars());
    }
  }

  public static function mepr_edge_updates() {
    if(!MeprUtils::is_mepr_admin() || !wp_verify_nonce($_POST['wpnonce'],'wp-edge-updates')) {
      die(json_encode(array('error' => __('You do not have access.', 'memberpress'))));
    }

    if(!isset($_POST['edge'])) {
      die(json_encode(array('error' => __('Edge updates couldn\'t be updated.', 'memberpress'))));
    }

    $mepr_options = MeprOptions::fetch();
    $mepr_options->edge_updates = ($_POST['edge']=='true');
    $mepr_options->store(false);

    // Re-queue updates when this is checked
    self::manually_queue_update();

    die(json_encode(array('state' => ($mepr_options->edge_updates ? 'true' : 'false'))));
  }

  public static function addons($return_object=false, $force=false) {
    $mepr_options = MeprOptions::fetch();
    $license = $mepr_options->mothership_license;

    if($force) {
      delete_site_transient('mepr_addons');
    }

    if(($addons = get_site_transient('mepr_addons'))) {
      $addons = json_decode($addons);
    }
    else {
      $addons = array();

      if(!empty($license)) {
        try {
          $domain = urlencode(MeprUtils::site_domain());
          $args = compact('domain');

          if(defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE) { $args['edge'] = 'true'; }
          $addons = self::send_mothership_request('/versions/addons/'.MEPR_EDITION."/{$license}", $args);
        }
        catch(Exception $e) {
          // fail silently
        }
      }

      $json = json_encode($addons);
      set_site_transient('mepr_addons',$json,MeprUtils::hours(12));

      if($return_object) {
        $addons = json_decode($json);
      }
    }

    return $addons;
  }
} //End class
