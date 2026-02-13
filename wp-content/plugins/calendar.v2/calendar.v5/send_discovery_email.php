<?php
function sendDiscoveryEmail($data) {
  $domain = 'mg.amelia-i.com';
  $apiKey = '71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';

  // Debug log to confirm what data is passed
  error_log("📧 Discovery Email Data: " . json_encode($data));

  $studentName  = $data['student_name'] ?? 'Student';
  $studentEmail = $data['student_email'] ?? '';
  $studentPhone = $data['student_phone'] ?? '';
  $flightType   = $data['flight_type'] ?? 'Discovery Flight';
  $startTime    = isset($data['start_time']) ? date("l, F jS \\a\\t g:i A", strtotime($data['start_time'])) : 'TBD';
  $tailNumber   = $data['tail_number'] ?? 'Unknown';

  $subject = "🌟 Your Discovery Flight is Booked!";

  $html = "
    <div style='font-family: Arial, sans-serif; font-size: 16px; color: #222;'>
      <img src='https://amelia-i.com/wp-content/plugins/calendar.v4/assets/email/PistonAviationLogo.jpg' 
           alt='Piston Aviation Logo' 
           style='width:100%; max-width:175px; margin-bottom: 30px;' />

      <p>Hey <strong>$studentName</strong>,</p>

      <p style='margin-bottom: 25px;'>
        Your <strong>$flightType</strong> is scheduled for 
        <span style='color: #2c3e50; font-weight: bold;'>$startTime</span> 
        in aircraft <strong>$tailNumber</strong>.
      </p>

      <div style='background-color: #f0f8ff; padding: 15px; border-radius: 6px; border-left: 4px solid #007bff; margin-bottom: 20px;'>
        ✈️ This is it — your first step into the sky. We'll be waiting with a headset and a high-five.
      </div>

      <p>
        If you're already thinking “Could I actually become a pilot?” — the answer is 
        <strong style='color: #28a745;'>hell yes.</strong>
      </p>

      <p>
        Our team at <strong>Piston Aviation</strong> offers career-changing training programs and 
        <strong>zero-down financing</strong> through our partners at Stratus.
      </p>

      <div style='text-align: center; margin-top: 30px;'>
        <a href='https://apply.stratus.finance/pistonaviation8450001' 
           style='background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin-right: 10px;'>
           💰 Explore Financing Options
        </a>
        <a href='https://pistonup.com' 
           style='background-color: #22c55e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
           🧢 Get Piston Merch
        </a>
      </div>

      <p style='margin-top: 40px; font-size: 14px;'>
        Questions? Text or call us at <a href='tel:6363283750' style='color:#007bff;'>(636) 328-3750</a>.
      </p>

      <p style='margin-top: 40px; font-size: 18px; font-weight: bold;'>Let’s fly. 🚀</p>
      <p style='font-size: 16px;'>– Your Crew at Piston Aviation</p>
    </div>
  ";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
  curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'from'    => 'Piston Aviation <info@flypiston.com>',
    'to'      => $studentEmail,
    'subject' => $subject,
    'html'    => $html
  ]);

  $result = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    error_log("❌ Mailgun Error [$httpCode]: $result");
  } else {
    error_log("✅ Discovery Email sent to $studentEmail");
  }

  return $httpCode === 200;
}
