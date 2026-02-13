<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/appointment-booking/google_calendar_push.php');
$conn = getDB();

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['flight_id'])) {
  echo json_encode(["success" => false, "message" => "Missing flight_id"]);
  exit;
}

$flight_id = $input['flight_id'];

// Retrieve Google event ID and CFI ID from the DB
$stmt = $conn->prepare("SELECT google_event_id, cfi_id FROM wp_flight_schedule WHERE id = ?");
$stmt->execute([$flight_id]);
$flight = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flight) {
  echo json_encode(["success" => false, "message" => "Flight not found"]);
  exit;
}

// Delete from DB
$stmt = $conn->prepare("DELETE FROM wp_flight_schedule WHERE id = ?");
$stmt->execute([$flight_id]);

// Remove from Google Calendar if linked
if (!empty($flight['google_event_id'])) {
  $gcal_result = deleteGoogleCalendarEvent([
    "google_event_id" => $flight['google_event_id'],
    "cfi_id" => $flight['cfi_id']
  ]);

  if (!$gcal_result['success']) {
    echo json_encode(["success" => false, "message" => "Deleted from DB but failed to remove from Google Calendar"]);
    exit;
  }
}

echo json_encode(["success" => true]);
?>