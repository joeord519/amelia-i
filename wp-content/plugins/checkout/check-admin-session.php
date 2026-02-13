<?php
// Enable error reporting for debugging (optional, remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and set response type
session_start();
header('Content-Type: application/json');

// Return admin session info if logged in
if (!empty($_SESSION['admin_logged_in'])) {
  echo json_encode([
    "success" => true,
    "admin_phone" => $_SESSION['admin_phone'],
    "admin_email" => $_SESSION['admin_email']
  ]);
} else {
  echo json_encode(["success" => false]);
}
