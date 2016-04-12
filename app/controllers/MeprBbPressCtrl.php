<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
/*
Integration of bbPress into MemberPress
*/
class MeprBbPressCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    //Protect the content directly, should they somehow get a direct link to these
    add_filter('bbp_get_reply_content', 'MeprBbPressCtrl::bbpress_rule_content', 999999, 2);
    add_filter('bbp_get_topic_content', 'MeprBbPressCtrl::bbpress_rule_content', 999999, 2);

    //Hide forums/topics from the loop
    add_action('get_template_part_loop', 'MeprBbPressCtrl::hide_forums_from_loop', 11, 2);
    add_action('get_template_part_loop', 'MeprBbPressCtrl::hide_topics_from_loop', 11, 2);

    add_filter('mepr-rules-cpts', 'MeprBbPressCtrl::filter_rules_cpts');
  }

  public static function bbpress_rule_content($content, $id) {
    //We only allow restriction on a per-forum basis currently
    //So let's get the current forum's id and check if it's protected
    $forum_id = bbp_get_forum_id();

    if(!$forum_id) { return $content; }

    $post = get_post($forum_id);

    if(!isset($post) || !MeprRule::is_locked($post)) { return $content; }

    //If we made it here, this is protected
    return MeprHooks::apply_filters('mepr-bbpress-unauthorized-message', do_shortcode(MeprRulesCtrl::unauthorized_message($post)));
  }

  public static function hide_forums_from_loop($slug, $name) {
    if($name != 'single-forum') { return; }

    //We only allow restriction on a per-forum basis currently
    //So let's get the current forum's id and check if it's protected
    $forum_id = bbp_get_forum_id();

    if(!$forum_id) { return; }

    $post = get_post($forum_id);

    if(!isset($post) || !MeprRule::is_locked($post)) { return; }

    //If we made it here, tihs is protected
    ?>
    <style type="text/css">
      #bbp-forum-<?php echo $forum_id; ?> {
        display:none !important;
      }
    </style>
    <?php
  }

  public static function hide_topics_from_loop($slug, $name) {
    if($name != 'single-topic') { return; }

    //We only allow restriction on a per-forum basis currently
    //So let's get the current forum's id and check if it's protected
    $forum_id = bbp_get_forum_id();
    $topic_id = bbp_get_topic_id();

    if(!$forum_id) { return; }

    $post = get_post($forum_id);

    if(!isset($post) || !MeprRule::is_locked($post)) { return; }

    //If we made it here, tihs is protected
    ?>
    <style>
      #bbp-topic-<?php echo $topic_id; ?> {
        display:none !important;
      }
    </style>
    <?php
  }

  public static function filter_rules_cpts($cpts) {
    //Since we only allow per-forum restriction,
    //let's unset topics and replies from showing up
    //in the Rules drop-down list
    unset($cpts['reply']);
    unset($cpts['topic']);

    return $cpts;
  }
} //End class
