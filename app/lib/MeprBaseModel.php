<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseModel
{
  protected $rec;
  protected $attrs;

  public function __get($name)
  {
    $value = null;

    if($this->magic_method_handler_exists($name))
      $value = $this->call_magic_method_handler('get',$name);

    $object_vars = array_keys(get_object_vars($this));
    $rec_array = (array)$this->rec;

    if(in_array($name, $object_vars))
      $value = $this->$name;
    else if(array_key_exists($name, $rec_array))
    {
      if(is_array($this->rec))
        $value = $this->rec[$name];
      else
        $value = $this->rec->$name;
    }

    return MeprHooks::apply_filters('mepr-get-model-attribute-'.$name, $value, $this);
  }

  public function __set($name, $value)
  {
    $value = MeprHooks::apply_filters('mepr-set-model-attribute-'.$name, $value, $this);

    if($this->magic_method_handler_exists($name))
      return $this->call_magic_method_handler('set', $name, $value);

    $object_vars = array_keys(get_object_vars($this));
    $rec_array = (array)$this->rec;

    if(in_array($name,$object_vars))
      $this->$name = $value;
    else if(array_key_exists($name, $rec_array))
    {
      if(is_array($this->rec))
        $this->rec[$name] = $value;
      else
        $this->rec->$name = $value;
    }
  }

  public function __isset($name)
  {
    if($this->magic_method_handler_exists($name))
      return $this->call_magic_method_handler('isset', $name);

    if(is_array($this->rec))
      return isset($this->rec[$name]);
    else if(is_object($this->rec))
      return isset($this->rec->$name);
    else
      return false;
  }

  public function __unset($name)
  {
    if($this->magic_method_handler_exists($name))
      return $this->call_magic_method_handler('unset', $name);

    if(is_array($this->rec))
      unset($this->rec[$name]);
    else if(is_object($this->rec))
      unset($this->rec->$name);
  }

  /** We just return a JSON encoding of the attributes in the model when we
    * try to get a string for the model. */
  public function __toString()
  {
    return json_encode((array)$this->rec);
  }

  public function load_from_array($values) {
    $this->rec = (object)array_merge((array)$this->rec,$values);
  }

  /* Ensure that the object validates. */
  public function validate() {
    return true;
  }

  /* Store the object in the database */
  abstract public function store();

  /** This is an alias of store() */
  public function save()
  {
    return $this->store();
  }

  abstract public function destroy();

  /** This is an alias of destroy() */
  public function delete()
  {
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
    if($var === '' || $var === '0' || $var === 0 || $var === false) {
      throw new MeprCreateException(sprintf(__('%s must not be empty', 'memberpress'),$field));
    }
  }

  protected function validate_is_bool($var, $field='') {
    if(!is_bool($var)) {
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
      throw new MeprCreateException(sprintf(__('%s must be a valid url', 'memberpress'),$field));
    }
  }

  protected function validate_is_currency($var, $min=0.00, $max=null, $field='') {
    if(!is_numeric($var) || $var < $min || (!is_null($max) && $var > $max)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid representation of currency', 'memberpress'),$field));
    }
  }

  protected function validate_is_numeric($var, $min=0, $max=null, $field='') {
    if(!is_numeric($var) || $var < $min || (!is_null($max) && $var > $max)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid number', 'memberpress'),$field));
    }
  }

  protected function validate_is_email($var, $field='') {
    if(!MeprUtils::is_email($var)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid email', 'memberpress'),$field));
    }
  }

  protected function validate_is_phone($var, $field='') {
    if(!MeprUtils::is_phone($var)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid email', 'memberpress'),$field));
    }
  }

  protected function validate_is_ip_addr($var, $field='') {
    if(!MeprUtils::is_ip($var)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid IP Address', 'memberpress'),$field));
    }
  }

  protected function validate_is_date($var, $field='') {
    if(!MeprUtils::is_date($var)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid date', 'memberpress'),$field));
    }
  }

  // Pretty much all we can do here is make sure it's a number and not empty
  protected function validate_is_timestamp($var, $field='') {
    if(empty($var) || !is_numeric($var)) {
      throw new MeprCreateException(sprintf(__('%s must be a valid timestamp', 'memberpress'),$field));
    }
  }

  protected function validate_regex($pattern, $var, $field='') {
    if(!preg_match($pattern, $var)) {
      throw new MeprCreateException(sprintf(__('%1$s must match the regex pattern: %2$s', 'memberpress'),$field,$pattern));
    }
  }
}
