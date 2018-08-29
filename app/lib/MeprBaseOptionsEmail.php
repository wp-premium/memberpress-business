<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseOptionsEmail extends MeprBaseEmail {
  public function get_stored_field($fieldname) {
    $mepr_options = MeprOptions::fetch();
    $classname = get_class($this);

    $default = isset($this->defaults[$fieldname]) ? $this->defaults[$fieldname] : false;

    if( !isset($mepr_options->emails[$classname]) or !isset($mepr_options->emails[$classname][$fieldname]) )
    { return $default; }

    return $mepr_options->emails[$classname][$fieldname];
  }

  public function field_name($field='enabled', $id=false) {
    $mepr_options = MeprOptions::fetch();
    $classname = get_class($this);

    if($id)
      return $mepr_options->emails_str . '-' . $this->dashed_name() . '-' . $field;
    else
      return $mepr_options->emails_str . '[' . $classname . '][' . $field . ']';
  }
}

