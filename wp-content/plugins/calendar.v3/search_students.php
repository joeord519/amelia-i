<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

$db = getDB();

// ✅ Return all active students if requested
if (isset($_GET['all']) && $_GET['all'] === 'true') {
  try {
    $stmt = $db->query("
      SELECT student_id, first_name, last_name
      FROM wp_students
      WHERE student_status = 'Current'
      ORDER BY last_name ASC
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  } catch (Exception $e) {
    echo json_encode([]);
    exit;
  }
}

// 🔍 Fallback: last name search
$query = $_GET['last'] ?? '';
if (!$query || strlen($query) < 2) {
  echo json_encode([]);
  exit;
}

try {
  $stmt = $db->prepare("
    SELECT 
      student_id,
      CONCAT(first_name, ' ', last_name) AS name,
      phone
    FROM wp_students
    WHERE 
      first_name LIKE ? OR
      last_name LIKE ? OR
      CONCAT(first_name, ' ', last_name) LIKE ?
    LIMIT 15
  ");
  $like = "%$query%";
  $stmt->execute([$like, $like, $like]);
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  echo json_encode([]);
}


