<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents('php://input'), true);
  $flightId = $data['id'];
  $reason = $data['reason'] ?? 'No reason provided';

  $conn = getDB();

  // Get student info for email
  $stmt = $conn->prepare("
    SELECT s.first_name, s.last_name, s.phone
    FROM wp_flight_students fs
    JOIN wp_students s ON fs.student_id = s.student_id
    WHERE fs.flight_id = ?
  ");
  $stmt->execute([$flightId]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Get full flight info for Google deletion
  $stmt = $conn->prepare("SELECT * FROM wp_flight_schedule WHERE id = ?");
  $stmt->execute([$flightId]);
  $flight = $stmt->fetch(PDO::FETCH_ASSOC);

  // Delete from schedule
  $conn->prepare("DELETE FROM wp_flight_schedule WHERE id = ?")->execute([$flightId]);
  $conn->prepare("DELETE FROM wp_flight_students WHERE flight_id = ?")->execute([$flightId]);

  // Email notifications
  foreach ($students as $s) {
    $payload = json_encode([
      'to' => $s['phone'],
      'subject' => "❌ Flight Canceled",
      'body' => "Hi {$s['first_name']} {$s['last_name']},<br><br>Your flight has been canceled.<br><br>Reason: {$reason}<br><br>- Piston Aviation"
    ]);
    $opts = [
      'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload
      ]
    ];
    file_get_contents("https://amelia-i.com/wp-content/plugins/calendar.v3/send_email_notification.php", false, stream_context_create($opts));
  }

  // Remove from Google
  if ($flight) deleteGoogleCalendarEvent($flight);

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
