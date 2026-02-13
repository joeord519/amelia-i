<?php
session_start();
header('Content-Type: application/json');
require_once(__DIR__ . '/db_connect.php');
$db = getDB();

$cfi_id = $_SESSION['cfi_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

$from = $input['from'] ?? null;
$to = $input['to'] ?? null;
$tail = trim($input['tail_number'] ?? '');
$student = trim($input['student_name'] ?? '');

$where = ['fl.cfi_id = :cfi_id'];
$params = [':cfi_id' => $cfi_id];

if (!empty($from)) {
  $where[] = 'fl.flight_date >= :from';
  $params[':from'] = $from;
}
if (!empty($to)) {
  $where[] = 'fl.flight_date <= :to';
  $params[':to'] = $to;
}
if (!empty($tail)) {
  $where[] = 'fl.tail_number = :tail';
  $params[':tail'] = $tail;
}
if (!empty($student)) {
  $where[] = 'CONCAT(s.first_name, " ", s.last_name) LIKE :student';
  $params[':student'] = "%$student%";
}

$sql = "
  SELECT 
    COUNT(*) AS count,
    SUM(fl.total_flight_time) AS total_flight_time,
    SUM(fl.ground_time) AS ground_time
  FROM wp_flight_logs fl
  LEFT JOIN wp_students s ON fl.student_id = s.student_id
  WHERE " . implode(" AND ", $where);

try {
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $result = $stmt->fetch();

  echo json_encode([
    'status' => 'success',
    'count' => intval($result['count']),
    'total_flight_time' => floatval($result['total_flight_time']),
    'ground_time' => floatval($result['ground_time'])
  ]);
} catch (PDOException $e) {
  echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $e->getMessage()]);
}
