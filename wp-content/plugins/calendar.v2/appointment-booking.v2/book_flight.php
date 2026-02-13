<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/google_calendar_push.php');

$conn = getDB();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$isGround = stripos($data['flightTypeName'] ?? '', 'ground') !== false;

$required = ['locationId', 'flightTypeId', 'flightTypeName', 'phone', 'date', 'time'];
if (!$isGround) $required[] = 'aircraftId';

foreach ($required as $field) {
  if (empty($data[$field])) {
    echo json_encode(["success" => false, "message" => "Missing: $field"]);
    exit;
  }
}

$tail_number     = $data['aircraftId'] ?? null;
$flight_type     = $data['flightTypeName'];
$flight_type_id  = $data['flightTypeId'];
$phone           = $data['phone'];
$date            = $data['date'];
$start_time      = $data['time'];
$cfi_id          = $data['cfiId'] ?? null;
$cfi_name        = $data['cfiName'] ?? 'CFI';
$home_airport    = $data['locationId'];
$start_datetime  = "$date $start_time:00";

try {
  $stmt = $conn->prepare("SELECT default_duration FROM wp_flight_types WHERE id = ?");
  $stmt->execute([$flight_type_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new Exception("Flight type not found");
  $duration_minutes = intval($row['default_duration']);
  $end_datetime = date("Y-m-d H:i:s", strtotime($start_datetime . " +$duration_minutes minutes"));
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Duration lookup failed."]);
  exit;
}

try {
  $stmt = $conn->prepare("SELECT student_id, email, first_name, last_name FROM wp_students WHERE phone = ?");
  $stmt->execute([$phone]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);
  $student_id = $student['student_id'] ?? null;
  $student_email = $student['email'] ?? null;
  $student_name = ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '');

  if (!$student_id || !$student_email) {
    echo json_encode(["success" => false, "message" => "Student not found or email missing."]);
    exit;
  }
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Student lookup failed."]);
  exit;
}

try {
  $query = "
    SELECT COUNT(*) FROM wp_flight_schedule
    WHERE status = 'Scheduled'
    AND (
      (tail_number = :tail AND start_time < :end AND end_time > :start)
      " . ($cfi_id ? "OR (cfi_id = :cfi AND start_time < :end AND end_time > :start)" : "") . "
    )
  ";
  $params = [
    ':tail' => $tail_number,
    ':start' => $start_datetime,
    ':end' => $end_datetime
  ];
  if ($cfi_id) $params[':cfi'] = $cfi_id;
  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  if ($stmt->fetchColumn() > 0) {
    echo json_encode(["success" => false, "message" => "Aircraft or CFI already booked."]);
    exit;
  }
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Conflict check failed."]);
  exit;
}

try {
  $stmt = $conn->prepare("INSERT INTO wp_flight_schedule 
    (tail_number, flight_type, start_time, end_time, cfi_id, student_id, home_airport, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled')");
  $stmt->execute([
    $tail_number,
    $flight_type,
    $start_datetime,
    $end_datetime,
    $cfi_id,
    $student_id,
    $home_airport
  ]);

  $inserted_flight_id = $conn->lastInsertId();
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Insert failed: " . $e->getMessage()]);
  exit;
}

// Google Calendar
$flightData = [
  'cfiId' => $cfi_id,
  'cfiName' => $cfi_name,
  'flightTypeId' => $flight_type_id,
  'flightTypeName' => $flight_type,
  'date' => $date,
  'time' => $start_time,
  'phone' => $phone,
  'aircraftId' => $tail_number
];
$calendarEventId = pushToGoogleCalendar($conn, $flightData);
if ($calendarEventId && is_string($calendarEventId)) {
  $stmt = $conn->prepare("UPDATE wp_flight_schedule SET google_event_id = ? WHERE id = ?");
  $stmt->execute([$calendarEventId, $inserted_flight_id]);
}

// Before the email, do this:
$cfiPhone = '';
if (!empty($cfi_id)) {
  $stmt = $conn->prepare("SELECT phone FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$cfi_id]);
  $cfiPhone = $stmt->fetchColumn() ?: '';
}

// ✅ Send Confirmation Email via Mailgun
sendEmailNotification($student_email, [
  'flight_type' => $flight_type,
  'student_name' => $student_name,
  'student_phone' => $phone,
  'tail_number' => $tail_number,
  'cfi_name' => $cfi_name,
  'cfi_phone' => $cfiPhone,
  'start' => $start_datetime,
  'end' => $end_datetime
]);

echo json_encode(["success" => true]);
