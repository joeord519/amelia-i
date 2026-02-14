<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/sync_google_calendar.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/includes/aircraft_availability.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input || !isset($input['id'], $input['start'], $input['end'])) {
    throw new Exception("Missing required input.");
  }

  $flightId   = $input['id'];
  $localStart = $input['start'];
  $localEnd   = $input['end'];
  $reason     = $input['reason'] ?? 'No reason provided';

  // ✅ We're no longer converting to UTC.
// ✅ Since MySQL is now set to America/Chicago, we store values as-is in Central Time.

$startUTC = $localStart;
$endUTC   = $localEnd;

$db = getDB();
$db->exec("SET time_zone = 'America/Chicago'"); // ✅ MySQL now saves and interprets these times correctly as CST/CDT


  // Fetch flight details
  $stmt = $db->prepare("
    SELECT 
      f.*, ft.name AS flight_type,
      s.first_name, s.last_name, s.email, s.phone,
      a.tail_number,
      c.first_name AS cfi_first, c.last_name AS cfi_last, c.phone AS cfi_phone
    FROM wp_flight_schedule f
    LEFT JOIN wp_flight_types ft ON f.flight_type_id = ft.id
    LEFT JOIN wp_flight_students fs ON f.id = fs.flight_id
    LEFT JOIN wp_students s ON fs.student_id = s.student_id
    LEFT JOIN wp_aircraft a ON f.tail_number = a.tail_number
    LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
    WHERE f.id = ?
  ");
  $stmt->execute([$flightId]);
  $flight = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$flight) throw new Exception("Flight not found.");

  $availabilityReason = '';
  if (!check_aircraft_available($db, $flight['tail_number'], $startUTC, $endUTC, $availabilityReason)) {
    throw new Exception($availabilityReason);
  }

  // Update DB
  $stmt = $db->prepare("UPDATE wp_flight_schedule SET start_time = ?, end_time = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$startUTC, $endUTC, $flightId]);

  // Build payload
  $flight['start_time'] = $startUTC;
  $flight['end_time'] = $endUTC;
  $flight['event_title'] = "{$flight['flight_type']} - {$flight['first_name']} {$flight['last_name']} - {$flight['tail_number']}";
  $flight['modified_by'] = $_SESSION['user_name'] ?? 'Unknown';
  $flight['modification_reason'] = $reason;
  $flight['google_event_id'] = $flight['google_event_id'] ?? null;

  // Google sync
  updateGoogleCalendarEvent($flight);

  // Send reschedule email (formatted back to CST)
  if (!empty($flight['email'])) {
    $startLocal = (new DateTime($startUTC, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('America/Chicago'));
    $endLocal = (new DateTime($endUTC, new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone('America/Chicago'));

    sendEmailNotification($flight['email'], [
      'start' => $startLocal->format('Y-m-d H:i'),
      'end' => $endLocal->format('Y-m-d H:i'),
      'flight_type' => $flight['flight_type'],
      'student_name' => $flight['first_name'] . ' ' . $flight['last_name'],
      'student_phone' => $flight['phone'],
      'tail_number' => $flight['tail_number'],
      'cfi_name' => $flight['cfi_first'] . ' ' . $flight['cfi_last'],
      'cfi_phone' => $flight['cfi_phone'],
      'modified_by' => $flight['modified_by'],
      'modification_reason' => $reason
    ]);
  }

  echo json_encode(['success' => true]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

