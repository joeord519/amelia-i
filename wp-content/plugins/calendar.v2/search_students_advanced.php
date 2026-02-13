<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

$query = $_GET['query'] ?? '';

if (strlen($query) < 2) {
  echo json_encode([]);
  exit;
}

$sql = "
  SELECT first_name, last_name, phone, email
  FROM wp_students
  WHERE 
    first_name LIKE :q OR
    last_name LIKE :q OR
    CONCAT(first_name, ' ', last_name) LIKE :q OR
    email LIKE :q OR
    phone LIKE :q
  LIMIT 10
";

$stmt = $conn->prepare($sql);
$like = '%' . $query . '%';
$stmt->bindValue(':q', $like, PDO::PARAM_STR);
$stmt->execute();

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $results[] = [
    'name' => $row['first_name'] . ' ' . $row['last_name'],
    'phone' => $row['phone'],
    'email' => $row['email']
  ];
}

echo json_encode($results);
?>

