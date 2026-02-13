<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../checkout/db_connect.php';

// Get JSON payload
$input = json_decode(file_get_contents("php://input"), true);
$employee_id = intval($input['employee_id']);
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);
$selfieData = $input['selfie'];

if (!$employee_id || !$lat || !$lng || !$selfieData) {
    http_response_code(400);
    echo "❌ Missing required data.";
    exit;
}

// Decode and save selfie
$selfieData = str_replace('data:image/jpeg;base64,', '', $selfieData);
$selfieData = str_replace(' ', '+', $selfieData);
$imageData = base64_decode($selfieData);
$filename = 'checkout_' . $employee_id . '_' . time() . '.jpg';
$savePath = __DIR__ . '/uploads/selfies/' . $filename;
file_put_contents($savePath, $imageData);
$imageUrl = "https://amelia-i.com/wp-content/plugins/time-keeping/uploads/selfies/" . $filename;

// Find today's existing clock-in
$stmt = $conn->prepare("SELECT id FROM wp_time_log WHERE employee_id = ? AND DATE(checkin_time) = CURDATE() AND checkout_time IS NULL ORDER BY checkin_time DESC LIMIT 1");
$stmt->execute([$employee_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo "⚠️ No active clock-in found today. Contact admin if this is a mistake.";
    exit;
}

// Update the row with checkout time and location
$now = date("Y-m-d H:i:s");
$stmt = $conn->prepare("UPDATE wp_time_log SET checkout_time = ?, checkout_lat = ?, checkout_lng = ?, checkout_photo = ? WHERE id = ?");
$stmt->execute([$now, $lat, $lng, $imageUrl, $log['id']]);

echo "✅ Clock-out successful at $now";
