<?php
/*
File: lock.php
Description: This file prevents access to static files & standalone php scripts if protected.
             For this file to function properly the .htaccess file must be altered.
Author: Caseproof, LLC
Copyright: 2004-2013, Caseproof, LLC
*/
$root = dirname(dirname(dirname(dirname(__FILE__))));
if(file_exists($root.'/wp-load.php'))
  require_once($root.'/wp-load.php');
else
  require_once($root.'/wp-config.php');

if(!isset($_REQUEST['mepruri']))
  $_REQUEST['mepruri'] = $_SERVER['REQUEST_URI'];

$mepr_uri = untrailingslashit($_REQUEST['mepruri']);

$full_uri = 'http' . ((MeprUtils::is_ssl()) ? 's' : '') . '://' .
            $_SERVER['HTTP_HOST'] .
            ( ( $_SERVER['SERVER_PORT'] == 80 || $_SERVER['SERVER_PORT'] == 443 ) ? "" : ":{$_SERVER['SERVER_PORT']}" ) .
            $_SERVER['REQUEST_URI'];

$mepr_full_uri = preg_replace( '#^(https?://[^/]*).*$#', '$1', home_url() ) . $mepr_uri;
$full_uri = preg_replace( '#^(https?://[^/]*).*$#', '$1', $full_uri ) . $mepr_uri;

$subdir = preg_replace("#^https?://[^/]+#", '', site_url());
$mepr_filename = basename( $mepr_uri );
$from_abspath_uri = substr(str_replace($subdir, '', $mepr_uri),1);
$mepr_full_filename = ABSPATH.$from_abspath_uri;

// Redirecting unless the correct home_url is used
if($mepr_full_uri != $full_uri) { wp_redirect( $mepr_full_uri ); exit; }

// Figure out the rule hash for this uri
if($user = MeprUtils::get_currentuserinfo())
  $rule_hash = md5($mepr_uri . $user->ID . wp_salt());
else
  $rule_hash = md5($mepr_uri . wp_salt());

// Make sure expired files are cleaned out
mepr_clean_rule_files();

// Handle when a URI is locked
if(MeprRule::is_uri_locked($mepr_uri)) {
  $mepr_options = MeprOptions::fetch();
  $redirect_to = $mepr_options->login_page_url("action=mepr_unauthorized&redirect_to=".urlencode($mepr_full_uri));

  MeprUtils::wp_redirect($redirect_to);
  exit;
}

// Handle php files & directories
// At this point in the script the user has access
// so all we need to do is redirect them to the right place
if(preg_match('/\.(php|phtml)/',$mepr_uri))
  mepr_redirect_locked_uri($mepr_uri, $rule_hash);
else if(is_dir($mepr_full_filename)) {
  if(file_exists($mepr_full_filename.'/index.php'))
    mepr_redirect_locked_uri($mepr_uri.'/index.php', $rule_hash);
  else if(file_exists($mepr_full_filename.'/index.phtml'))
    mepr_redirect_locked_uri($mepr_uri.'/index.phtml', $rule_hash);
  else if(file_exists($mepr_full_filename.'/index.htm'))
    mepr_render_locked_file($mepr_full_filename.'/index.htm');
  else if(file_exists($mepr_full_filename.'/index.html'))
    mepr_render_locked_file($mepr_full_filename.'/index.html');
} // Handle all other static file types
else
  mepr_redirect_locked_uri($mepr_uri, $rule_hash);
  //mepr_render_locked_file($mepr_full_filename);

function mepr_redirect_locked_uri($mepr_uri, $rule_hash) {
  $rule_dir = MeprRule::rewrite_rule_file_dir();
  @touch($rule_dir . '/' . $rule_hash); // Store off the rule file
  setcookie('mplk',$rule_hash,(time()+5));
  MeprUtils::wp_redirect($mepr_uri);
  exit;
}

function mepr_render_locked_file($filename) {
  // Trim any params from the filename
  $filename = preg_replace('#\?.*$#','',$filename);
  $info = wp_check_filetype($filename);
  $file_contents = file_get_contents($filename);
  header("Content-Type: {$info['type']}");
  echo $file_contents;
  exit;
}

// Demolish rule files if they're older than 60 seconds
function mepr_clean_rule_files() {
  $filenames = @glob( MeprRule::rewrite_rule_file_dir() . '/*', GLOB_NOSORT );

  if(!empty($filenames))
    foreach($filenames as $filename)
      if((time() - filemtime($filename)) > 60)
        unlink($filename);
}
