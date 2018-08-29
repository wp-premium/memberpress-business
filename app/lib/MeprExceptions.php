<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprException extends Exception { }

class MeprLogException extends MeprException {
  public function __construct($message, $code = 0, Exception $previous = null) {
    $classname = get_class($this);
    MeprUtils::error_log("{$classname}: {$message}");
    parent::__construct($message, $code, $previous);
  }
}

class MeprDbMigrationException extends MeprLogException {
  public function __construct($message, $code = 0, Exception $previous = null) {
    delete_transient('mepr_migrating');
    delete_transient('mepr_current_migration');
    set_transient('mepr_migration_error',$message,MeprUtils::hours(4));
    parent::__construct($message, $code, $previous);
  }
}

class MeprDbMigrationRollbackException extends MeprDbMigrationException {
  public function __construct($message, $code = 0, Exception $previous = null) {
    global $wpdb;
    $wpdb->query('ROLLBACK'); // Attempt a rollback
    parent::__construct($message, $code, $previous);
  }
}

class MeprCreateException extends MeprException { }
class MeprUpdateException extends MeprException { }
class MeprDeleteException extends MeprException { }

class MeprInvalidGatewayException extends MeprException { }
class MeprInvalidEmailException extends MeprException { }
class MeprInvalidJobException extends MeprException { }
class MeprInvalidMethodException extends MeprException { }
class MeprInvalidVariableException extends MeprException { }

