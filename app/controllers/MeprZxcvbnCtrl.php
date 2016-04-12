<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprZxcvbnCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->enforce_strong_password) {
      add_filter('mepr-signup-scripts', 'MeprZxcvbnCtrl::load_scripts', 10, 3);
      add_action('mepr-after-password-fields', 'MeprZxcvbnCtrl::display_meter');
      add_filter('mepr-validate-signup', 'MeprZxcvbnCtrl::validate_signup');
    }
  }

  public static function validate_signup($errors) {
    if(isset($_POST['mp-pass-strength']) && (int)$_POST['mp-pass-strength'] < self::get_required_int()) {
      $errors[] = sprintf(__('This password doesn\'t meet the minimum strength requirement. %s.', 'memberpress'), self::get_required_str());
    }

    return $errors;
  }

  public static function load_scripts($reqs, $is_prod_page, $is_acct_page) {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->global_styles || $is_prod_page) {
      $i18n = array(
        'script_url'  => MEPR_JS_URL.'/zxcvbn.js',
        'very_weak'   => __('Very Weak', 'memberpress'),
        'weak'        => __('Weak', 'memberpress'),
        'medium'      => __('Medium', 'memberpress'),
        'strong'      => __('Strong', 'memberpress'),
        'very_strong' => __('Very Strong', 'memberpress'),
        'required'    => '',
        'indicator'   => __('Password Strength', 'memberpress')
      );

      wp_register_script('mepr-zxcvbn', MEPR_JS_URL.'/zxcvbn-async.js');
      wp_localize_script('mepr-zxcvbn', 'MeprZXCVBN', $i18n);
      wp_enqueue_style('mepr-zxcvbn-css', MEPR_CSS_URL.'/zxcvbn.css');

      $reqs[] = 'mepr-zxcvbn';
    }

    return $reqs;
  }

  public static function display_meter() {
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

    switch($mepr_options->enforce_strong_password) {
      case 'weak':
        return __('Password must be "Weak" or stronger', 'memberpress');
      case 'medium':
        return __('Password must be "Medium" or stronger', 'memberpress');
      case 'strong':
        return __('Password must be "Strong" or stronger', 'memberpress');
      default:
        return '';
    }
  }

  public static function get_required_int() {
    $mepr_options = MeprOptions::fetch();

    switch($mepr_options->enforce_strong_password) {
      case 'weak':
        return 1;
      case 'medium':
        return 2;
        break;
      case 'strong':
        return 3;
      default:
        return 0;
    }
  }
} //End class

