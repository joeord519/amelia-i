<?php
require_once(__DIR__ . '/../wp-content/plugins/checkout.basic/db_connect.php');

$data = json_decode(file_get_contents('php://input'), true);

$phoneRaw = $data['phone'] ?? '';
$aircraft = floatval($data['aircraft_hours'] ?? 0);
$instructor = floatval($data['instructor_hours'] ?? 0);

$clean = preg_replace('/\D/', '', $phoneRaw);
if (strlen($clean) === 10) {
  $formattedPhone = '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6);
} else {
  http_response_code(400);
  exit("Invalid phone");
}

try {
  $db = getDB();

  $update = $db->prepare("
    UPDATE wp_students
    SET aircraft_hours_remaining = aircraft_hours_remaining + :a,
        instructor_hours_remaining = instructor_hours_remaining + :i
    WHERE phone = :p
  ");
  $update->execute([
    ':a' => $aircraft,
    ':i' => $instructor,
    ':p' => $formattedPhone
  ]);

  http_response_code(200);
  echo "✅ Updated balances for $formattedPhone";

} catch (Exception $e) {
  http_response_code(500);
  echo "❌ " . $e->getMessage();
}
