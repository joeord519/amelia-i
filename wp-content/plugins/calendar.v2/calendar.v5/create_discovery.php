<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_discovery_email.php');
require_once(__DIR__ . '/includes/aircraft_availability.php');
header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  $required = ['student_name', 'student_phone', 'student_email', 'aircraft_tail', 'cfi_id', 'date', 'time'];
  foreach ($required as $field) {
    if (empty($input[$field])) {
      throw new Exception("Missing: $field");
    }
  }

  $name = trim($input['student_name']);
  $nameParts = explode(' ', $name, 2);
  $firstName = $nameParts[0];
  $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
  $phone  = trim($input['student_phone']);
  $email  = trim($input['student_email']);
  $tail   = trim($input['aircraft_tail']);
  $cfiId  = intval($input['cfi_id']);
  $date   = $input['date'];
  $time   = $input['time'];
  $notes  = trim($input['notes'] ?? '');

  $start = "$date $time:00";
  $endTimestamp = strtotime($start) + (1 * 60 * 60);
  $end = date("Y-m-d H:i:s", $endTimestamp);

  $db = getDB();

  $availabilityReason = '';
  if (!check_aircraft_available($db, $tail, $start, $end, $availabilityReason)) {
    throw new Exception($availabilityReason);
  }

  // 1️⃣ Insert into wp_leads
  $stmt = $db->prepare("
    INSERT INTO wp_leads (first_name, last_name, phone, email, lead_source, notes, created_at)
    VALUES (?, ?, ?, ?, 'Discovery Flight', ?, NOW())
  ");
  $stmt->execute([$firstName, $lastName, $phone, $email, $notes]);
  $leadId = $db->lastInsertId();

  error_log("🛫 About to insert discovery flight for $name | lead_id = $leadId");

  // 2️⃣ Insert into wp_flight_schedule
  $stmt = $db->prepare("
    INSERT INTO wp_flight_schedule (
      flight_type, tail_number, cfi_id, lead_id, start_time, end_time
    ) VALUES (
      'Discovery Flight', ?, ?, ?, ?, ?
    )
  ");
  $stmt->execute([
    $tail,
    $cfiId,
    $leadId,
    $start,
    $end
  ]);

  $flightId = $db->lastInsertId();
  error_log("✅ Discovery Flight ID = $flightId");

  // 3️⃣ Push to Google Calendar via cURL to push_google_event.php
  $payload = json_encode(['flight_id' => $flightId]);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://amelia-i.com/wp-content/plugins/calendar.v4/push_google_event.php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  error_log("📤 Google Sync Response [$httpCode]: $response");

  // 4️⃣ Send confirmation email
  sendDiscoveryEmail([
    'student_name'   => $name,
    'student_phone'  => $phone,
    'student_email'  => $email,
    'flight_type'    => 'Discovery Flight',
    'start_time'     => $start,
    'tail_number'    => $tail
  ]);

  // 5️⃣ Return success to frontend
  echo json_encode([
  'success' => true,
  'event' => [
    'id'         => $flightId,
    'title'      => "Discovery Flight - $name - $phone",
    'start'      => $start,
    'end'        => $end,
    'resourceId' => $tail,
    'extendedProps' => [
      'student_name'         => $name,
      'student_phone'        => $phone,
      'future_student_name'  => $name,
      'future_student_phone' => $phone,
      'tail_number'          => $tail,
      'flight_type'          => 'Discovery Flight',
      'flight_id'            => $flightId
    ]
  ]
]);

} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}

