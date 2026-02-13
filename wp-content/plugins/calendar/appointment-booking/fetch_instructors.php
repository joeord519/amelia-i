<?php
require_once('../db_connect.php');
$conn = getDB();

$locId = $_GET['loc'] ?? '';
header('Content-Type: application/json');

try {
  $stmt = $conn->prepare("
    SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name 
    FROM wp_cfis 
    WHERE status = 'Active' 
      AND home_airport = ?
    ORDER BY last_name ASC
  ");
  $stmt->execute([$locId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($rows);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}

