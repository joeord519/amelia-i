<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();
$data = json_decode(file_get_contents("php://input"), true);

// Required fields
$phone        = $data['phone'] ?? '';
$flightTypeId = $data['flightTypeId'] ?? null;
$tailNumber   = $data['tailNumber'] ?? null;
$cfiId        = $data['cfiId'] ?? null;
$location     = $data['location'] ?? null;
$date         = $data['date'] ?? null;
$time         = $data['time'] ?? null;

// Optional Discovery fields
$futureName  = $data['futureName'] ?? '';
$futurePhone = $data['futurePhone'] ?? '';

if (!$flightTypeId || !$location || !$date || !$time) {
  echo json_encode(["success" => false, "message" => "Missing required booking fields."]);
  exit;
}

$start = "$date $time:00";

// 🕑 Get flight type name + duration
try {
  $stmt = $conn->prepare("SELECT name, default_duration FROM wp_flight_types WHERE id = ?");
  $stmt->execute([$flightTypeId]);
  $type = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$type) throw new Exception("Invalid flight type.");
  $flightTypeName = $type['name'];
  $duration = intval($type['default_duration']);
  $end = date("Y-m-d H:i:s", strtotime("$start +$duration minutes"));
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Duration lookup failed: " . $e->getMessage()]);
  exit;
}

// 👤 Match student (Discovery flights may not need one)
$studentId = null;
if (!empty($phone)) {
  try {
    $stmt = $conn->prepare("SELECT student_id FROM wp_students WHERE phone = ?");
    $stmt->execute([$phone]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentId = $student['student_id'] ?? null;
  } catch (Exception $e) {
    // Allow insert to continue even if student not found (for Discovery)
    error_log("ℹ️ Student lookup failed: " . $e->getMessage());
  }
}

// ✅ Insert booking
try {
  $stmt = $conn->prepare("
    INSERT INTO wp_flight_schedule (
      tail_number, flight_type, start_time, end_time, 
      cfi_id, student_id, home_airport, status,
      future_student_name, future_student_phone
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?)
  ");
  $stmt->execute([
    $tailNumber,
    $flightTypeName,
    $start,
    $end,
    $cfiId ?: null,
    $studentId,
    $location,
    $futureName ?: null,
    $futurePhone ?: null
  ]);

  echo json_encode(["success" => true]);
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Insert failed: " . $e->getMessage()]);
}

// 📝 If Discovery Flight with future student info, also log to leads
if ($flightTypeName === 'Discovery Flight' && $futureName && $futurePhone) {
  $parts = explode(' ', $futureName, 2);
  $firstName = $parts[0] ?? '';
  $lastName = $parts[1] ?? '';

  try {
    $stmt = $conn->prepare("
      INSERT INTO leads (first_name, last_name, phone, training_program)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$firstName, $lastName, $futurePhone, 'Discovery Flight']);
  } catch (Exception $e) {
    error_log("❌ Failed to insert lead from discovery flight: " . $e->getMessage());
  }
}
