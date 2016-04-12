<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAddonsHelper {

  public static function install_url($slug) {
    return admin_url('update.php?action=install-plugin&plugin=' . urlencode($slug) . '&_wpnonce=' . wp_create_nonce('install-plugin_' . $slug));
  }

  public static function upgrade_url($main_file) {
    return admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($main_file) . '&_wpnonce=' . wp_create_nonce('upgrade-plugin_' . $main_file));
  }

  public static function activate_url($main_file) {
    return admin_url('plugins.php?action=activate&plugin=' . urlencode($main_file) . '&plugin_status=all&paged=1&s&_wpnonce=' . wp_create_nonce('activate-plugin_' . $main_file));
  }

  public static function curr_version($main_file) {
    $addons_ctrl = MeprCtrlFactory::fetch('addons');
    $curr_info = $addons_ctrl->curr_plugin_info($main_file);
    return empty($curr_info) ? '' : $curr_info['Version'];
  }

  public static function is_update_available($main_file, $latest_version) {
    $curr_version = self::curr_version($main_file);
    return version_compare($curr_version, $latest_version, '<');
  }
}

