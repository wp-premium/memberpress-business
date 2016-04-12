<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprProductEmailException extends Exception { }

abstract class MeprBaseProductEmail extends MeprBaseEmail {
  // Override the constructor to setup memberships and then
  // call the parent constructor to get everything else setup
  public function __construct($args=array()) {
    // $this->product isn't necessarily set so you can't rely on it
    if(isset($args['product_id']))
      $this->product = new MeprProduct($args['product_id']);

    parent::__construct($args);
  }

  public function get_stored_field($fieldname) {
    $classname = get_class($this);
    $default = isset($this->defaults[$fieldname]) ? $this->defaults[$fieldname] : false;

    if( !isset($this->product) or
        !isset($this->product->emails) or
        !isset($this->product->emails[$classname]) or
        !isset($this->product->emails[$classname][$fieldname]) )
    { return $default; }

    return $this->product->emails[$classname][$fieldname];
  }

  public function field_name($field='enabled', $id=false) {
    $classname = get_class($this);

    if($id)
      return MeprProduct::$emails_str . '-' . $this->dashed_name() . '-' . $field;
    else
      return MeprProduct::$emails_str . '[' . $classname . '][' . $field . ']';
  }
}

