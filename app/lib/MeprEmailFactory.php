<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Churns out our Emails on demand **/
class MeprEmailFactory {
  public static function fetch( $class, $etype='MeprBaseEmail', $args=array() ) {
    if(!class_exists($class)) {
      throw new MeprInvalidEmailException(__('Email wasn\'t found', 'memberpress'));
    }

    // We'll let the autoloader in memberpress.php
    // handle including files containing these classes
    $r = new ReflectionClass($class);
    $obj = $r->newInstanceArgs($args);

    if(!($obj instanceof $etype)) {
      throw new MeprInvalidEmailException(sprintf(__('Not a valid email object: %1$s is not an instance of %2$s', 'memberpress'), $class, $etype));
    }

    return $obj;
  }

  public static function all($etype='MeprBaseEmail', $args=array()) {
    static $objs;

    if( !isset($objs) ) { $objs = array(); }

    if( !isset($objs[$etype]) ) {
      $objs[$etype] = array();

      foreach( self::paths() as $path ) {
        $files = @glob( $path . '/Mepr*Email.php', GLOB_NOSORT );
        foreach( $files as $file ) {
          $class = preg_replace( '#\.php#', '', basename($file) );

          try {
            $obj = self::fetch($class, $etype, $args);
            $objs[$etype][$class] = $obj;
          }
          catch (Exception $e) {
            continue; // For now we do nothing if an exception is thrown
          }
        }
      }

      // order based on the ui_order
      uasort($objs[$etype], 'MeprEmailFactory::cmp_uasort');
    }

    return $objs[$etype];
  }

  // Purely used for sorting based on the ui_order
  public static function cmp_uasort($a, $b) {
    if($a->ui_order==$b->ui_order) { return 0; }
    return ($a->ui_order < $b->ui_order) ? -1 : 1;
  }

  public static function paths() {
    return MeprHooks::apply_filters( 'mepr-email-paths', array( MEPR_EMAILS_PATH ) );
  }
}

