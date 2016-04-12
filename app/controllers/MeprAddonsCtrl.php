<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAddonsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('admin_notices', array($this,'addon_notice'));
    add_action('wp_ajax_mepr_addon_stop_notice', array($this, 'ajax_addon_stop_notice'));
  }

  public function addon_notice() {
    $addons = $this->get_addons();

    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    foreach($addons as $k => $a) {
      // No warning message if the plugin is installed
      if(is_plugin_active("memberpress-{$k}/main.php")) { continue; }

      // No notice if the user has aborted these messages
      $stop = get_option("mepr_{$k}_stop_notices");
      if(!empty($stop)) { continue; }

      // If the addons is enabled and its addon isn't istalled then popup an error
      $dbval = get_option($a->option);
      if(empty($dbval) ) { continue; }

      $installed = is_dir(WP_PLUGIN_DIR."/memberpress-{$k}");

      MeprView::render('admin/update/addon_popup', get_defined_vars());

      break; // only show one error at a time yo
    }
  }

  public function ajax_addon_stop_notice() {
    if(!MeprUtils::is_mepr_admin()) {
      header('HTTP/1.1 401 Unauthorized', true, 401);
      exit(json_encode(array('error'=>__('Error: You are unauthorized.', 'memberpress'))));
    }

    if(!isset($_REQUEST['addon'])) {
      header('HTTP/1.1 400 Bad Request', true, 400);
      exit(json_encode(array('error'=>__('Error: No addon was specified.', 'memberpress'))));
    }

    $addon = $_REQUEST['addon'];
    $addons = $this->get_addons();

    if(!array_key_exists($addon,$addons)) {
      header('HTTP/1.1 400 Bad Request', true, 400);
      exit(json_encode(array('error'=>__('Error: Invalid addon.', 'memberpress'))));
    }

    update_option("mepr_{$addon}_stop_notices", true);

    exit(true);
  }

  private function get_addons() {
    return array(
      'activecampaign' =>(object)array('option'=>'mepractivecampaign_enabled',  'name'=>__('ActiveCampaign', 'memberpress')),
      'aweber'         =>(object)array('option'=>'mepr_adv_aweber_enabled',     'name'=>__('AWeber', 'memberpress')),
      'constantcontact'=>(object)array('option'=>'meprconstantcontact_enabled', 'name'=>__('Constant Contact', 'memberpress')),
      'drip'           =>(object)array('option'=>'meprdrip_enabled',            'name'=>__('Drip', 'memberpress')),
      'getresponse'    =>(object)array('option'=>'meprgetresponse_enabled',     'name'=>__('GetResponse', 'memberpress')),
      'madmimi'        =>(object)array('option'=>'meprmadmimi_enabled',         'name'=>__('Mad Mimi', 'memberpress')),
      'mailchimp'      =>(object)array('option'=>'meprmailchimp_enabled',       'name'=>__('MailChimp', 'memberpress')),
      'mailpoet'       =>(object)array('option'=>'meprmailpoet_enabled',        'name'=>__('MailPoet', 'memberpress')),
    );
  }

  /**
   * Returns current plugin info.
   *
   * @return string Plugin info
   */
  public function curr_plugin_info($main_file) {
    static $curr_plugins;

    if(!isset($curr_plugins)) {
      if(!function_exists('get_plugins')) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
      }

      $curr_plugins = get_plugins();
    }

    if(isset($curr_plugins[$main_file])) {
      return $curr_plugins[$main_file];
    }

    return '';
  }

} //End class

