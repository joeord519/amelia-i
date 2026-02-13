<?php
session_start();
header('Content-Type: application/json');

if (!empty($_SESSION['admin_logged_in'])) {
  echo json_encode([
    "success" => true,
    "admin_phone" => $_SESSION['admin_phone'],
    "admin_email" => $_SESSION['admin_email']
  ]);
} else {
  echo json_encode(["success" => false]);
}
