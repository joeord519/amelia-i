<?php
require_once('../db_connect.php');
$conn = getDB();

header('Content-Type: application/json');

// ✅ Clear SiteGround Cache (via WordPress theme endpoint)
function clearSiteGroundCache() {
  file_get_contents("https://amelia-i.com/wp-content/themes/generatepress/clear-cache-endpoint.php?secret=aabbcc112233ddee");
}

// ✅ Read the incoming POST body
$data = json_decode(file_get_contents('php://input'), true);

$isGround = stripos($data['flightTypeName'] ?? '', 'ground') !== false;

$required = ['locationId', 'flightTypeId', 'flightTypeName', 'phone', 'date', 'time'];
if (!$isGround) {
  $required[] = 'aircraftId';
}

foreach ($required as $field) {
  if (empty($data[$field])) {
    echo json_encode(["success" => false, "message" => "Missing: $field"]);
    exit;
  }
}

// ✅ Extract fields
$tail_number     = $data['aircraftId'] ?? null;
$flight_type     = $data['flightTypeName'];
$flight_type_id  = $data['flightTypeId'];
$phone           = $data['phone'];
$date            = $data['date'];
$start_time      = $data['time'];
$cfi_id          = $data['cfiId'] ?? null;
$home_airport    = $data['locationId'];
$start_datetime  = "$date $start_time:00";

// ✅ Look up duration
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

// ✅ Match student by phone number
try {
  $stmt = $conn->prepare("SELECT student_id, email FROM wp_students WHERE phone = ?");
  $stmt->execute([$phone]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);
  $student_id = $student['student_id'] ?? null;
  $student_email = $student['email'] ?? null;

  if (!$student_id || !$student_email) {
    echo json_encode(["success" => false, "message" => "Student not found or email missing."]);
    exit;
  }
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Student lookup failed."]);
  exit;
}

// ✅ Check for aircraft or CFI conflicts
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

// ✅ Insert booking into DB
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
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Insert failed: " . $e->getMessage()]);
  exit;
}

// ✅ Google Calendar push
require_once("google_calendar_push.php");
pushToGoogleCalendar($conn, $tail_number, $flight_type, $start_datetime, $end_datetime, $cfi_id, $student_id);

// ✅ Get student email
$stmt = $conn->prepare("SELECT email FROM wp_students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$student_email = $student['email'] ?? '';

error_log("📧 Attempting to send email to: " . $student_email);

if (!empty($student_email)) {
  $ics = "BEGIN:VCALENDAR\r\n";
  $ics .= "VERSION:2.0\r\n";
  $ics .= "PRODID:-//Piston Aviation//Flight Scheduler//EN\r\n";
  $ics .= "BEGIN:VEVENT\r\n";
  $ics .= "UID:" . uniqid() . "@pistonaviation.com\r\n";
  $ics .= "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
  $ics .= "DTSTART:" . gmdate("Ymd\THis\Z", strtotime($start_datetime)) . "\r\n";
  $ics .= "DTEND:" . gmdate("Ymd\THis\Z", strtotime($end_datetime)) . "\r\n";
  $ics .= "SUMMARY:$flight_type – $tail_number\r\n";
  $ics .= "DESCRIPTION:Flight scheduled with Piston Aviation\r\n";
  $ics .= "LOCATION:" . ($home_airport ?: "Piston Aviation") . "\r\n";
  $ics .= "END:VEVENT\r\n";
  $ics .= "END:VCALENDAR\r\n";

  $boundary = "----=_PistonFlight_" . md5(uniqid());

  $headers = "MIME-Version: 1.0\r\n";
  $headers .= "From: Piston Aviation <noreply@amelia-i.com>\r\n";
  $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

  $plain = "You're booked with Piston Aviation!\n\n";
  $plain .= "Tail #: $tail_number\n";
  $plain .= "Flight Type: $flight_type\n";
  $plain .= "Time: $start_datetime to $end_datetime\n";
  $plain .= "Location: " . ($home_airport ?: "Piston Aviation") . "\n";

  $html = "
    <html>
    <body style='font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px;'>
      <div style='max-width: 600px; margin: auto; background: white; border-radius: 8px; padding: 20px; border: 1px solid #ddd;'>
        <h2 style='color: #2d3748;'>✈️ Your Flight is Scheduled!</h2>
        <p style='font-size: 16px;'>You're officially booked with <strong>Piston Aviation</strong>.</p>
        <table style='margin-top: 15px;'>
          <tr><td><strong>Tail Number:</strong></td><td>$tail_number</td></tr>
          <tr><td><strong>Flight Type:</strong></td><td>$flight_type</td></tr>
          <tr><td><strong>Time:</strong></td><td>$start_datetime – $end_datetime</td></tr>
          <tr><td><strong>Location:</strong></td><td>" . ($home_airport ?: "Piston Aviation") . "</td></tr>
        </table>
        <p style='margin-top: 20px; font-style: italic; color: #4a5568;'>This email includes a calendar invite for your records.</p>
      </div>
    </body>
    </html>
  ";

  $message = "--$boundary\r\n";
  $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
  $message .= $plain . "\r\n";
  $message .= "--$boundary\r\n";
  $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
  $message .= $html . "\r\n";
  $message .= "--$boundary\r\n";
  $message .= "Content-Type: text/calendar; method=REQUEST; name=\"flight.ics\"\r\n";
  $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
  $message .= $ics . "\r\n";
  $message .= "--$boundary--";

  $sent = mail($student_email, "Piston Flight Scheduled", $message, $headers);
  error_log("📨 Email to $student_email: " . ($sent ? "✅ Sent" : "❌ Failed"));
} else {
  error_log("❌ No student email found for student_id: $student_id");
}


