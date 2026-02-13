<?php
file_put_contents(__DIR__ . '/logs/email-in-' . time() . '.json', file_get_contents('php://input'));
require_once(__DIR__ . '/db_connect.php');

file_put_contents(__DIR__ . "/logs/incoming-email-" . time() . ".json", file_get_contents("php://input"));

function sendEmailNotification($toEmail, $payload) {
  $apiKey = '71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';
  $domain = 'mg.amelia-i.com';

  $flightType   = $payload['flight_type'] ?? 'Flight';
  $studentName  = $payload['student_name'] ?? 'N/A';
  $tail         = $payload['tail_number'] ?? 'N/A';
  $cfiName      = $payload['cfi_name'] ?? 'N/A';
  $cfiPhone     = $payload['cfi_phone'] ?? 'N/A';
  $studentPhone = $payload['student_phone'] ?? 'N/A';

  $start = date("M d, Y H:i", strtotime($payload['start']));
  $end   = date("M d, Y H:i", strtotime($payload['end']));

  // Reschedule email
  if (isset($payload['modification_reason'])) {
    $modReason = $payload['modification_reason'];
    $modBy = $payload['modified_by'] ?? 'Unknown';

    $subject = "🔁 Flight Rescheduled - {$flightType} - {$studentName}";

    $html = "
      <html>
      <body style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color:#f59e0b;'>🔁 Your Flight Has Been Rescheduled</h2>
        <p><strong>Type:</strong> $flightType</p>
        <p><strong>Student:</strong> $studentName</p>
        <p><strong>Phone:</strong> $studentPhone</p>
        <p><strong>Tail Number:</strong> $tail</p>
        <p><strong>CFI:</strong> $cfiName</p>
        <p><strong>CFI Phone:</strong> $cfiPhone</p>
        <p><strong>New Time:</strong> $start → $end</p>
        <p><strong>Rescheduled By:</strong> $modBy</p>
        <p><strong>Reason:</strong> $modReason</p>
        <br>
        <a href='https://amelia-i.com/wp-content/plugins/calendar.v2/appointment-booking/index.html' style='display:inline-block; background:#1e40af; color:#fff; padding:10px 16px; text-decoration:none; border-radius:5px;'>📆 Confirm or Reschedule Again</a>
        <p style='margin-top: 16px;'>
  <a href='https://pistonup.com' target='_blank'
     style='background:#111827;color:white;padding:10px 16px;border-radius:5px;text-decoration:none;display:inline-block;'>
     🧢 Buy Piston Merch
  </a>
</p>

      </body>
      </html>
    ";

    $postData = [
      'from' => 'Piston Scheduler <no-reply@' . $domain . '>',
      'to' => $toEmail,
      'subject' => $subject,
      'html' => $html
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    curl_exec($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . "/logs/mailgun-response-" . time() . ".json", $result);

    return;
  }

  // Cancellation email
  if (isset($payload['canceled_reason'])) {
    $cancelReason = $payload['canceled_reason'] ?? 'No reason provided';
    $studentRequested = !empty($payload['student_requested']) ? '✅ Yes' : '❌ No';
    $deletedBy = $payload['deleted_by'] ?? 'Unknown';

    $subject = "🔔 Flight Cancellation Notice - $flightType - $studentName";

    $html = "
      <html>
      <body style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color:#dc2626;'>🔔 Your Flight Has Been Cancelled</h2>
        <p><strong>Type:</strong> $flightType</p>
        <p><strong>Student:</strong> $studentName</p>
        <p><strong>Phone:</strong> $studentPhone</p>
        <p><strong>Tail Number:</strong> $tail</p>
        <p><strong>CFI:</strong> $cfiName</p>
        <p><strong>CFI Phone:</strong> $cfiPhone</p>
        <p><strong>Scheduled Flight:</strong> $start → $end</p>
        <p><strong>Cancelled By:</strong> $deletedBy</p>
        <p><strong>Reason:</strong> $cancelReason</p>
        <p><strong>Student Requested?:</strong> $studentRequested</p>
        <br>
        <a href='https://amelia-i.com/wp-content/plugins/calendar.v2/appointment-booking/index.html' style='display:inline-block; background:#1e40af; color:#fff; padding:10px 16px; text-decoration:none; border-radius:5px;'>🔁 Reschedule Your Flight</a>
        <p style='margin-top: 16px;'>
  <a href='https://pistonup.com' target='_blank'
     style='background:#111827;color:white;padding:10px 16px;border-radius:5px;text-decoration:none;display:inline-block;'>
     🧢 Buy Piston Merch
  </a>
</p>

      </body>
      </html>
    ";
  } else {
    // Standard booking
    $subject = "Flight Scheduled - $flightType - $studentName - $tail";

    // Google Calendar Add Link
    $googleLink = sprintf(
      "https://www.google.com/calendar/render?action=TEMPLATE&text=%s&dates=%s/%s&details=%s",
      urlencode($subject),
      date('Ymd\THis\Z', strtotime($payload['start'])),
      date('Ymd\THis\Z', strtotime($payload['end'])),
      urlencode("Tail: $tail
CFI: $cfiName
CFI Phone: $cfiPhone
Student: $studentName")
    );

    // Dynamic student list
    if (!empty($payload['students']) && is_array($payload['students'])) {
      $studentListHtml = "<strong>Students Attending:</strong><br>";
      foreach ($payload['students'] as $s) {
        $studentListHtml .= "{$s['name']} - {$s['phone']}<br>";
      }
    } else {
      $studentListHtml = "
        <p><strong>Student:</strong> $studentName</p>
        <p><strong>Phone:</strong> $studentPhone</p>
      ";
    }

    $html = "
      <html>
      <body style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color:#1e40af;'>🛩️ Your Flight is Scheduled</h2>
        <p><strong>Type:</strong> $flightType</p>
        $studentListHtml
        <p><strong>Tail Number:</strong> $tail</p>
        <p><strong>CFI:</strong> $cfiName</p>
        <p><strong>CFI Phone:</strong> $cfiPhone</p>
        <p><strong>Start:</strong> $start</p>
        <p><strong>End:</strong> $end</p>
        <br>
        <a href='$googleLink' style='display:inline-block; background:#1e40af; color:#fff; padding:10px 16px; text-decoration:none; border-radius:5px;'>➕ Add to Google Calendar</a>
        <p style='margin-top: 16px;'>
  <a href='https://pistonup.com' target='_blank'
     style='background:#111827;color:white;padding:10px 16px;border-radius:5px;text-decoration:none;display:inline-block;'>
     🧢 Buy Piston Merch
  </a>
</p>

      </body>
      </html>
    ";
  }

  $postData = [
    'from' => 'Piston Scheduler <no-reply@' . $domain . '>',
    'to' => $toEmail,
    'subject' => $subject,
    'html' => $html
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

  $result = curl_exec($ch);
  curl_close($ch);

  return $result;
}