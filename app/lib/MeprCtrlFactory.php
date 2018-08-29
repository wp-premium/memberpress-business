<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Controllers in MemberPress are all singletons, so we can
  * use this factory to churn out objects for us.
  */
class MeprCtrlFactory {
  public static function fetch($class, $args=array()) {
    static $objs;

    if(!preg_match('#^Mepr.*Ctrl$#', $class)) {
      if(preg_match('/[-_]/',$class)) {
        $class = MeprUtils::camelcase($class);
      }
      elseif(!preg_match('/^[A-Z]/', $class)) {
        $class = ucwords($class);
      }

      $class = 'Mepr'.$class.'Ctrl';
    }

    if(isset($objs[$class]) && ($objs[$class] instanceof MeprBaseCtrl)) {
      return $objs[$class];
    }

    if(!class_exists($class)) {
      throw new Exception(__('Ctrl wasn\'t found', 'memberpress'));
    }

    // We'll let the autoloader in memberpress.php
    // handle including files containing these classes
    $r = new ReflectionClass($class);
    $obj = $r->newInstanceArgs($args);

    $objs[$class] = $obj;

    return $obj;
  }

  public static function all($args=array()) {
    $objs = array();

    foreach(self::paths() as $path) {
      $ctrls = @glob($path . '/Mepr*Ctrl.php', GLOB_NOSORT);
      foreach($ctrls as $ctrl) {
        $class = preg_replace('#\.php#', '', basename($ctrl));
        $objs[$class] = self::fetch($class, $args);
      }
    }

    return $objs;
  }

  public static function paths() {
    return MeprHooks::apply_filters('mepr-ctrls-paths', array(MEPR_CTRLS_PATH));
  }
}

