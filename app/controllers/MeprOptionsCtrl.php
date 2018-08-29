<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprOptionsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('wp_ajax_mepr_gateway_form', 'MeprOptionsCtrl::gateway_form');
    add_action('admin_enqueue_scripts', 'MeprOptionsCtrl::enqueue_scripts');
    add_action('admin_notices', 'MeprOptionsCtrl::maybe_configure_options');
  }

  public static function maybe_configure_options() {
    $mepr_options = MeprOptions::fetch();

    if(!$mepr_options->setup_complete and
        (!isset($_REQUEST['page']) or
          $_REQUEST['page']!='memberpress-options'))
      MeprView::render('/admin/must_configure', get_defined_vars());
  }

  public static function route() {
    $action = (isset($_REQUEST['action'])?$_REQUEST['action']:'');

    if(MeprUtils::is_post_request() && $action == 'process-form') {
      check_admin_referer('mepr_update_options', 'mepr_options_nonce');
      return self::process_form();
    }
    else if($action == 'queue' and isset($_REQUEST['_wpnonce']) and
            wp_verify_nonce($_REQUEST['_wpnonce'], 'MeprUpdateCtrl::manually_queue_update')) {
      MeprUpdateCtrl::manually_queue_update();
    }
    else if($action==='upgrade') { // Manually upgrade the database
      $mepr_app = new MeprAppCtrl();
      try {
        delete_transient('mepr_migration_error');
        $mepr_app->install();
        $message = __('Database Was Upgraded', 'memberpress');
        return self::display_form(array(),$message);
      }
      catch(MeprDbMigrationException $e) {
        return self::display_form(array($e->getMessage()),'');
      }
    }
    else if($action==='clear_tax_rates') {
      check_admin_referer('clear_tax_rates', 'mepr_taxes_nonce');
      MeprTaxRate::destroy_all();
      $message = __('Tax rates have been cleared', 'memberpress');
      return self::display_form(array(),$message);
    }
    else {
      return self::display_form();
    }
  }

  public static function display_form($errors=array(), $message='') {
    $mepr_options = MeprOptions::fetch();

    if(MeprUtils::is_logged_in_and_an_admin()) {
      MeprView::render('/admin/options/form', get_defined_vars());
    }
  }

  public static function process_form() {
    $mepr_options = MeprOptions::fetch();

    if(MeprUtils::is_logged_in_and_an_admin()) {
      $errors = MeprHooks::apply_filters('mepr-validate-options', $mepr_options->validate($_POST, array()));

      if(count($errors) <= 0) {
        MeprHooks::do_action('mepr-process-options', $_POST);

        $mepr_options->update($_POST);
        $mepr_options->store();

        // Ensure that the rewrite rules are flushed & in place
        MeprUtils::flush_rewrite_rules(); //Don't call this before running ->update() - it borks stuff

        $message = __('Options saved.', 'memberpress');
      }

      MeprView::render('/admin/options/form', get_defined_vars());
    }
  }

  public static function enqueue_scripts($hook) {
    if($hook == 'memberpress_page_memberpress-options') {
      $mepr_options = MeprOptions::fetch();

      wp_enqueue_style('mp-options', MEPR_CSS_URL.'/admin-options.css', array('mepr-settings-table-css'), MEPR_VERSION);
      wp_enqueue_style('mp-emails', MEPR_CSS_URL.'/admin-emails.css', array('mp-options'), MEPR_VERSION);

      wp_register_script('jquery-clippy', MEPR_JS_URL.'/jquery.clippy.js', array('jquery'), MEPR_VERSION);

      $js_helpers = array(
        'nameLabel'         => __('Name:', 'memberpress'),
        'typeLabel'         => __('Type:', 'memberpress'),
        'defaultLabel'      => __('Default Value(s):', 'memberpress'),
        'signupLabel'       => __('Show at Signup', 'memberpress'),
        'accountLabel'      => __('Show in Account', 'memberpress'),
        'requiredLabel'     => __('Required', 'memberpress'),
        'textOption'        => __('Text', 'memberpress'),
        'textareaOption'    => __('Textarea', 'memberpress'),
        'checkboxOption'    => __('Checkbox', 'memberpress'),
        'dropdownOption'    => __('Dropdown', 'memberpress'),
        'multiselectOption' => __('Multi-Select', 'memberpress'),
        'emailOption'       => __('Email', 'memberpress'),
        'urlOption'         => __('URL', 'memberpress'),
        'radiosOption'      => __('Radio Buttons', 'memberpress'),
        'checkboxesOption'  => __('Checkboxes', 'memberpress'),
        'dateOption'        => __('Date', 'memberpress'),
        'optionNameLabel'   => __('Option Name:', 'memberpress'),
        'optionValueLabel'  => __('Option Value:', 'memberpress'),
        'addOptionLabel'    => __('Add Option', 'memberpress'),
        'show_fname_lname_id'    => "#{$mepr_options->show_fname_lname_str}",
        'require_fname_lname_id' => "#{$mepr_options->require_fname_lname_str}",
        'jsUrl'             => MEPR_JS_URL,
        'taxRateRemoveStr'  => __('Are you sure you want to delete this Tax Rate?', 'memberpress'),
        'confirmPMDelete'   => __('WARNING: Do not remove this Payment Method if you have active subscriptions using it. Doing so will prevent you from being notified of recurring payments for those subscriptions, which means your members will lose access to their paid content. Are you sure you want to delete this Payment Method?', 'memberpress'),
        'wpnonce'           => wp_create_nonce(MEPR_PLUGIN_SLUG)
      );
      wp_localize_script('jquery-clippy', 'MeprOptions', $js_helpers);

      wp_register_script('memberpress-i18n', MEPR_JS_URL.'/i18n.js', array('jquery'), MEPR_VERSION);
      wp_localize_script('memberpress-i18n', 'MeprI18n', array('states' => MeprUtils::states()));

      // Enqueue admin_options.js
      $local_data = array(
        'option_nonce' => wp_create_nonce('mepr_gateway_form_nonce'),
        'tax_nonce' => wp_create_nonce('mepr_taxes')
      );
      wp_enqueue_script('mepr-options-js', MEPR_JS_URL.'/admin_options.js',
        array(
          'jquery',
          'jquery-clippy',
          'mepr-settings-table-js',
          'mepr-admin-shared-js',
          'jquery-ui-sortable',
          'memberpress-i18n'
        ),
        MEPR_VERSION
      );
      wp_localize_script('mepr-options-js', 'MeprOptionData', $local_data);

      wp_enqueue_script('mepr-emails-js', MEPR_JS_URL.'/admin_emails.js', array('mepr-options-js'), MEPR_VERSION);

      MeprHooks::do_action('mepr-options-admin-enqueue-script', $hook);
    }
  }

  public static function gateway_form() {
    check_ajax_referer('mepr_gateway_form_nonce', 'option_nonce');

    if(!is_admin()) {
      die(json_encode(array('error'=>__('Unauthorized', 'memberpress'))));
    }

    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST['g']) or empty($_POST['g'])) {
      $gateways = array_keys(MeprGatewayFactory::all());

      if(empty($gateways)) {
        die(json_encode(array('error'=>__('No gateways were found', 'memberpress'))));
      }

      // Artificially set the gateway to the first available
      $gateway = $gateways[0];
    }
    else {
      $gateway = $_POST['g'];
    }

    try {
      $obj = MeprGatewayFactory::fetch($gateway);
    }
    catch(Exception $e) {
      die($e->getMessage());
    }

    ob_start();
    MeprView::render("/admin/options/gateway", get_defined_vars());
    $form = ob_get_clean();

    die( json_encode( array( 'form' => $form, 'id' => $obj->id ) ) );
  }
} //End class
