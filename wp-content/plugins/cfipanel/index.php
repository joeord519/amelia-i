<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$cfi_logged_in = isset($_SESSION['cfi_id']);

if ($cfi_logged_in) {
  include_once('dashboard.php');
} else {
  include_once('login.php');
}
?>

