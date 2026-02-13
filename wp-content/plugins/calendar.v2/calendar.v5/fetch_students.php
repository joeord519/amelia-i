<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $q = $_GET['query'] ?? '';
  if (!$q) throw new Exception("Missing search query");

  $db = getDB();

  $stmt = $db->prepare("
  SELECT student_id, CONCAT(first_name, ' ', last_name) AS name, phone, email
  FROM wp_students
  WHERE 
    first_name LIKE ? 
    OR last_name LIKE ? 
    OR CONCAT(first_name, ' ', last_name) LIKE ?
  ORDER BY last_name ASC
  LIMIT 25
");

  $wild = '%' . $q . '%';
  $stmt->execute([$wild, $wild, $wild]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($students);
} catch (Exception $e) {
  echo json_encode([]);
}

