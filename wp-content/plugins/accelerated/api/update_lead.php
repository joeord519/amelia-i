<?php
require_once('../db_connect.php');
header("Access-Control-Allow-Origin: https://flypiston.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

try {
  $db = getDB();
  $id = intval($_POST['id'] ?? 0);
  $headset = trim($_POST['headset'] ?? '');
  $selected_month = trim($_POST['selected_month'] ?? '');
  $housing_choice = trim($_POST['housing_choice'] ?? '');
  file_put_contents(__DIR__ . '/debug-housing.txt', print_r($_POST, true));


  if (!$id) {
    throw new Exception("Missing or invalid ID.");
  }

  $fields = [];
  $params = ['id' => $id];

  if ($headset) {
    $fields[] = "headset_choice = :headset";
    $params['headset'] = $headset;
  }

  if ($selected_month) {
    $fields[] = "selected_month = :selected_month";
    $params['selected_month'] = $selected_month;
  }

  if ($housing_choice) {
    $fields[] = "housing_choice = :housing_choice";
    $params['housing_choice'] = $housing_choice;
  }

  if (empty($fields)) {
    throw new Exception("No valid fields to update.");
  }

  $sql = "UPDATE wp_leads SET " . implode(', ', $fields) . " WHERE id = :id";
  $stmt = $db->prepare($sql);
  $stmt->execute($params);

  // Optional debug output (disable or delete this in production)
  // file_put_contents(__DIR__ . '/debug-update.txt', print_r($params, true));

  echo json_encode(['status' => 'updated']);
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}

