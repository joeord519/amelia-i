<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../checkout/db_connect.php';
$conn = getDB(); // 🧠 this initializes the PDO connection
if (!$conn) {
    die("❌ Database connection failed.");
}

$azureEndpoint = "https://piston-face-api.cognitiveservices.azure.com/";
$azureKey = "FoXwTOlGdzh9WfGPUXm6V6HQaYkLwqYJe9CKrWlHs7guFit4KInYJQQJ99BDACYeBjFXJ3w3AAAKACOGvcwA";

// Get incoming data
$input = json_decode(file_get_contents("php://input"), true);
$employee_id = preg_replace("/[^\d]/", "", $input['employee_id']); // sanitize mobile number
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);
$selfieData = $input['selfie'];

if (!$employee_id || !$lat || !$lng || !$selfieData) {
    http_response_code(400);
    echo "❌ Missing required data.";
    exit;
}

// Get employee from database
$stmt = $conn->prepare("SELECT * FROM wp_employees WHERE phone = ? AND is_active = 1");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    http_response_code(404);
    echo "❌ No active employee found for phone number $employee_id.";
    exit;
}

// Decode and save selfie
$selfieData = str_replace('data:image/jpeg;base64,', '', $selfieData);
$selfieData = str_replace(' ', '+', $selfieData);
$imageData = base64_decode($selfieData);
$filename = 'selfie_' . $employee['id'] . '_' . time() . '.jpg';
$savePath = __DIR__ . '/uploads/selfies/' . $filename;
file_put_contents($savePath, $imageData);
$imageUrl = "https://amelia-i.com/wp-content/plugins/time-keeping/uploads/selfies/" . $filename;

// First-time setup: if no profile photo, save this one
if (empty($employee['azure_face_id']) && empty($employee['profile_photo'])) {
    $stmt = $conn->prepare("UPDATE wp_employees SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$imageUrl, $employee['id']]);
}

// Prevent duplicate clock-ins for the day
$stmt = $conn->prepare("SELECT id FROM wp_time_log WHERE employee_id = ? AND DATE(checkin_time) = CURDATE()");
$stmt->execute([$employee['id']]);
if ($stmt->fetch()) {
    echo "⏱️ You already clocked in today.";
    exit;
}

// Log clock-in
$now = date("Y-m-d H:i:s");
$stmt = $conn->prepare("
    INSERT INTO wp_time_log 
    (employee_id, checkin_time, checkin_lat, checkin_lng, checkin_photo) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$employee['id'], $now, $lat, $lng, $imageUrl]);

echo "✅ Clock-in successful at $now";
