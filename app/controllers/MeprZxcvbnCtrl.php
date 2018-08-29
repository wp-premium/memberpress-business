<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprZxcvbnCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->enforce_strong_password) {
      add_filter('mepr-signup-scripts', 'MeprZxcvbnCtrl::load_scripts', 10, 3);
      add_action('wp_enqueue_scripts', 'MeprZxcvbnCtrl::load_reset_password_scripts');
      add_action('mepr-after-password-fields', 'MeprZxcvbnCtrl::display_meter');
      add_action('mepr-account-after-password-fields', 'MeprZxcvbnCtrl::display_meter');
      add_action('mepr-reset-password-after-password-fields', 'MeprZxcvbnCtrl::display_meter');
      add_filter('mepr-validate-signup', 'MeprZxcvbnCtrl::validate_signup');
    }
  }

  public static function validate_signup($errors) {
    if(isset($_POST['mp-pass-strength']) && (int)$_POST['mp-pass-strength'] < self::get_required_int()) {
      $errors[] = sprintf(__('This password doesn\'t meet the minimum strength requirement. %s.', 'memberpress'), self::get_required_str());
    }

    return $errors;
  }

  public static function get_i18n_array() {
    //Weak is actually still relatively strong, so we're going to alter the mapping a bit
    return  array(
              'script_url'  => MEPR_JS_URL.'/zxcvbn.js',
              'very_weak'   => __('Weak', 'memberpress'),
              'weak'        => __('Medium', 'memberpress'),
              'medium'      => __('Strong', 'memberpress'),
              'strong'      => __('Very Strong', 'memberpress'),
              'very_strong' => __('Unbreakable', 'memberpress'),
              'required'    => '',
              'indicator'   => __('Password Strength', 'memberpress')
            );
  }

  public static function load_scripts($reqs, $is_prod_page, $is_acct_page) {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->global_styles || $is_prod_page || $is_acct_page) {
      $i18n = self::get_i18n_array();

      wp_register_script('mepr-zxcvbn', MEPR_JS_URL.'/zxcvbn-async.js');
      wp_localize_script('mepr-zxcvbn', 'MeprZXCVBN', $i18n);
      wp_enqueue_style('mepr-zxcvbn-css', MEPR_CSS_URL.'/zxcvbn.css');

      $reqs[] = 'mepr-zxcvbn';
    }

    return $reqs;
  }

  public static function load_reset_password_scripts() {
    $mepr_options = MeprOptions::fetch();

    if(!$mepr_options->global_styles && isset($_GET['action']) && $_GET['action'] == 'reset_password') {
      $i18n = self::get_i18n_array();

      wp_register_script('mepr-zxcvbn', MEPR_JS_URL.'/zxcvbn-async.js', array('jquery'));
      wp_localize_script('mepr-zxcvbn', 'MeprZXCVBN', $i18n);
      wp_enqueue_script('mepr-zxcvbn');
      wp_enqueue_style('mepr-zxcvbn-css', MEPR_CSS_URL.'/zxcvbn.css');
    }
  }

  public static function display_meter($user = null) {
    $required_str = self::get_required_str();

    ?>
    <div class="mp-form-row mp-password-strength-area">
      <span class="mp-password-strength-display mp-nopass"><?php _e('Password Strength', 'memberpress'); ?></span>

      <?php if(!empty($required_str)): ?>
        <span>&nbsp;<small><em><?php echo $required_str; ?></em></small></span>
      <?php endif; ?>

      <input type="hidden" name="mp-pass-strength" class="mp-pass-strength" value="0" />
    </div>
    <?php
  }

  public static function get_required_str() {
    $mepr_options = MeprOptions::fetch();
    $txt          = '';

    switch($mepr_options->enforce_strong_password) {
      case 'weak': // Mapped as "Medium"
        $txt = __('Password must be "Medium" or stronger', 'memberpress');
        break;
      case 'medium': // Mapped as "Strong"
        $txt = __('Password must be "Strong" or stronger', 'memberpress');
        break;
      case 'strong': // Mapped as "Very Strong"
        $txt = __('Password must be "Very Strong" or stronger', 'memberpress');
        break;
    }

    return MeprHooks::apply_filters('mepr-password-meter-text', $txt, $mepr_options->enforce_strong_password);
  }

  public static function get_required_int() {
    $mepr_options = MeprOptions::fetch();

    switch($mepr_options->enforce_strong_password) {
      case 'weak': // Mapped as "Medium"
        return 1;
      case 'medium': // Mapped as "Strong"
        return 2;
        break;
      case 'strong': // Mapped as "Very Strong"
        return 3;
      default:
        return 0; // Not required
    }
  }
} //End class

