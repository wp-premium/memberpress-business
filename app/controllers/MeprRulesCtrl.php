<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprRulesCtrl extends MeprCptCtrl {
  public function load_hooks() {
    $mepr_options = MeprOptions::fetch();

    //Protection stuff
    if($mepr_options->redirect_method == 'init') {
      add_action('init', 'MeprRulesCtrl::rule_redirection', 3);
    }
    else {
      // wp hook may be even better than template_redirect here
      add_action('template_redirect', 'MeprRulesCtrl::rule_redirection', 3);
    }

    // add_filter('the_content_feed', 'MeprRulesCtrl::rule_content', 999999, 1); //I think the_content is called before the_content_feed, so this is redundant
    add_filter('the_content', 'MeprRulesCtrl::rule_content', 999999, 1);
    add_action('admin_init', 'MeprRulesCtrl::admin_rule_redirection', 3);
    add_filter('comments_template', 'MeprRulesCtrl::rule_comments');
    add_action('mod_rewrite_rules', 'MeprRulesCtrl::mod_rewrite_rules');

    //All other stuff
    add_filter('bulk_actions-edit-memberpressrule', 'MeprRulesCtrl::disable_bulk');
    add_filter('post_row_actions', 'MeprRulesCtrl::disable_row', 10, 2);
    add_action('admin_enqueue_scripts', 'MeprRulesCtrl::enqueue_scripts');
    add_action('admin_init', 'MeprRule::cleanup_db'); //Clear out all unused auto-save's
    add_action('manage_posts_custom_column', 'MeprRulesCtrl::custom_columns', 10, 2);
    add_filter('manage_edit-memberpressrule_columns', 'MeprRulesCtrl::columns');
    add_action('save_post', 'MeprRulesCtrl::save_postdata');
    add_action('delete_post', 'MeprRulesCtrl::delete_access_rules', 10);
    add_action('wp_ajax_mepr_show_content_dropdown', 'MeprRulesCtrl::display_content_dropdown');
    add_action('wp_ajax_mepr_remove_access_condition', 'MeprRulesCtrl::remove_access_condition');
    add_action('wp_ajax_mepr_rule_content_search', 'MeprRulesCtrl::ajax_content_search');
    add_filter('default_title', 'MeprRulesCtrl::get_page_title_code');

    // Add virtual capabilities
    add_filter('user_has_cap', 'MeprRulesCtrl::authorized_cap', 10, 3);
    add_filter('user_has_cap', 'MeprRulesCtrl::product_authorized_cap', 10, 3);
    add_filter('user_has_cap', 'MeprRulesCtrl::rule_authorized_cap', 10, 3);
    add_filter('user_has_cap', 'MeprRulesCtrl::active_cap', 10, 3);

    add_shortcode('mepr-rule', 'MeprRulesCtrl::rule_shortcode');
    add_shortcode('mepr-active', 'MeprRulesCtrl::active_shortcode');
    add_shortcode('mepr-unauthorized-message', 'MeprRulesCtrl::unauthorized_message_shortcode');

    add_shortcode('mepr-show', 'MeprRulesCtrl::show_shortcode');
    add_shortcode('mepr-hide', 'MeprRulesCtrl::hide_shortcode');

    // Cleanup list view
    add_filter('views_edit-'.MeprRule::$cpt, 'MeprAppCtrl::cleanup_list_view' );
  }

  public function register_post_type() {
    register_post_type( MeprRule::$cpt, array(
        'labels' => array(
          'name' => __('Rules', 'memberpress'),
          'singular_name' => __('Rule', 'memberpress'),
          'add_new_item' => __('Add New Rule', 'memberpress'),
          'edit_item' => __('Edit Rule', 'memberpress'),
          'new_item' => __('New Rule', 'memberpress'),
          'view_item' => __('View Rule', 'memberpress'),
          'search_items' => __('Search Rules', 'memberpress'),
          'not_found' => __('No Rules found', 'memberpress'),
          'not_found_in_trash' => __('No Rules found in Trash', 'memberpress'),
          'parent_item_colon' => __('Parent Rule:', 'memberpress')
        ),
        'public' => false,
        'show_ui' => true, //MeprUpdateCtrl::is_activated(),
        'show_in_menu' => 'memberpress',
        'capability_type' => 'page',
        'hierarchical' => false,
        'register_meta_box_cb' => 'MeprRulesCtrl::add_meta_boxes',
        'rewrite' => false,
        'supports' => array('title')
      )
    );
  }

  //Set an initial page title
  public static function get_page_title_code($title) {
    global $current_screen;

    if(empty($title) && $current_screen->post_type == MeprRule::$cpt) {
      return __('All Content: ', 'memberpress');
    }
    else {
      return $title;
    }
  }

  public static function columns($columns) {
    $columns = array(
      "cb" => "<input type=\"checkbox\" />",
      "ID" => __("ID", 'memberpress'),
      "title" => __("Title", 'memberpress'),
      "rule-type" => __("Type", 'memberpress'),
      "rule-content" => __("Content", 'memberpress'),
      "rule-products" => __("Access", 'memberpress')
    );

    return $columns;
  }

  public static function custom_columns($column, $rule_id) {
    $rule = new MeprRule($rule_id);

    if($rule->ID !== null) {
      $rule_contents = MeprRule::get_contents_array($rule->mepr_type);
      $types = MeprRule::get_types();

      if("ID" == $column) {
        echo $rule->ID;
      }
      elseif("rule-type" == $column and isset($types[$rule->mepr_type])) {
        echo $types[$rule->mepr_type];
      }
      elseif("rule-content" == $column and $rule->mepr_type != 'custom' and
              isset($rule_contents[$rule->mepr_content])) {
        echo $rule_contents[$rule->mepr_content];
      }
      elseif("rule-content" == $column and $rule->mepr_type == 'custom' and
              isset($rule->mepr_content)) {
        echo $rule->mepr_content;
      }
      elseif("rule-content" == $column and
              strstr($rule->mepr_type, 'all_') !== false and
              isset($rule->mepr_content)) {
        echo __('Except', 'memberpress') . ': ' . $rule->mepr_content;
      }
      elseif("rule-products" == $column) {
        echo implode(', ', $rule->get_formatted_accesses());
      }
    }
  }

  public static function rule_comments($template = '') {
    $current_post = MeprUtils::get_current_post();
    $mepr_options = MeprOptions::fetch();

    if(isset($current_post)) {
      if(MeprRule::is_locked($current_post)) {
        return MeprView::file('/shared/unauthorized_comments');
      }
    }

    return $template;
  }

  /** Used to redirect unauthorized visitors if redirect_on_unauthorized is selected in MeprOptions or
  if we're protecting a WP controlled-URI. */
  public static function rule_redirection() {
    global $post;

    //Prevents us double matching a URI and causing a redirect loop
    if(isset($_GET['action']) && $_GET['action'] == 'mepr_unauthorized') {
      return;
    }

    $uri = esc_url($_SERVER['REQUEST_URI']);
    $mepr_options = MeprOptions::fetch();
    $delim = MeprAppCtrl::get_param_delimiter_char($mepr_options->unauthorized_redirect_url);
    $is_ssl = MeprUtils::is_ssl();

    //Add this filter to allow external resources
    //to control whether to redirect away from this content
    //if the resource sets the filter to FALSE then no redirect will occur
    if(!MeprHooks::apply_filters('mepr-pre-run-rule-redirection', true, $uri, $delim)) { return; }

    // Let's check the URI's first ok?
    // This is here to perform an unauthorized redirection based on the uri
    if(MeprRule::is_uri_locked($uri)) {
      if($mepr_options->redirect_on_unauthorized) { //Send to unauth page
        $redirect_url = "{$mepr_options->unauthorized_redirect_url}{$delim}action=mepr_unauthorized&redirect_to=".urlencode($uri);
      }
      else { //Send to login page
        $redirect_url = $mepr_options->login_page_url("action=mepr_unauthorized&redirect_to=".urlencode($uri));
      }

      //Handle SSL
      $redirect_url = ($is_ssl)?str_replace('http:', 'https:', $redirect_url):$redirect_url;
      MeprUtils::wp_redirect($redirect_url);
      exit;
    }

    // If the URI isn't protected, let's check the other Rules
    if($mepr_options->redirect_on_unauthorized) {
      $do_redirect = MeprHooks::apply_filters('mepr-rule-do-redirection', self::should_do_redirect());

      if( (!is_singular() && $do_redirect) ||
          ($do_redirect && isset($post) && MeprRule::is_locked($post)) ||
          (!is_user_logged_in() && isset($post) && $post->ID == $mepr_options->account_page_id) ) {
        $redirect_url = "{$mepr_options->unauthorized_redirect_url}{$delim}mepr-unauth-page={$post->ID}&redirect_to=".urlencode($uri);

        //Handle SSL
        $redirect_url = ($is_ssl)?str_replace('http:', 'https:', $redirect_url):$redirect_url;
        MeprUtils::wp_redirect($redirect_url);
        exit;
      }
    }
  }

  //Allow control of the admin dashboard URL's too
  public static function admin_rule_redirection() {
    $uri = esc_url($_SERVER['REQUEST_URI']);
    $mepr_options = MeprOptions::fetch();
    $delim = MeprAppCtrl::get_param_delimiter_char($mepr_options->unauthorized_redirect_url);

    // This performs an unauthorized redirection based on the uri
    if(MeprRule::is_uri_locked($uri)) {
      if($mepr_options->redirect_on_unauthorized) { //Send to unauth page
        $redirect_url = "{$mepr_options->unauthorized_redirect_url}{$delim}action=mepr_unauthorized&redirect_to=".urlencode($uri);
      }
      else { //Send to login page
        $redirect_url = $mepr_options->login_page_url("action=mepr_unauthorized&redirect_to=".urlencode($uri));
      }

      //Handle SSL
      $redirect_url = (MeprUtils::is_ssl())?str_replace('http:', 'https:', $redirect_url):$redirect_url;
      MeprUtils::wp_redirect($redirect_url);
      exit;
    }
  }

  public static function should_do_redirect() {
    global $wp_query;
    $mepr_options = MeprOptions::fetch();

    if(!empty($wp_query->posts) && $mepr_options->redirect_non_singular) {
      //If even one post on this non-singular page is protected, let's redirect brotha
      foreach($wp_query->posts as $post)
        if(MeprRule::is_locked($post))
          return true;
    }

    return is_singular();
  }

  /** Used to replace content for unauthorized visitors if redirect_on_unauthorized is not selected in MeprOptions. */
  public static function rule_content($content) {
    $current_post = MeprUtils::get_current_post();

    //This isn't a post? Just return the content then
    if($current_post === false) { return $content; }

    //WARNING the_content CAN be run more than once per page load
    //so this static var prevents stuff from happening twice
    //like cancelling a subscr or resuming etc...
    static $already_run = array();
    static $new_content = array();
    static $content_length = array();

    //Init this posts static values
    if(!isset($new_content[$current_post->ID]) || empty($new_content[$current_post->ID])) {
      $already_run[$current_post->ID] = false;
      $new_content[$current_post->ID] = '';
      $content_length[$current_post->ID] = -1;
    }

    if($already_run[$current_post->ID] && strlen($content) == $content_length[$current_post->ID]) {
      return $new_content[$current_post->ID];
    }

    $content_length[$current_post->ID] = strlen($content);
    $already_run[$current_post->ID] = true;

    //Get the URI
    $uri = $_SERVER['REQUEST_URI'];

    //Add this filter to allow external resources
    //to control whether to show or hide this content
    //if the resource sets the filter to FALSE then it will not be protected
    if(!MeprHooks::apply_filters('mepr-pre-run-rule-content', true, $current_post, $uri)) {
      //See notes above
      $new_content[$current_post->ID] = $content;
      return $new_content[$current_post->ID];
    }

    if(MeprRule::is_locked($current_post) || (MeprRule::is_uri_locked($uri))) {
      $content = do_shortcode(self::unauthorized_message($current_post));
    }
    else {
      //The user is allowed to see this content, but let's give developers one last chance to
      //block it if necessary - will be very helpful for magazine style membership sites
      //return TRUE here to block the content from this user
      if(MeprHooks::apply_filters('mepr-last-chance-to-block-content', false, $current_post, $uri)) {
        $content = do_shortcode(self::unauthorized_message($current_post));
      }
    }

    //See notes above
    $new_content[$current_post->ID] = $content;
    return $new_content[$current_post->ID];
  }

  public static function unauthorized_message_shortcode($atts = '') {
    $mepr_options = MeprOptions::fetch();
    $message = '';

    if( isset($_REQUEST['mepr-unauth-page']) &&
        is_numeric($_REQUEST['mepr-unauth-page']) &&
        $post = get_post(esc_html($_REQUEST['mepr-unauth-page'])) ) {
      $message = self::unauthorized_message($post);
    }
    elseif(isset($GLOBALS['post'])) {
      $message = self::unauthorized_message($GLOBALS['post']);
    }
    else {
      $message = wpautop($mepr_options->unauthorized_message);
    }

    return do_shortcode($message);
  }

  public static function unauthorized_message($post) {
    $mepr_options = MeprOptions::fetch();
    $unauth = MeprRule::get_unauth_settings_for($post);

    static $login_form_shown = false;
    $show_login = ($unauth->show_login && !$login_form_shown);

    //if this is a singular page, then allow it to be shown more than once
    //it won't literally be shown on the page more than once, but in case something
    //calls the_content filter during an earlier hook, we'll want to make sure the form shows
    //up on the page itself still.
    if($show_login && !is_singular()) { $login_form_shown = true; }

    try {
      $login_ctrl = MeprCtrlFactory::fetch('login');
      $form = MeprHooks::apply_filters('mepr-unauthorized-login-form', $login_ctrl->render_login_form(null, null, true), $post);
    }
    catch(Exception $e) {
      $form = '<a href="'.$mepr_options->login_page_url().'">'.__('Login', 'memberpress').'</a>';
    }

    ob_start();
    MeprView::render('/shared/unauthorized_message', get_defined_vars());
    $content = ob_get_clean();

    // TODO: oEmbed still not working for some strange reason
    return MeprHooks::apply_filters('mepr-unauthorized-content', $content, $post);
  }

  public static function add_meta_boxes() {
    add_meta_box("memberpress-rule-meta", __("Content & Access", "memberpress"), "MeprRulesCtrl::rule_meta_box", MeprRule::$cpt, "normal", "high");
    add_meta_box("memberpress-rule-drip", __("Drip / Expiration", "memberpress"), "MeprRulesCtrl::rule_drip_meta_box", MeprRule::$cpt, "normal", "high");
    add_meta_box("memberpress-rule-unauth", __("Unauthorized Access", "memberpress"), "MeprRulesCtrl::rule_unauth_meta_box", MeprRule::$cpt, "normal", "high");
  }

  public static function save_postdata($post_id) {
    $post = get_post($post_id);

    if(!wp_verify_nonce((isset($_POST[MeprRule::$mepr_nonce_str]))?$_POST[MeprRule::$mepr_nonce_str]:'', MeprRule::$mepr_nonce_str.wp_salt())) {
      return $post_id; //Nonce prevents meta data from being wiped on move to trash
    }

    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

    if(defined('DOING_AJAX')) { return; }

    if(!empty($post) && $post->post_type == MeprRule::$cpt) {
      $rule = new MeprRule($post_id);
      $rule->mepr_type           = sanitize_text_field($_POST[MeprRule::$mepr_type_str]);
      $rule->mepr_content        = (('partial' != $_POST[MeprRule::$mepr_type_str] && isset($_POST[MeprRule::$mepr_content_str])) ? sanitize_text_field($_POST[MeprRule::$mepr_content_str]) : '');
      $rule->drip_enabled        = isset($_POST[MeprRule::$drip_enabled_str]);
      $rule->drip_amount         = sanitize_text_field($_POST[MeprRule::$drip_amount_str]);
      $rule->drip_unit           = sanitize_text_field($_POST[MeprRule::$drip_unit_str]);
      $rule->drip_after          = sanitize_text_field($_POST[MeprRule::$drip_after_str]);
      $rule->drip_after_fixed    = sanitize_text_field($_POST[MeprRule::$drip_after_fixed_str]);
      $rule->expires_enabled     = isset($_POST[MeprRule::$expires_enabled_str]);
      $rule->expires_amount      = sanitize_text_field($_POST[MeprRule::$expires_amount_str]);
      $rule->expires_unit        = sanitize_text_field($_POST[MeprRule::$expires_unit_str]);
      $rule->expires_after       = sanitize_text_field($_POST[MeprRule::$expires_after_str]);
      $rule->expires_after_fixed = sanitize_text_field($_POST[MeprRule::$expires_after_fixed_str]);
      $rule->unauth_excerpt_type = sanitize_text_field($_POST[MeprRule::$unauth_excerpt_type_str]);
      $rule->unauth_excerpt_size = sanitize_text_field($_POST[MeprRule::$unauth_excerpt_size_str]);
      $rule->unauth_message_type = sanitize_text_field($_POST[MeprRule::$unauth_message_type_str]);
      $rule->unauth_message      = wp_kses_post(wp_unslash($_POST[MeprRule::$unauth_message_str]));
      $rule->unauth_login        = sanitize_text_field($_POST[MeprRule::$unauth_login_str]);
      $rule->auto_gen_title      = ($_POST[MeprRule::$auto_gen_title_str] == 'true');

      $rule->is_mepr_content_regexp = isset($_POST[MeprRule::$is_mepr_content_regexp_str]);

      $rule->store_meta();

      // Delete rules first then add them back below
      MeprRuleAccessCondition::delete_all_by_rule($post_id);

      // Let's store the access rules
      if(isset($_POST['mepr_access_row']) && !empty($_POST['mepr_access_row'])) {
        foreach($_POST['mepr_access_row']['type'] as $index => $access_type) {
          $rule_access_condition = new MeprRuleAccessCondition($_POST['mepr_access_row']['rule_access_condition_id'][$index]);
          $rule_access_condition->rule_id = $post_id;
          $rule_access_condition->access_type = sanitize_text_field($access_type);
          $rule_access_condition->access_operator = sanitize_text_field($_POST['mepr_access_row']['operator'][$index]);
          $rule_access_condition->access_condition = sanitize_text_field($_POST['mepr_access_row']['condition'][$index]);
          $rule_access_condition->store();
        }
      }
    }
  }

  public static function delete_access_rules($post_id) {
    $rule = new MeprRule($post_id);
    $rule->delete_access_conditions();
  }

  public static function rule_meta_box() {
    global $post_id;
    $mepr_options = MeprOptions::fetch();

    $rule = new MeprRule($post_id);
    $rule_access_conditions = $rule->access_conditions();
    $server = strtolower($_SERVER['SERVER_SOFTWARE']);

    //If mod rewrite rules are disabled, then we don't care
    if($mepr_options->disable_mod_rewrite) {
      $server = 'whocares';
    }
    else if(preg_match('/(apache|litespeed)/', $server)) { //LiteSpeed is essentially the same as Apache, only it claims to be twice as fast
      $server = 'apache';
      $htaccess = ABSPATH . ".htaccess";
      $htaccess_writable = (file_exists($htaccess) and is_writable($htaccess));
    }
    else if(preg_match('/nginx/',$server)) {
      $server = 'nginx';
    }
    else {
      $server = 'unknown';
    }

    MeprView::render('/admin/rules/form', get_defined_vars());
  }

  public static function rule_drip_meta_box() {
    global $post_id;

    $rule = new MeprRule($post_id);

    MeprView::render('/admin/rules/drip_form', get_defined_vars());
  }

  public static function rule_unauth_meta_box() {
    global $post_id;

    $rule = new MeprRule($post_id);

    MeprView::render('/admin/rules/unauth_meta_box', get_defined_vars());
  }

  public static function display_content_dropdown() {
    if(!isset($_POST['field_name']) || !isset($_POST['type'])) {
      die(__('Error', 'memberpress'));
    }

    if(MeprUtils::is_logged_in_and_an_admin()) {
      MeprRulesHelper::content_dropdown($_POST['field_name'], '', $_POST['type']);
    }

    die();
  }

  public static function remove_access_condition() {
    if(!isset($_POST['rule_access_condition_id'])) {
      wp_die(__('Error', 'memberpress'));
    }

    if(MeprUtils::is_logged_in_and_an_admin()) {
      $rule_access_condition = new MeprRuleAccessCondition($_POST['rule_access_condition_id']);
      $rule_access_condition->destroy();
    }

    wp_die();
  }

  public static function disable_row($actions, $post) {
    global $current_screen;

    if(!isset($current_screen->post_type) || $current_screen->post_type != MeprRule::$cpt) {
      return $actions;
    }

    unset($actions['inline hide-if-no-js']); //Hides quick-edit

    return $actions;
  }

  public static function disable_bulk($actions) {
    unset($actions['edit']); //disables bulk edit

    return $actions;
  }

  public static function enqueue_scripts($hook) {
    global $current_screen;

    $wp_scripts = new WP_Scripts();
    $ui = $wp_scripts->query('jquery-ui-core');
    $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";

    if($current_screen->post_type == MeprRule::$cpt) {
      $rules_json = array(
        'mepr_no_products_message' => __('Please select at least one Membership before saving.', 'memberpress'),
        'types' => MeprRule::get_types(),
        'access_row' => array(
          'membership' => array(
            'row_tpl' => MeprRulesHelper::access_row_string(new MeprRuleAccessCondition(array('access_type' => 'membership')),1),
            'types_tpl' => MeprRulesHelper::access_types_dropdown_string('membership'),
            'operator_tpl' => MeprRulesHelper::access_operators_dropdown_string('membership'),
            'condition_tpl' => MeprRulesHelper::access_conditions_dropdown_string('membership')
          ),
          'member' => array(
            'row_tpl' => MeprRulesHelper::access_row_string(new MeprRuleAccessCondition(array('access_type' => 'member')),1),
            'types_tpl' => MeprRulesHelper::access_types_dropdown_string('member'),
            'operator_tpl' => MeprRulesHelper::access_operators_dropdown_string('member'),
            'condition_tpl' => MeprRulesHelper::access_conditions_dropdown_string('member')
          ),
          'blank' => array(
            'row_tpl' => MeprRulesHelper::access_row_string(new MeprRuleAccessCondition(),1),
            'types_tpl' => MeprRulesHelper::access_types_dropdown_string(),
            'operator_tpl' => MeprRulesHelper::access_operators_dropdown_string(),
            'condition_tpl' => MeprRulesHelper::access_conditions_dropdown_string()
          )
        )
      );

      wp_register_style('mepr-jquery-ui-smoothness', $url);
      wp_enqueue_style('jquery-ui-timepicker-addon', MEPR_CSS_URL.'/jquery-ui-timepicker-addon.css', array('mepr-jquery-ui-smoothness'));
      wp_register_script('mepr-timepicker-js', MEPR_JS_URL.'/jquery-ui-timepicker-addon.js', array('jquery-ui-datepicker'));
      wp_register_script('mepr-date-picker-js', MEPR_JS_URL.'/date_picker.js', array('mepr-timepicker-js'), MEPR_VERSION);
      wp_register_script('rule-form-validator', '//cdnjs.cloudflare.com/ajax/libs/jquery-form-validator/2.3.26/jquery.form-validator.min.js', array('jquery'), '2.3.26');
      wp_dequeue_script('autosave'); //Disable auto-saving
      //Need mepr-rules-js to load in the footer since this script doesn't fully use document.ready()
      wp_enqueue_script('mepr-rules-js', MEPR_JS_URL.'/admin_rules.js', array('jquery','jquery-ui-autocomplete','mepr-date-picker-js','rule-form-validator'), MEPR_VERSION.time(), true);
      wp_register_style('mepr-simplegrid', MEPR_CSS_URL.'/vendor/simplegrid.css', array(), MEPR_VERSION);
      wp_enqueue_style('mepr-rules-css', MEPR_CSS_URL.'/admin-rules.css', array('mepr-simplegrid'), MEPR_VERSION);
      wp_localize_script('mepr-rules-js', 'MeprRule', $rules_json);
      wp_enqueue_script('mepr-helpers', MEPR_JS_URL . '/mphelpers.js', array('suggest'), MEPR_VERSION);
    }
  }

  public static function mod_rewrite_rules($rules) {
    $mepr_options = MeprOptions::fetch();

    //If disabled mod_rewrite is checked let's not go on
    if($mepr_options->disable_mod_rewrite) { return $rules; }

    $rule_uri = MEPR_URL . '/lock.php';
    $rule_path = preg_replace('#^(https?:)?//[^/]+#','',$rule_uri); // grab the root
    $subdir = preg_replace("#^https?://[^/]+#", '', site_url());
    $mepr_rules = "\n";
    $mepr_rules .= "# BEGIN MemberPress Rules\n";
    $mepr_rules .= "<IfModule mod_rewrite.c>\n\n";

    // Make sure there's been a cookie set for us to access the file
    $mepr_rules .= "RewriteCond %{HTTP_COOKIE} mplk=([a-zA-Z0-9]+)\n";

    // See if there's also a rule file for the rule hash
    $mepr_rules .= "RewriteCond " . MeprRule::rewrite_rule_file_dir(true) . "/%1 -f\n";
    // If rule hash exists in query string, there's a rule file and they match then short circuit to the actual url
    $mepr_rules .= "RewriteRule ^(.*)$ - [L]\n\n";
    // If the url is the lock url then don't lock it or we'll end up in an infinite redirect
    // Don't need this now that we're bypassing php files alltogether
    //$mepr_rules .= "RewriteRule memberpress\/lock\.php$ - [L]\n";

    // Directories that we shouldn't allow to be protected
    $no_protect_dirs = MeprHooks::apply_filters('mepr_rewrite_rules_no_protect_dirs', array('wp-admin','wp-includes','wp-content/plugins','wp-content/themes'), $rules);
    $npstr = implode('|', $no_protect_dirs);
    $mepr_rules .= 'RewriteCond %{REQUEST_URI} !^/('.$npstr.")\n";

    // File types that we will allow to be protected
    // Eventually we can maybe make this configurable by the user ...
    $protect_types = MeprHooks::apply_filters('mepr_rewrite_rules_protect_types', array('zip','gz','tar','rar','doc','docx','xls','xlsx','xlsm','pdf','mp4','m4v','mp3','ts','key','m3u8'), $rules);
    $ptstr = implode('|', $protect_types);
    $mepr_rules .= 'RewriteCond %{REQUEST_URI} \.('.strtolower($ptstr).'|'.strtoupper($ptstr).")$\n";

    // All else fails ... run it through lock.php to see if it's protected
    $mepr_rules .= "RewriteRule . {$rule_path} [L]\n\n";
    $mepr_rules .= "</IfModule>\n";
    $mepr_rules .= "# END MemberPress Rules\n";

    $mepr_rules = MeprHooks::apply_filters('mepr_rewrite_rules', $mepr_rules, $rules);

    // Mepr rules must appear *AFTER* wp's rules because we
    // don't know how wp will handle the uri unless its a file
    return $rules.$mepr_rules;
  }

  public static function rule_shortcode($atts, $content='') {
    return self::protect_shortcode_content($atts, $content, 'mp-rule');
  }

  public static function active_shortcode($atts, $content='') {
    return self::protect_shortcode_content($atts, $content);
  }

  public static function show_shortcode($atts, $content='') {
    return self::protect_shortcode_content($atts, $content, 'mepr-show');
  }

  public static function hide_shortcode($atts, $content='') {
    return self::protect_shortcode_content($atts, $content, 'mepr-hide');
  }

  public static function protect_shortcode_content($atts, $content='', $shortcode_type='mp-active') {
    $mepr_options = MeprOptions::fetch();

    //Allow single level shortcode nesting
    //This only works if the inner shortcode does NOT have an ending tag
    $content = do_shortcode($content);

    if($shortcode_type==='mepr-show') {
      $hide_if_allowed = false;
    }
    elseif($shortcode_type==='mepr-hide') {
      $hide_if_allowed = true;
    }
    else {
      $hide_if_allowed = (
        ((isset($atts['hide']) && trim($atts['hide']) == 'true') ||
         (isset($atts['ifallowed']) && trim($atts['ifallowed']) == 'hide'))
      );
    }

    $unauth = '';
    if(isset($atts['unauth'])) {
      if(trim($atts['unauth'])=='message' || trim($atts['unauth'])=='both') {
        if(isset($atts['unauth_message'])) {
          $unauth = '<div class="mepr_error">'.trim($atts['unauth_message']).'</div>';
        }
        else {
          $unauth = '<div class="mepr_error">'.$mepr_options->unauthorized_message.'</div>';
        }
      }

      if(trim($atts['unauth'])=='login' || trim($atts['unauth'])=='both') {
        try {
          $login_ctrl = MeprCtrlFactory::fetch('login');
          $unauth .= '<div>'.$login_ctrl->render_login_form().'</div>';
        }
        catch(Exception $e) {
          $unauth = '<div><a href="'.$mepr_options->login_page_url().'">'.__('Login', 'memberpress').'</a></div>';
        }
      }
    }

    $allowed = false;
    if(isset($atts['if']) && preg_match('/^logged[ _-]?in$/', $atts['if'])) {
      $allowed = MeprUtils::is_user_logged_in();
    }
    else if(isset($atts['if']) && preg_match('/^logged[ _-]?out$/', $atts['if'])) {
      $allowed = !MeprUtils::is_user_logged_in();
    }
    else {
      //Check if we've been given sanitary input, if not this shortcode
      //is no good so let's return the full content here
      if(MeprUtils::is_mepr_admin()) { return ($hide_if_allowed?$unauth:$content); }

      if(MeprUtils::is_user_logged_in()) {
        if($shortcode_type=='mp-rule') {
          $allowed = (
            (isset($atts['id']) && current_user_can('mepr-active',"rule: {$atts['id']}")) ||
            (isset($atts['ids']) && current_user_can('mepr-active',"rules: {$atts['ids']}"))
          );
        }
        else {
          $allowed = (
            (isset($atts['if']) && current_user_can('mepr-active',$atts['if'])) ||
            (isset($atts['id']) && current_user_can('mepr-active',$atts['id'])) ||
            (isset($atts['ids']) && current_user_can('mepr-active',$atts['ids'])) ||
            (isset($atts['rule']) && current_user_can('mepr-active',"rule: {$atts['rule']}")) ||
            (isset($atts['rules']) && current_user_can('mepr-active',"rules: {$atts['rules']}")) ||
            (isset($atts['product']) && current_user_can('mepr-active',"product: {$atts['product']}")) ||
            (isset($atts['products']) && current_user_can('mepr-active',"products: {$atts['products']}")) ||
            (isset($atts['membership']) && current_user_can('mepr-active',"membership: {$atts['membership']}")) ||
            (isset($atts['memberships']) && current_user_can('mepr-active',"membership: {$atts['memberships']}"))
          );
        }
      }
    }

    return ((($allowed && !$hide_if_allowed) || (!$allowed && $hide_if_allowed))?$content:$unauth);
  }

  /* This will only work once $post is in place in the wp request flow */
  /* Will support dashes, underscores, full plugin name, short plugin name and authorized or auth */
  public static function authorized_cap($caps, $cap, $args) {
    $regex = '(memberpress|mepr)[-_]auth(orized)?';

    if(!isset($cap[0]) || !preg_match("/^{$regex}$/", $cap[0])) {
      return $caps;
    }

    $caps[$cap[0]] = 1;
    $current_post = MeprUtils::get_current_post();

    // General MemberPress Authorized for this page
    if(($current_post !== false && MeprRule::is_locked($current_post)) ||
       MeprRule::is_uri_locked($_SERVER['REQUEST_URI'])) {
      unset($caps[$cap[0]]);
    }

    return $caps;
  }

  /* membership based capabilities */
  public static function product_authorized_cap($caps, $cap, $args) {
    $regex = '(memberpress|mepr)[-_](product|membership)[-_]auth(orized)?[-_](\d+)';

    if(!isset($cap[0]) || !preg_match("/^{$regex}$/i", $cap[0], $m)) {
      return $caps;
    }

    //User is most likely a guest, so they don't have access to whatever we're doing here
    if(!isset($args[1]) || !$args[1]) {
      return $caps;
    }

    $user = new MeprUser($args[1]);
    $ids = $user->active_product_subscriptions();

    if(MeprUtils::is_mepr_admin() || in_array($m[4], $ids)) { $caps[$cap[0]] = 1; }

    return $caps;
  }

  /* rule based capabilities */
  public static function rule_authorized_cap($caps, $cap, $args) {
    $regex = '(memberpress|mepr)[-_]rule[-_]auth(orized)?[-_](\d+)';

    if(!isset($cap[0]) || !preg_match("/^{$regex}$/i", $cap[0], $m)) {
      return $caps;
    }

    // User is most likely a guest, so they don't have access to whatever we're doing here
    if(!isset($args[1]) || !$args[1]) {
      return $caps;
    }

    $rule_id = $m[3];

    $user = new MeprUser($args[1]);
    $rule = new MeprRule($rule_id);

    if($rule->ID <= 0 || !$rule->has_dripped() || $rule->has_expired()) { return $caps; }
    if($user->has_access_from_rule($rule_id)) { $caps[$cap[0]] = 1; }

    return $caps;
  }

  /* Is the user active on any membership, one specific rule or one specific membership? */
  public static function active_cap($caps, $cap, $args) {
    $active_str = 'mepr-active';

    if(!isset($cap[0]) || !preg_match("/^{$active_str}$/", $cap[0])) {
      return $caps;
    }

    //User is most likely a guest, so they don't have access to whatever we're doing here
    if(!isset($args[1]) || !$args[1]) {
      return $caps;
    }

    $user = new MeprUser($args[1]);
    $ids = $user->active_product_subscriptions();

    if(MeprUtils::is_mepr_admin($user->ID)) {
      $caps[$active_str] = 1;
    }
    else if(is_array($ids) && !empty($ids)) {
      // membership specific active
      if(isset($args[2])) {
        // If it's a membership then check that it's in the active membership subscriptions array
        if(is_numeric($args[2])) {
          if(in_array($args[2],$ids)) {
            $caps[$active_str] = 1;
          }
        }
        // If it's spelled out as a product or membership do the same thing here
        else if(preg_match('/^((product|membership)s?\s*[=:_-]?\s*)?((\d+\s*,\s*)*\d+)$/i',$args[2],$m)) {
          $product_ids = array_map('trim', explode(',',$m[3]));
          if(is_array($product_ids) && !empty($product_ids) &&
             ($intersect = array_intersect($product_ids, $ids)) &&
             !empty($intersect)) {
            $caps[$active_str] = 1;
          }
        }
        // If it's an array then check that it's in the active membership subscriptions array
        else if(preg_match('/^rules?\s*[=:_-]?\s*((\d+\s*,\s*)*\d+)$/i',$args[2],$m)) {
          $product_ids = array();
          $rule_ids = array_map('trim', explode(',',$m[1]));

          if(is_array($rule_ids) && !empty($rule_ids)) {
            foreach($rule_ids as $rule_id) {
              $rule = new MeprRule($rule_id);
              if($rule->ID <= 0 || !$rule->has_dripped() || $rule->has_expired()) { continue; }
              if($user->has_access_from_rule($rule_id)) {
                $caps[$active_str] = 1;
                break;
              }
            }
          }
        }
      }
      else {
        $caps[$active_str] = 1;
      }
    }

    return $caps;
  }

  public static function ajax_content_search() {
    //Array( [action] => mepr_rule_content_search [type] => single_post [term] => you)

    $data = MeprRule::search_content( $_REQUEST['type'], $_REQUEST['term'] );
    die(json_encode($data));
  }
} //End class
