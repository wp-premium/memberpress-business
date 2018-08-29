<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
/*
Integration of bbPress into MemberPress
*/
class MeprBbPressCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    //Used to hide forums & topics
    add_filter('bbp_get_forum_visibility', 'MeprBbPressCtrl::hide_forums', 11, 2);
    add_filter('bbp_get_hidden_forum_ids', 'MeprBbPressCtrl::hide_threads');

    //We're only allowing blocking by forum
    add_filter('mepr-rules-cpts', 'MeprBbPressCtrl::filter_rules_cpts');

    add_action('mepr_account_nav', 'MeprBbPressCtrl::mepr_account_page_links');

    //Don't override bbPress the_content - this is needed when using the forum shortcodes
    add_filter('mepr-pre-run-rule-content', 'MeprBbPressCtrl::dont_block_the_content', 11, 3);
    add_filter('is_bbpress', 'MeprBbPressCtrl::dont_redirect_on_shortcode');
  }

  public static function dont_redirect_on_shortcode($bool) {
    global $wp_query;

    if(empty($wp_query->queried_object->post_content)) { return $bool; }

    if(strpos($wp_query->queried_object->post_content, '[bbp-forum-index') !== false) {
      $_REQUEST['mepr_is_bbp_shortcode'] = true; //Set this so we can later check for it in hide_forums
    }

    return $bool;
  }

  public static function dont_block_the_content($block, $current_post, $uri) {
    if(function_exists('is_bbpress') && is_bbpress()) { return false; }
    return $block;
  }

  public static function mepr_account_page_links($user) {
    if(!class_exists('bbPress')) { return; }

    ?>
      <span class="mepr-nav-item mepr_bbpress_subscriptions">
        <a href="<?php echo bbp_user_profile_url(bbp_get_current_user_id()); ?>" id="mepr-account-bbpress-subscriptions"><?php _e('Forum Profile', 'memberpress'); ?></a>
      </span>
    <?php
  }

  public static function hide_threads($ids) {
    global $wpdb;

    $all_forums = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'forum'");
    $call       = function_exists('debug_backtrace')?debug_backtrace():array();
    $to_hide    = array();

    if(!empty($all_forums)) {
      foreach($all_forums as $forum) {
        $forum = get_post($forum->ID);

        if(MeprRule::is_locked($forum)) {
          $to_hide[] = $forum->ID;
        }
      }
    }

    foreach($call as $c) {
      // We only want to hide in indexes or searches for now
      if( $c['function'] == 'display_topic_index' ||
          $c['function'] == 'display_search' ) {
        $ids = array_merge($ids, $to_hide);
      }
    }

    return $ids;
  }

  //Used mostly for redirecting to the login or unauthorized page if the current forum is locked
  public static function hide_forums($status, $forum_id) {
    if(isset($_REQUEST['mepr_is_bbp_shortcode'])) { return $status; }

    static $already_here;
    if(isset($already_here) && $already_here) { return $status; }
    $already_here = true;

    $mepr_options = MeprOptions::fetch();
    $forum        = get_post($forum_id);
    $uri          = urlencode(esc_url($_SERVER['REQUEST_URI']));

    $actual_forum_id = bbp_get_forum_id();
    $forum = get_post($actual_forum_id);

    //Not a singular view, then let's bail
    if(!is_singular()) { return $status; }

    //Let moderators and keymasters see everything
    if(current_user_can('edit_others_topics')) { return $status; }

    if(!isset($forum)) { return $status; }

    if(MeprRule::is_locked($forum)) {
      if(!headers_sent()) {
        if($mepr_options->redirect_on_unauthorized) {
          $delim = MeprAppCtrl::get_param_delimiter_char($mepr_options->unauthorized_redirect_url);
          $redirect_to = "{$mepr_options->unauthorized_redirect_url}{$delim}mepr-unauth-page={$forum->ID}&redirect_to={$uri}";
        }
        else {
          $redirect_to = $mepr_options->login_page_url("action=mepr_unauthorized&mepr-unauth-page={$forum->ID}&redirect_to=".$uri);
          $redirect_to = (MeprUtils::is_ssl())?str_replace('http:', 'https:', $redirect_to):$redirect_to;
        }
        MeprUtils::wp_redirect($redirect_to);
        exit;
      }
      else {
        $status = 'hidden';
      }
    }

    return $status;
  }

  public static function filter_rules_cpts($cpts) {
    unset($cpts['reply']);
    unset($cpts['topic']);

    return $cpts;
  }
} //End class
