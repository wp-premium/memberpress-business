<?php if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

class MeprPopupCtrl extends MeprBaseCtrl {
  public $popup_css, $popup_js, $popups;

  public function __construct() {
    $cdn_base = 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0';
    $this->popup_css = "{$cdn_base}/magnific-popup.min.css";
    $this->popup_js  = "{$cdn_base}/jquery.magnific-popup.min.js";

    // This is an array of the currently defined popups ...
    // used to validate that the popup specified actually exists
    $this->popups = array(
      'senddata' => array(
        'user_popup' => false,
        'delay' => MONTH_IN_SECONDS,
        'delay_after_last_popup' => WEEK_IN_SECONDS,
      ),
    );

    parent::__construct();
  }

  public function load_hooks() {
    // This is a hidden option to help support in case
    // there's a problem stopping or delaying a popup
    $dap = get_option('mepr_disable_all_popups');
    if($dap) { return; }

    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_mepr_stop_popup', array($this, 'ajax_stop_or_delay_popup'));
    add_action('wp_ajax_mepr_delay_popup', array($this, 'ajax_stop_or_delay_popup'));
    add_action('admin_notices', array($this,'display_popups'));
  }

  public function enqueue_admin_scripts($hook) {
    $mepr_cpts = array(
      'memberpressproduct',
      'memberpressgroup',
      'memberpressrule',
      'memberpresscoupon',
      'mp-reminder'
    );

    if( false !== strstr($hook, 'memberpress') ||
        ( $hook=='edit.php' &&
          isset($_REQUEST['post_type']) &&
          in_array($_REQUEST['post_type'], $mepr_cpts) ) ) {
      wp_register_style('jquery-magnific-popup', $this->popup_css);
      wp_enqueue_style(
        'mepr-admin-popup',
        MEPR_CSS_URL.'/admin_popup.css',
        array('jquery-magnific-popup'),
        MEPR_VERSION
      );

      wp_register_script('jquery-magnific-popup', $this->popup_js, array('jquery'));
      wp_enqueue_script(
        'mepr-admin-popup',
        MEPR_JS_URL.'/admin_popup.js',
        array('jquery','jquery-magnific-popup'),
        MEPR_VERSION
      );
      $loc = array(
        'security' => wp_create_nonce('mepr-admin-popup'),
        'error' => __('An unknown error occurred.', 'memberpress')
      );
      wp_localize_script('mepr-admin-popup','MeprPopup', $loc);
    }
  }
  private function on_memberpress_page() {
    $screen = get_current_screen();
    return (isset($screen->parent_base) && $screen->parent_base=='memberpress');
  }

  public function display_popups() {
    //if(!$this->on_memberpress_page()) { return; }

    // If this isn't a MemberPress authorized user then bail
    if(!MeprUtils::is_mepr_admin()) { return; }

    foreach($this->popups as $popup => $settings) {
      $this->maybe_show_popup($popup);
    }
  }

  public function ajax_stop_or_delay_popup() {
    MeprUtils::check_ajax_referer('mepr-admin-popup','security');

    // If this isn't a MemberPress authorized user then bail
    if(!MeprUtils::is_mepr_admin()) {
      MeprUtils::exit_with_status(403,json_encode(array('error'=>__('Forbidden', 'pretty-link', 'memberpress'))));
    }

    if(!isset($_POST['popup'])) {
      MeprUtils::exit_with_status(400,json_encode(array('error'=>__('Must specify a popup', 'memberpress'))));
    }

    $popup = sanitize_text_field($_POST['popup']);

    if(!$this->is_valid_popup($popup)) {
      MeprUtils::exit_with_status(400,json_encode(array('error'=>__('Invalid popup', 'memberpress'))));
    }

    if($_POST['action']=='mepr_delay_popup') {
      $this->delay_popup($popup);
      $message = __('The popup was successfully delayed', 'memberpress');
    }
    else {
      $this->stop_popup($popup); // TODO: Error handling
      $message = __('The popup was successfully stopped', 'memberpress');
    }

    MeprUtils::exit_with_status(200,json_encode(compact('message')));
  }

