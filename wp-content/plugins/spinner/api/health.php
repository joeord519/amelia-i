<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$resp = [
  'ok' => true,
  'php_version' => PHP_VERSION,
  'paths' => []
];

$root = dirname(__DIR__, 4); // wp root
$resp['paths']['wp_root'] = $root;

$wpLoad = $root . '/wp-load.php';
$config = dirname(__DIR__) . '/config.php';
$dbconn = dirname(__DIR__) . '/db_connect.php';

$resp['paths']['wp-load'] = $wpLoad;
$resp['paths']['wp-load_exists']   = file_exists($wpLoad);
$resp['paths']['config']           = $config;
$resp['paths']['config_exists']    = file_exists($config);
$resp['paths']['db_connect']       = $dbconn;
$resp['paths']['db_connect_exists']= file_exists($dbconn);

try { require_once $config; $resp['config_loaded']=true; } 
catch (Throwable $e) { $resp['config_loaded']=false; $resp['config_error']=$e->getMessage(); }

try {
  if (defined('PISTON_SPINNER_LOCAL_DBCONNECT')) {
    require_once PISTON_SPINNER_LOCAL_DBCONNECT;
    $resp['db_loaded']=true;
    $resp['pdo_defined']= isset($pdo);
  } else { $resp['db_loaded']=false; $resp['pdo_defined']=false; }
} catch (Throwable $e) { $resp['db_loaded']=false; $resp['db_error']=$e->getMessage(); }

try { require_once $wpLoad; $resp['wp_loaded']=true; }
catch (Throwable $e) { $resp['wp_loaded']=false; $resp['wp_error']=$e->getMessage(); }

echo json_encode($resp);
