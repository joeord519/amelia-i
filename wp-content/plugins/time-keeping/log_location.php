<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../checkout/db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
$employee_id = intval($input['employee_id']);
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);

if (!$employee_id || !$lat || !$lng) {
    http_response_code(400);
    echo "❌ Missing required fields.";
    exit;
}

$stmt = $conn->prepare("INSERT INTO wp_location_logs (employee_id, lat, lng) VALUES (?, ?, ?)");
$stmt->execute([$employee_id, $lat, $lng]);

echo "📍 Location ping logged.";
