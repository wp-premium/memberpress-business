<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseJob {
  public $rec, $db;

  // Take in a job record from the database and use it to populate the object
  public function __construct($db=false) {
    if( empty($db) ) {
      $db = (object)array();
      $db->id = 0;
    }

    $this->rec = isset($db->args) ? json_decode($db->args, true) : array();
    $this->db = (object)$db;
  }

  abstract public function perform();

  public function enqueue_in($in, $priority=10) {
    $classname = get_class($this);
    $jobs = new MeprJobs();
    $jobs->enqueue_in($in, $classname, (array)$this->rec, $priority);
  }

  public function enqueue_at($at, $priority=10) {
    $classname = get_class($this);
    $jobs = new MeprJobs();
    $jobs->enqueue_at($at, $classname, (array)$this->rec, $priority);
  }

  public function enqueue($when='now', $priority=10) {
    $classname = get_class($this);
    $jobs = new MeprJobs();
    $jobs->enqueue($classname, (array)$this->rec, $when, $priority);
  }

  public function dequeue() {
    $jobs = new MeprJobs();
    $jobs->dequeue($this->db->id);
  }

  public function __get($name)
  {
    $value = null;

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

    return MeprHooks::apply_filters('mepr-get-job-attribute-'.$name, $value, $this);
  }

  public function __set($name, $value)
  {
    $value = MeprHooks::apply_filters('mepr-set-job-attribute-'.$name, $value, $this);

    $object_vars = array_keys(get_object_vars($this));
    $rec_array = (array)$this->rec;

    if(in_array($name,$object_vars)) {
      $this->$name = $value;
      // else if(array_key_exists($name, $rec_array))
    }
    else {
      if(is_array($this->rec))
        $this->rec[$name] = $value;
      else
        $this->rec->$name = $value;
    }
  }

  public function __isset($name)
  {
    if(is_array($this->rec))
      return isset($this->rec[$name]);
    else if(is_object($this->rec))
      return isset($this->rec->$name);
    else
      return false;
  }

  public function __unset($name)
  {
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
}

