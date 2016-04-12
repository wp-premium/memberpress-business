<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

interface MeprProductInterface {
  public function group();
  public function is_upgrade();
  public function is_downgrade();
  public function is_upgrade_or_downgrade();
}

