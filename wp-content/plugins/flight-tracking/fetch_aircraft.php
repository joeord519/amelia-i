<?php
require_once 'db.php';
header('Content-Type: application/json');

$airport = $_GET['airport'] ?? '';
if (!$airport) {
  echo json_encode(['status' => 'error', 'error' => 'Missing airport code']);
  exit;
}

$stmt = $pdo->prepare("SELECT tail_number, aircraft_type, manufacturer, model FROM wp_aircraft WHERE status = 'Available' AND home_airport = ?");
$stmt->execute([$airport]);
$aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'status' => 'success',
  'aircraft' => $aircraft
]);
