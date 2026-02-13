<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  if (!isset($input['flight_id'])) throw new Exception("Missing flight ID");

  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_flight_schedule WHERE id = ?");
  $stmt->execute([$input['flight_id']]);
  $flight = $stmt->fetch();

  if (!$flight) throw new Exception("Flight not found");

  deleteGoogleCalendarEvent($flight);

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
