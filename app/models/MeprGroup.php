<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprGroup extends MeprCptModel {
  public static $pricing_page_disabled_str         = '_mepr_group_pricing_page_disabled';
  public static $is_upgrade_path_str               = '_mepr_group_is_upgrade_path';
  public static $group_theme_str                   = '_mepr_group_theme';
  public static $page_button_class_str             = '_mepr_page_button_class';
  public static $page_button_highlighted_class_str = '_mepr_page_button_highlighted_class';
  public static $page_button_disabled_class_str    = '_mepr_page_button_disabled_class';
  public static $products_str                      = '_mepr_products';
  public static $group_page_style_options_str      = '_mepr_group_page_style_options';
  public static $group_page_layout_str             = 'mepr-group-page-layout';
  public static $group_page_style_str              = 'mepr-group-page-style';
  public static $group_page_button_size_str        = 'mepr-group-page-button-size';
  public static $group_page_bullet_style_str       = 'mepr-group-page-bullet-style';
  public static $group_page_font_style_str         = 'mepr-group-page-font-style';
  public static $group_page_font_size_str          = 'mepr-group-page-font-size';
  public static $group_page_button_color_str       = 'mepr-group-page-button-color';
  public static $alternate_group_url_str           = '_mepr-alternate-group-url';
  public static $use_custom_template_str           = '_mepr_use_custom_template';
  public static $custom_template_str               = '_mepr_custom_template';

  public static $nonce_str    = 'mepr_groups_nonce';
  public static $last_run_str = 'mepr_groups_db_cleanup_last_run';

  public static $cpt = 'memberpressgroup';

  public $default_style_options;

  public function __construct($id = null) {
    $this->default_style_options = array(
      'layout'        => 'mepr-vertical',
      'style'         => 'mepr-gray',
      'button_size'   => 'mepr-medium',
      'bullet_style'  => 'mepr-circles',
      'font_style'    => 'custom',
      'font_size'     => 'custom',
      'button_color'  => 'mepr-button-gray'
    );

    $this->load_cpt(
      $id,
      self::$cpt,
      array(
        'pricing_page_disabled' => false,
        'is_upgrade_path' => false,
        'group_theme' => 'minimal_gray_horizontal.css',
        'page_button_class' => '',
        'page_button_highlighted_class' => '',
        'page_button_disabled_class' => '',
        'alternate_group_url' => '',
        'group_page_style_options' => $this->default_style_options,
        'use_custom_template' => false,
        'custom_template' => ''
      )
    );

    // Ensure defaults get folded in
    $this->group_page_style_options = array_merge(
      $this->default_style_options,
      $this->group_page_style_options
    );
  }

  public function validate() {
    $this->validate_is_bool($this->pricing_page_disabled, 'pricing_page_disabled');

    $this->validate_is_bool($this->is_upgrade_path, 'is_upgrade_path');

    $this->validate_is_in_array(
      $this->group_theme,
      self::group_themes(false,true),
      'group_theme'
    );

    if(!empty($this->alternate_group_url)) { $this->validate_is_url($this->alternate_group_url); }

    $this->validate_is_array($this->default_style_options);

    $this->validate_is_bool($this->use_custom_template, 'use_custom_template');

    if($this->use_custom_template) { $this->validate_not_empty($this->custom_template); }

    //No need to validate these at this point
    //'page_button_class' => '',
    //'page_button_highlighted_class' => '',
    //'page_button_disabled_class' => '',
  }

  public function store_meta() {
    $id = $this->ID;

    update_post_meta($id, self::$pricing_page_disabled_str, $this->pricing_page_disabled);
    update_post_meta($id, self::$is_upgrade_path_str, $this->is_upgrade_path);
    update_post_meta($id, self::$group_theme_str, $this->group_theme);
    update_post_meta($id, self::$page_button_class_str, $this->page_button_class);
    update_post_meta($id, self::$page_button_highlighted_class_str, $this->page_button_highlighted_class);
    update_post_meta($id, self::$page_button_disabled_class_str, $this->page_button_disabled_class);
    update_post_meta($id, self::$group_page_style_options_str, $this->group_page_style_options);
    update_post_meta($id, self::$alternate_group_url_str, $this->alternate_group_url);
    update_post_meta($id, self::$use_custom_template_str, $this->use_custom_template);
    update_post_meta($id, self::$custom_template_str, $this->custom_template);
  }

  //$return_type should be a string containing 'objects', 'ids', or 'titles'
  public function products($return_type = 'objects') {
    global $wpdb;

    $query = "
      SELECT ID FROM {$wpdb->posts} AS p
        JOIN {$wpdb->postmeta} AS pm_group_id
          ON p.ID = pm_group_id.post_id
         AND pm_group_id.meta_key = %s
         AND pm_group_id.meta_value = %s
        JOIN {$wpdb->postmeta} AS pm_group_order
          ON p.ID = pm_group_order.post_id
         AND pm_group_order.meta_key = %s
       ORDER BY pm_group_order.meta_value * 1
    "; // * 1 = easy way to cast strings as numbers in SQL

    $query = $wpdb->prepare($query, MeprProduct::$group_id_str, $this->ID, MeprProduct::$group_order_str);

    $res = $wpdb->get_col($query);

    $products = array();

    if(is_array($res)) {
      foreach($res as $product_id) {
        $prd = new MeprProduct($product_id);

        if($return_type == 'objects') {
          $products[] = $prd;
        }
        elseif($return_type == 'ids') {
          $products[] = $prd->ID;
        }
        elseif($return_type == 'titles') {
          $products[] = $prd->post_title;
        }
      }
    }

    return $products;
  }

  //Gets the transaction related to a lifetime membership in a group
  //For use during upgrades from lifetime to subscriptions
  public function get_old_lifetime_txn($new_prd_id, $user_id) {
    $txn_id = false;
    $grp_prds = $this->products('ids');
    $usr_txns = MeprTransaction::get_all_by_user_id($user_id, '', '', true);

    //Try and find the old txn and make sure it's not one belonging
    //to the membership the user just signed up for
    foreach($usr_txns as $txn) {
      if(in_array($txn->product_id, $grp_prds) && $txn->product_id != $new_prd_id) {
        $txn_id = $txn->id;
      }
    }

    if($txn_id) {
      return new MeprTransaction($txn_id);
    }
    else {
      return false;
    }
  }

  public static function cleanup_db() {
    global $wpdb;
    $date = time();
    $last_run = get_option(self::$last_run_str, 0); //Prevents all this code from executing on every page load

    if(($date - $last_run) > 86400) { //Runs at most once a day
      $sq1 = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '".self::$cpt."' AND
                      post_status = 'auto-draft'";
      $q1 = "DELETE
                FROM {$wpdb->postmeta}
                WHERE post_id IN ({$sq1})";
      $q2 = "DELETE
                FROM {$wpdb->posts}
                WHERE post_type = '".self::$cpt."' AND
                      post_status = 'auto-draft'";

      $wpdb->query($q1);
      $wpdb->query($q2);
      update_option(self::$last_run_str, $date);
    }
  }

  public function get_page_template() {
    if($this->use_custom_template) {
      return locate_template($this->custom_template);
    }
    else {
      return locate_template(self::template_search_path());
    }
  }

  public static function template_search_path() {
    return array(
      'page_memberpressgroup.php',
      'single-memberpressgroup.php',
      'page.php',
      'custom_template.php',
      'index.php'
    );
  }

  public function manual_append_price_boxes() {
    return preg_match('~\[mepr-group-price-boxes~',$this->post_content);
  }

  public static function is_group_page($post) {
    if( is_object($post) &&
        ( ( $post->post_type == MeprGroup::$cpt &&
            $grp = new MeprGroup($post->ID) ) ||
          ( preg_match(
              '~\[mepr-group-price-boxes\s+group_id=[\"\\\'](\d+)[\"\\\']~',
              $post->post_content, $m ) &&
            isset($m[1]) &&
            $grp = new MeprGroup($m[1]) ) ) ) {
      return $grp;
    }

    return false;
  }

  public function group_template() {
    if($this->group_theme != 'custom' &&
       false !== ($filename = self::find_group_theme($this->group_theme))) {
      $template_str = file_get_contents($filename);
      preg_match('~MP PLAN TEMPLATE:\s+(\S+)~', $template_str, $m);

      if(isset($m[1])) { return $m[1]; }
    }

    return '';
  }

  public static function group_theme_templates_paths() {
    return MeprHooks::apply_filters('mepr_group_theme_templates_paths', array(MEPR_CSS_PATH . '/plan_templates'));
  }

  public static function group_theme_templates($full_paths=false) {
    $paths = self::group_theme_templates_paths();

    $templates = array();
    foreach($paths as $path) {
      $templates = array_merge($templates, @glob("{$path}/*.css"));
    }

    if(!$full_paths) {
      // TODO: This could cause issues down the line because we're counting on the theme
      //       base name being unique across all search paths for the group theme files.
      foreach($templates as $i => $template) {
        $templates[$i] = basename($template);
      }
    }

    return $templates;
  }

  public static function group_themes_paths() {
    return MeprHooks::apply_filters('mepr_group_themes_paths', array(MEPR_CSS_PATH . '/plans'));
  }

  public static function find_group_theme($theme) {
    $paths = self::group_themes_paths();
    foreach($paths as $path) {
      $filepath = $path . '/' . $theme;
      if(file_exists($filepath)) {
        return $filepath;
      }
    }
    return false;
  }

  public static function group_themes($full_paths=false,$include_custom=false) {
    $paths = self::group_themes_paths();

    $themes = array();
    foreach($paths as $path) {
      $themes = array_merge($themes, @glob("{$path}/*.css"));
    }

    if(!$full_paths) {
      // TODO: This could cause issues down the line because we're counting on the theme
      //       base name being unique across all search paths for the group theme files.
      foreach($themes as $i => $theme) {
        $themes[$i] = basename($theme);
      }
    }

    if($include_custom) {
      $themes[] = 'custom';
    }

    return $themes;
  }
} //End class

