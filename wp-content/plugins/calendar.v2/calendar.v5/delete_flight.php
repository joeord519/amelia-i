<?php
session_start(); // ✅ Start session for deleted_by identity

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input || !isset($input['id'])) throw new Exception("Missing flight ID");

  $flightId = $input['id'];
  $reason = $input['reason'] ?? 'No reason provided';
  $studentRequested = !empty($input['student_requested']) ? 1 : 0;

  // ✅ Use session-based user info
  $deletedBy = [
    'name' => $_SESSION['user_name'] ?? 'Unknown',
    'phone' => $_SESSION['user_phone'] ?? ''
  ];

  $db = getDB();

  // Pull full flight data
  $stmt = $db->prepare("
    SELECT 
      f.*, ft.name AS flight_type,
      s.first_name, s.last_name, s.phone, s.email,
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
  if (!$flight) throw new Exception("Flight not found");

  // ✅ Delete from Google Calendar
  deleteGoogleCalendarEvent([
    'id' => $flightId,
    'google_event_id' => $flight['google_event_id'] ?? null,
    'start_time' => $flight['start_time'],
    'end_time' => $flight['end_time'],
    'cfi_id' => $flight['cfi_id'],
    'flight_type' => $flight['flight_type'],
    'event_title' => $flight['flight_type'] . ' - ' . $flight['first_name'] . ' ' . $flight['last_name'] . ' - ' . $flight['tail_number']
  ]);

  // ✅ Remove from DB
  $db->prepare("DELETE FROM wp_flight_students WHERE flight_id = ?")->execute([$flightId]);
  $db->prepare("DELETE FROM wp_flight_schedule WHERE id = ?")->execute([$flightId]);

  // ✅ Log if student requested it
  if ($studentRequested) {
    $stmt = $db->prepare("
      INSERT INTO wp_canceled_flights (
        student_name, student_phone, flight_type, cfi_id, tail_number,
        appointment_date, date_booked, date_canceled, deleted_reason, deleted_by
      ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
      $flight['first_name'] . ' ' . $flight['last_name'],
      $flight['phone'],
      $flight['flight_type'],
      $flight['cfi_id'],
      $flight['tail_number'],
      $flight['start_time'],
      $flight['created_at'] ?? null,
      $reason,
      "{$deletedBy['name']} ({$deletedBy['phone']})"
    ]);
  }

  // ✅ Send cancellation email to student
  if (!empty($flight['email'])) {
    sendEmailNotification($flight['email'], [
      'start' => $flight['start_time'],
      'end' => $flight['end_time'],
      'flight_type' => $flight['flight_type'],
      'student_name' => $flight['first_name'] . ' ' . $flight['last_name'],
      'student_phone' => $flight['phone'],
      'tail_number' => $flight['tail_number'],
      'cfi_name' => $flight['cfi_first'] . ' ' . $flight['cfi_last'],
      'cfi_phone' => $flight['cfi_phone'],
      'canceled_reason' => $reason,
      'deleted_by' => "{$deletedBy['name']} - {$deletedBy['phone']}",
      'student_requested' => $studentRequested
    ]);
  }

  echo json_encode(['success' => true]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}