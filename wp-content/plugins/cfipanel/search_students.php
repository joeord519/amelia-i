<?php
require_once(__DIR__ . '/db_connect.php');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['cfi_id'])) {
  echo json_encode([]);
  exit;
}

$db = getDB();
$cfi_id = (int) $_SESSION['cfi_id'];

$term = trim($_GET['term'] ?? '');
$termLike = '%' . $term . '%';

try {
  $stmt = $db->prepare("
    SELECT student_id, CONCAT(first_name, ' ', last_name) AS full_name
    FROM wp_students
    WHERE assigned_cfi_id = :cfi_id
      AND (
        first_name LIKE :term
        OR last_name LIKE :term
        OR CONCAT(first_name, ' ', last_name) LIKE :term
        OR CONCAT(last_name, ' ', first_name) LIKE :term
      )
    ORDER BY last_name ASC
    LIMIT 20
  ");

  $stmt->execute([
    ':cfi_id' => $cfi_id,
    ':term' => $termLike
  ]);

  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(array_map(function ($row) {
    return [
      'id' => $row['student_id'],
      'text' => $row['full_name']
    ];
  }, $results));
} catch (PDOException $e) {
  echo json_encode([]);
}




