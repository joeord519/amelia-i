<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/clear_cache.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  if (empty($input['id']) || empty($input['start']) || empty($input['end'])) {
    throw new Exception("Missing required data.");
  }

  $flightId = $input['id'];
  $newStart = $input['start'];
  $newEnd = $input['end'];
  $reason = $input['reason'] ?? 'No reason provided';
  $modified_by = $_COOKIE['user_name'] ?? 'Unknown';

  $db = getDB();

  // ✅ Fetch original times before update
  $originalStmt = $db->prepare("SELECT start_time, end_time FROM wp_flight_schedule WHERE id = ?");
  $originalStmt->execute([$flightId]);
  $original = $originalStmt->fetch();

  // ✅ Update the flight record
  $stmt = $db->prepare("UPDATE wp_flight_schedule SET start_time = ?, end_time = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$newStart, $newEnd, $flightId]);

  // ✅ Pull enriched flight for Google Calendar & Email
  $stmt = $db->prepare("
    SELECT 
      fs.*, 
      ft.name AS flight_type_name,
      s.first_name AS student_first,
      s.last_name AS student_last,
      s.phone AS student_phone,
      c.first_name AS cfi_first,
      c.last_name AS cfi_last,
      c.email AS cfi_email
    FROM wp_flight_schedule fs
    LEFT JOIN wp_flight_types ft ON fs.flight_type = ft.id
    LEFT JOIN wp_students s ON fs.student_id = s.student_id
    LEFT JOIN wp_cfis c ON fs.cfi_id = c.cfi_id
    WHERE fs.id = ?
  ");
  $stmt->execute([$flightId]);
  $flight = $stmt->fetch();

  if (!$flight) throw new Exception("Updated flight not found.");

  // ✅ Add audit fields for notification
  $flight['modified_by'] = $modified_by;
  $flight['modification_reason'] = $reason;
  $flight['original_start_time'] = date("M j, Y \\a\\t g:i A", strtotime($original['start_time']));
  $flight['original_end_time'] = date("g:i A", strtotime($original['end_time']));

  // ✅ Sync to GCal + Send email
  syncGoogleCalendarUpdate($flight);
  sendFlightChangeNotification($flight, 'updated');

  // ✅ Clear cache
  clearSiteCache();

  echo json_encode(['success' => true]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}



