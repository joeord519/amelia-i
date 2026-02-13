<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();
$query = $_GET['name'] ?? '';

if (!$query || strlen($query) < 2) {
  echo json_encode([]);
  exit;
}

$terms = explode(' ', trim($query));
$firstTerm = $terms[0] ?? '';
$secondTerm = $terms[1] ?? '';

try {
  if (count($terms) >= 2) {
    // Match both first and last name
    $stmt = $conn->prepare("
      SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name
      FROM wp_cfis
      WHERE first_name LIKE ? AND last_name LIKE ?
      ORDER BY last_name ASC
      LIMIT 10
    ");
    $stmt->execute(["%$firstTerm%", "%$secondTerm%"]);
  } else {
    // Match either first or last name
    $stmt = $conn->prepare("
      SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name
      FROM wp_cfis
      WHERE first_name LIKE ? OR last_name LIKE ?
      ORDER BY last_name ASC
      LIMIT 10
    ");
    $stmt->execute(["%$firstTerm%", "%$firstTerm%"]);
  }

  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  error_log("❌ search_cfis.php error: " . $e->getMessage());
  echo json_encode([]);
}


