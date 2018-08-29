<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseModel {
  protected $rec, $attrs, $defaults;

  public function __get($name) {
    $value = null;

    if($this->magic_method_handler_exists($name)) {
      $value = $this->call_magic_method_handler('get',$name);
    }

    $object_vars = array_keys(get_object_vars($this));
    $rec_array = (array)$this->rec;

    if(in_array($name, $object_vars)) {
      $value = $this->$name;
    }
    else if(array_key_exists($name, $rec_array))
    {
      if(is_array($this->rec)) {
        $value = $this->rec[$name];
      }
      else {
        $value = $this->rec->$name;
      }
    }

    // Alternative way to filter results through an sub class method
    $extend_fn = "get_extend_{$name}";
    if(method_exists($this,$extend_fn)) {
      $value = call_user_func(array($this,$extend_fn), $value);
    }

    return MeprHooks::apply_filters('mepr-get-model-attribute-'.$name, $value, $this);
  }

  public function __set($name, $value) {
    $value = MeprHooks::apply_filters('mepr-set-model-attribute-'.$name, $value, $this);

    // Alternative way to filter results through an sub class method
    $extend_fn = "set_extend_{$name}";
    if(method_exists($this,$extend_fn)) {
      $value = call_user_func(array($this,$extend_fn), $value);
    }

    if($this->magic_method_handler_exists($name)) {
      return $this->call_magic_method_handler('set', $name, $value);
    }

    $object_vars = array_keys(get_object_vars($this));
    $rec_array = (array)$this->rec;

    if(in_array($name,$object_vars)) {
      $this->$name = $value;
    }
    else if(array_key_exists($name, $rec_array)) {
      if(is_array($this->rec)) {
        $this->rec[$name] = $value;
      }
      else {
        $this->rec->$name = $value;
      }
    }
    else {
      $this->$name = $value;
    }
  }

  public function __isset($name) {
    if($this->magic_method_handler_exists($name))
      return $this->call_magic_method_handler('isset', $name);

    if(is_array($this->rec))
      return isset($this->rec[$name]);
    else if(is_object($this->rec))
      return isset($this->rec->$name);
    else
      return false;
  }

  public function __unset($name) {
    if($this->magic_method_handler_exists($name))
      return $this->call_magic_method_handler('unset', $name);

    if(is_array($this->rec))
      unset($this->rec[$name]);
    else if(is_object($this->rec))
      unset($this->rec->$name);
  }

  /** We just return a JSON encoding of the attributes in the model when we
    * try to get a string for the model. */
  public function __toString() {
    return json_encode((array)$this->rec);
  }

  public function initialize($defaults, $obj=null) {
    $this->rec = (object)$defaults;
    $this->attrs = array_keys($defaults);

    if(!is_null($obj)) {
      if(is_numeric($obj) && $obj > 0) {
        $class = get_class($this);

        //$fetched_obj = $class::get_one((int)$obj);
        //$fetched_obj = call_user_func("{$class}::get_one",(int)$obj);

        // ReflectionMethod is less error prone than the other two methods above
        $rm = new ReflectionMethod($class, 'get_one');
        $fetched_obj = $rm->invoke(null, (int)$obj);

        $this->load_from_array($fetched_obj);
      }
      else if(is_array($obj)) {
        $this->load_from_array($obj);
      }
    }
  }

  public function get_values() {
    return MeprUtils::filter_array_keys((array)$this->rec,(array)$this->attrs);
  }

  public function get_attrs() {
    return (array)$this->attrs;
  }

  // create a duplicate model without an id
  public function duplicate() {
    $values = (array)$this->rec;

    if(isset($values['id'])) { unset($values['id']); }
    if(isset($values['ID'])) { unset($values['ID']); }

    $class = get_class($this);

    $r = new ReflectionClass($class);
    $obj = $r->newInstance();

    $obj->load_from_array($values);

    return $obj;
  }

  public function load_from_array($values) {
    $unserialized_values = array();
    $values = (array)$values;
    $defaults = (array)$this->defaults;

    foreach($values as $key => $value) {
      // Try to detect the type appropriately
      if(isset($defaults[$key])) {
        if(is_bool($defaults[$key])) {
          $value = (bool)$value;
        }
        else if(is_float($defaults[$key])) {
          $value = (float)$value;
        }
        else if(is_integer($defaults[$key])) {
          $value = (integer)$value;
        }
      }
      $unserialized_values[$key] = maybe_unserialize($value);

    }

    $this->rec = (object)array_merge((array)$this->rec,(array)$unserialized_values);
  }

  // Alias just for convenience
  public function load_by_array($values) {
    $this->load_from_array($values);
  }

  // Alias just for convenience
  public function load_data($values) {
    $this->load_from_array($values);
  }

  /* Ensure that the object validates. */
  public function validate() {
    return true;
  }

  /* Store the object in the database */
  abstract public function store();

  /** This is an alias of store() */
  public function save() {
    return $this->store();
  }

  abstract public function destroy();

  /** This is an alias of destroy() */
  public function delete() {
    return $this->destroy();
  }

  // If this function exists it will override the default behavior of looking in the rec object
  protected function magic_method_handler_exists($name) {
    return in_array("mgm_{$name}", get_class_methods($this));
  }

  protected function call_magic_method_handler($mgm, $name, $value='') {
    return call_user_func_array( array($this, "mgm_{$name}"), array( $mgm, $value ) );
  }

  protected function validate_not_null($var, $field='') {
    if(is_null($var)) {
      throw new MeprCreateException(sprintf(__('%s must not be empty', 'memberpress'),$field));
    }
  }

  protected function validate_not_empty($var, $field='') {
    if(empty($var)) {
      throw new MeprCreateException(sprintf(__('%s must not be empty', 'memberpress'),$field));
    }
  }

  protected function validate_is_bool($var, $field='') {
    if(!is_bool($var) && $var!=0 && $var!=1) {
      throw new MeprCreateException(sprintf(__('%s must be true or false', 'memberpress'),$field));
    }
  }

  protected function validate_is_array($var, $field='') {
    if(!is_array($var)) {
      throw new MeprCreateException(sprintf(__('%s must be an array', 'memberpress'),$field));
    }
  }

  protected function validate_is_in_array($var, $lookup, $field='') {
    if(is_array($lookup) && !in_array($var, $lookup)) {
      throw new MeprCreateException(sprintf(__('%1$s must be %2$s', 'memberpress'),$field, implode( ' '.__('or', 'memberpress').' ', $lookup)));
    }
  }

  protected function validate_is_url($var, $field='') {
    if(!MeprUtils::is_url($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid url', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_currency($var, $min=0.00, $max=null, $field='') {
    if(!is_numeric($var) || $var < $min || (!is_null($max) && $var > $max)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid representation of currency', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_numeric($var, $min=0, $max=null, $field='') {
    if(!is_numeric($var) || $var < $min || (!is_null($max) && $var > $max)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid number', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_email($var, $field='') {
    if(!MeprUtils::is_email($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid email', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_phone($var, $field='') {
    if(!MeprUtils::is_phone($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid phone number', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_ip_addr($var, $field='') {
    if(!MeprUtils::is_ip($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid IP Address', 'memberpress'),$field,$var));
    }
  }

  protected function validate_is_date($var, $field='') {
    if(!MeprUtils::is_date($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid date', 'memberpress'),$field,$var));
    }
  }

  // Pretty much all we can do here is make sure it's a number and not empty
  protected function validate_is_timestamp($var, $field='') {
    if(empty($var) || !is_numeric($var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must be a valid timestamp', 'memberpress'),$field,$var));
    }
  }

  protected function validate_regex($pattern, $var, $field='') {
    if(!preg_match($pattern, $var)) {
      throw new MeprCreateException(sprintf(__('%1$s (%2$s) must match the regex pattern: %3$s', 'memberpress'),$field,$var,$pattern));
    }
  }
}
