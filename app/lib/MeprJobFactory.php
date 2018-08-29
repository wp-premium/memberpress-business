<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprJobFactory
{
  public static function fetch( $class, $db=false ) {
    if(!class_exists($class)) {
      throw new MeprInvalidJobException(sprintf(__('Job class wasn\'t found for %s', 'memberpress'), $class));
    }

    // We'll let the autoloader in memberpress.php
    // handle including files containing these classes
    $r = new ReflectionClass($class);
    $job = $r->newInstanceArgs(array($db));

    if( !( $job instanceof MeprBaseJob ) ) {
      throw new MeprInvalidJobException(sprintf(__('%s is not a valid job object.', 'memberpress'), $class));
    }

    return $job;
  }

  public static function paths() {
    $paths = MeprHooks::apply_filters('mepr-job-paths', array(MEPR_JOBS_PATH));
    MeprUtils::debug_log(sprintf(__('Job Paths %s', 'memberpress'),MeprUtils::object_to_string($paths)));
    return $paths;
  }
}

