<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** Churns out our Gateways on demand **/
class MeprGatewayFactory {
  public static function fetch( $class, $settings=null ) {
    if(!class_exists($class))
      throw new MeprInvalidGatewayException(__('Gateway wasn\'t found', 'memberpress'));

    // We'll let the autoloader in memberpress.php
    // handle including files containing these classes
    $obj = new $class;

    if( !is_a($obj,'MeprBaseRealGateway') )
      throw new MeprInvalidGatewayException(__('Not a valid gateway', 'memberpress'));

    if( !is_null($settings) )
      $obj->load($settings);

    return $obj;
  }

  public static function all() {
    static $gateways;

    if( !isset($gateways) ) {
      $gateways = array();

      foreach( self::paths() as $path ) {
        $files = @glob( $path . '/Mepr*Gateway.php', GLOB_NOSORT );
        foreach( $files as $file ) {
          $class = preg_replace( '#\.php#', '', basename($file) );

          try {
            $obj = self::fetch($class);
            $gateways[$class] = $obj->name;
          }
          catch (Exception $e) {
            continue; // For now we do nothing if an exception is thrown
          }
        }
      }
    }

    return $gateways;
  }

  public static function paths() {
    return MeprHooks::apply_filters( 'mepr-gateway-paths', array( MEPR_GATEWAYS_PATH ) );
  }
}
