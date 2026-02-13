<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();

$query = $_GET['last'] ?? '';  // 'last' is just the field name from your fetch, but it now supports full name input

if (!$query || strlen($query) < 2) {
  echo json_encode([]);
  exit;
}

// Split query into terms (e.g. "Joe Ord" = ["Joe", "Ord"])
$terms = explode(' ', trim($query));
$firstTerm = $terms[0] ?? '';
$secondTerm = $terms[1] ?? '';

try {
  if (count($terms) >= 2) {
    // Search first AND last name
    $stmt = $conn->prepare("
      SELECT CONCAT(first_name, ' ', last_name) AS name, phone
      FROM wp_students
      WHERE first_name LIKE ? AND last_name LIKE ?
      ORDER BY last_name ASC
      LIMIT 10
    ");
    $stmt->execute(["%$firstTerm%", "%$secondTerm%"]);
  } else {
    // Search first OR last name
    $stmt = $conn->prepare("
      SELECT CONCAT(first_name, ' ', last_name) AS name, phone
      FROM wp_students
      WHERE first_name LIKE ? OR last_name LIKE ?
      ORDER BY last_name ASC
      LIMIT 10
    ");
    $stmt->execute(["%$firstTerm%", "%$firstTerm%"]);
  }

  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  error_log("❌ search_students.php error: " . $e->getMessage());
  echo json_encode([]);
}