  private function is_valid_popup($popup) {
    return in_array($popup,array_keys($this->popups));
  }

  private function stop_popup($popup) {
    // TODO: Should we add some error handling?
    if(!$this->is_valid_popup($popup)) { return; }

    if($this->popups[$popup]['user_popup']) {
      $user_id = MeprUtils::get_current_user_id();
      update_user_meta($user_id, $this->popup_stop_key($popup), 1);
    }
    else {
      update_option($this->popup_stop_key($popup), 1);
    }
  }

  private function delay_popup($popup) {
    // TODO: Should we add some error handling?
    if(!$this->is_valid_popup($popup)) { return; }

    set_transient(
      $this->popup_delay_key($popup),
      1,
      $this->popups[$popup]['delay']
    );
  }

  private function is_popup_delayed($popup) {
    if(!$this->is_valid_popup($popup)) { $return; }

    if($this->popups[$popup]['user_popup']) {
      // check if it's been delayed or stopped
      $user_id = MeprUtils::get_current_user_id();
      return get_transient($this->popup_delay_key($popup));
    }

    return get_transient($this->popup_delay_key($popup));
  }

  private function is_popup_stopped($popup) {
    if(!$this->is_valid_popup($popup)) { $return; }

    if($this->popups[$popup]['user_popup']) {
      $user_id = MeprUtils::get_current_user_id();
      return get_user_meta($user_id, $this->popup_stop_key($popup), true);
    }

    return get_option($this->popup_stop_key($popup));
  }

  private function set_popup_last_viewed_timestamp($popup) {
    $timestamp = time();
    return update_option('mepr-popup-last-viewed', compact('popup','timestamp'));
  }

  private function get_popup_last_viewed_timestamp() {
    $default = array('popup'=>false,'timestamp'=>false);
    return get_option('mepr-popup-last-viewed',$default);
  }

  private function maybe_show_popup($popup) {
    if($this->popup_visible($popup)) {
      $this->increment_popup_display_count($popup);
      $this->set_popup_last_viewed_timestamp($popup);
      require(MEPR_VIEWS_PATH."/admin/popups/{$popup}.php");
    }
  }

  private function popup_visible($popup) {
    $mepr_update = new MeprUpdateCtrl();
    if(!$mepr_update->is_activated() ||
       !$this->is_valid_popup($popup)) {
      return false;
    }

    // If we're not yet past the delay threshold for the last viewed popup then don't show it
    $last_viewed = $this->get_popup_last_viewed_timestamp();
    if( !empty($last_viewed) &&
        $last_viewed['popup']!=$popup &&
        ((int)$last_viewed['timestamp'] + (int)$this->popups[$popup]['delay_after_last_popup']) > time() ) {
      return false;
    }

    // This is for popups that should be displayed and resolved for each individual admin user
    $delayed = $this->is_popup_delayed($popup);

    // Popups displayed and resolved for any admin user in the system
    $stopped = $this->is_popup_stopped($popup);

    return (!$delayed && !$stopped);
  }

  private function increment_popup_display_count($popup) {
    $user_id = MeprUtils::get_current_user_id();
    $count = (int)get_user_meta($user_id, $this->popup_display_count_key($popup), true);
    update_user_meta($user_id, $this->popup_display_count_key($popup), ++$count);
  }

  private function popup_display_count_key($popup) {
    return "mepr-{$popup}-popup-display-count";
  }

  private function popup_delay_key($popup) {
    if($this->is_valid_popup($popup) && $this->popups[$popup]['user_popup']) {
      $user_id = MeprUtils::get_current_user_id();
      return "mepr-delay-{$popup}-popup-for-{$user_id}";
    }
    else {
      return "mepr-delay-{$popup}-popup";
    }
  }

  private function popup_stop_key($popup) {
    return "mepr-stop-{$popup}-popup";
  }
}

