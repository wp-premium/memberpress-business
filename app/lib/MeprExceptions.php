<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprCreateException extends Exception { }
class MeprUpdateException extends Exception { }
class MeprDeleteException extends Exception { }
class MeprInvalidGatewayException extends Exception { }
class MeprInvalidEmailException extends Exception { }
class MeprInvalidJobException extends Exception { }
class MeprInvalidMethodException extends Exception { }
class MeprInvalidVariableException extends Exception { }

