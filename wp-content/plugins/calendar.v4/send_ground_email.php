<?php
function sendGroundLessonEmail($toEmail, $payload) {
  $domain = 'mg.amelia-i.com';
  $apiKey = '71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';

  file_put_contents(__DIR__ . "/logs/ground_log_check.txt", "✅ Function triggered for $toEmail\n", FILE_APPEND);
  file_put_contents(__DIR__ . "/logs/ground_email_payload.txt", print_r($payload, true), FILE_APPEND);

  $flightType   = $payload['flight_type'] ?? 'Ground Lesson';
  $studentName  = $payload['student_name'] ?? 'Student';
  $studentPhone = $payload['student_phone'] ?? 'N/A';
  $cfiName      = $payload['cfi_name'] ?? 'N/A';
  $cfiPhone     = $payload['cfi_phone'] ?? 'N/A';
  $start        = date("M d, Y H:i", strtotime($payload['start'] ?? ''));
  $end          = date("M d, Y H:i", strtotime($payload['end'] ?? ''));
  $googleLink = sprintf(
    "https://www.google.com/calendar/render?action=TEMPLATE&text=%s&dates=%s/%s&details=%s",
    urlencode("Ground Lesson - $studentName"),
    date('Ymd\THis\Z', strtotime($payload['start'])),
    date('Ymd\THis\Z', strtotime($payload['end'])),
    urlencode("CFI: $cfiName\nPhone: $cfiPhone")
  );

  $subject = "📚 Ground Lesson Scheduled - $studentName";

  $html = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; color: #333;'>
      <div style='max-width: 600px; margin: auto; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);'>
        <h2 style='color:#1e40af;'>🛩️ Ground Lesson Scheduled</h2>
        <p>Hi <strong>$studentName</strong>,</p>
        <p>Your ground lesson has been scheduled. Here are the details:</p>
        <table style='width:100%; margin-top: 16px; border-collapse: collapse;'>
          <tr><td><strong>Type:</strong></td><td>$flightType</td></tr>
          <tr><td><strong>CFI:</strong></td><td>$cfiName</td></tr>
          <tr><td><strong>CFI Phone:</strong></td><td>$cfiPhone</td></tr>
          <tr><td><strong>Your Phone:</strong></td><td>$studentPhone</td></tr>
          <tr><td><strong>Start:</strong></td><td>$start</td></tr>
          <tr><td><strong>End:</strong></td><td>$end</td></tr>
        </table>
        <p style='margin-top: 20px;'>Please be on time and bring any required materials. ✏️</p>
        <p style='margin-top: 24px;'>
          <a href='$googleLink' style='background:#2563eb;color:white;padding:10px 16px;border-radius:5px;text-decoration:none;'>➕ Add to Google Calendar</a>
        </p>
        <p style='margin-top: 24px;'>
  <a href='https://pistonup.com' target='_blank' 
     style='background:#111827;color:#fff;padding:10px 16px;border-radius:5px;text-decoration:none;display:inline-block;'>
     🧢 Buy Piston Merch
  </a>
</p>

        <p style='margin-top: 24px;'>✈️ - Piston Aviation</p>
      </div>
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

  $result = curl_exec($ch);
  curl_close($ch);

  file_put_contents(__DIR__ . "/logs/ground_email_result.txt", $result . "\n", FILE_APPEND);

  return $result;
}

