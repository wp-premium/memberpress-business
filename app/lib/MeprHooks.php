<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Helper methods for working with hooks in MemberPress */
class MeprHooks {
  public static function do_action($tag, $arg='') {
    $args = func_get_args();
    $tags = self::tags($tag);

    foreach($tags as $t) {
      $args[0] = $t;
      call_user_func_array('do_action',$args);
    }
  }

  public static function do_action_ref_array($tag, $args) {
    $args = func_get_args();
    $tags = self::tags($tag);

    foreach($tags as $t) {
      $args[0] = $t;
      call_user_func_array('do_action_ref_array',$args);
    }
  }

  public static function apply_filters( $tag, $value ) {
    $args = func_get_args();
    $tags = self::tags($tag);

    foreach($tags as $t) {
      $args[0] = $t;
      $args[1] = call_user_func_array('apply_filters',$args);
    }

    return $args[1];
  }

  public static function apply_filters_ref_array($tag, $args) {
    $args = func_get_args();
    $tags = self::tags($tag);

    foreach($tags as $t) {
      $args[0] = $t;
      $args[1] = call_user_func_array('apply_filters_ref_array',$args);
    }

    return $args[1];
  }

  // We love dashes and underscores ... we just can't choose which we like better :)
  private static function tags($tag) {
    // Prepend mepr if it doesn't exist already
    if(!preg_match('/^mepr[-_]/i',$tag)) {
      $tag = 'mepr_'.$tag;
    }

    return array(
      '-' => preg_replace('/[-_]/','-',$tag),
      '_' => preg_replace('/[-_]/','_',$tag)
    );
  }
}

