<?php
/**
 *  WP Rollback MemberPress Upgrader
 *
 * @description: Class that extends the WP Core Plugin_Upgrader found in core to do rollback of MemberPress
 * @copyright  : http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      : 1.0.0
 */

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class WP_Rollback_MemberPress_Upgrader extends Plugin_Upgrader {

  public function rollback( $info, $args = array() ) {
    $defaults    = array('clear_update_cache' => true);
    $parsed_args = wp_parse_args($args, $defaults);

    $this->init();
    $this->upgrade_strings();

    // We know MemberPress exists because here we are
    if ( 0 ) {
      $this->skin->before();
      $this->skin->set_result( false );
      $this->skin->error( 'up_to_date' );
      $this->skin->after();

      return false;
    }

    add_filter( 'upgrader_pre_install', array( $this, 'deactivate_plugin_before_upgrade' ), 10, 2 );
    add_filter( 'upgrader_clear_destination', array( $this, 'delete_old_plugin' ), 10, 4 );

    $this->run( array(
      'package'           => $info['download_url'],
      'destination'       => WP_PLUGIN_DIR,
      'clear_destination' => true,
      'clear_working'     => true,
      'hook_extra'        => array(
        'plugin' => MEPR_PLUGIN_NAME,
        'type'   => 'plugin',
        'action' => 'update',
      ),
    ) );

    // Cleanup our hooks, in case something else does a upgrade on this connection.
    remove_filter( 'upgrader_pre_install', array( $this, 'deactivate_plugin_before_upgrade' ) );
    remove_filter( 'upgrader_clear_destination', array( $this, 'delete_old_plugin' ) );

    if ( ! $this->result || is_wp_error( $this->result ) ) {
      return $this->result;
    }

    // Force refresh of plugin update information
    wp_clean_plugins_cache( $parsed_args['clear_update_cache'] );

    return true;
  }

}

