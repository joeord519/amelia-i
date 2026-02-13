<?php
require_once(__DIR__ . '/db_connect.php');

/**
 * Push an event to a CFI's Google Calendar.
 * This function is called from create_company_event.php and others.
 *
 * @param array $eventData Must include:
 *   - flight_id
 *   - cfi_id
 *   - title
 *   - start
 *   - end
 *   - (optional) is_company_event
 */
function pushGoogleEvent($eventData) {
  try {
    // ✅ Validate input
    if (empty($eventData['flight_id']) || empty($eventData['cfi_id'])) {
      throw new Exception("Missing flight ID or CFI ID.");
    }

    $flightId = $eventData['flight_id'];
    $cfiId    = $eventData['cfi_id'];
    $title    = $eventData['title'] ?? 'Flight';
    $start    = $eventData['start'] ?? '';
    $end      = $eventData['end'] ?? '';
    $isCompany = $eventData['is_company_event'] ?? false;

    if (!$start || !$end) {
      throw new Exception("Missing start or end time.");
    }

    // ✅ Fetch Google token from CFI record
    $db = getDB();
    $stmt = $db->prepare("SELECT gcal_token FROM wp_cfis WHERE id = ?");
    $stmt->execute([$cfiId]);
    $cfi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfi || empty($cfi['gcal_token'])) {
      throw new Exception("No Google token found for CFI ID $cfiId");
    }

    $token = $cfi['gcal_token'];

    // ✅ Log push attempt
    $logTitle = $isCompany ? "🗕️ Company Event" : "✈️ Flight Event";
    error_log("$logTitle → $title ($start → $end) for CFI ID $cfiId [Flight ID: $flightId]");

    // 🔧 Replace with actual Google API logic using $token
    // This is where you'd use Google Client, Calendar insert call, etc.

    // ✅ If you implement full push, update wp_flight_schedule.google_event_id here

    return true;

  } catch (Exception $e) {
    error_log("❌ Google Calendar Push Failed: " . $e->getMessage());
    return false;
  }
}
