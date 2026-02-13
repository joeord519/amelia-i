<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!function_exists('getDB')) {
  require_once(__DIR__ . '/db_connect.php');
}

$conn = getDB();
$subject = $_POST['subject'] ?? '';
$body = $_POST['body'] ?? '';
$sendMode = $_POST['sendMode'] ?? 'test';

if (!$subject || !$body) {
    http_response_code(400);
    echo "Missing subject or body.";
    exit;
}

function sendViaMailgunAPI($to, $name, $subject, $htmlBody) {
    $apiKey = 'api:71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';
    $domain = 'mg.amelia-i.com';
    $url = "https://api.mailgun.net/v3/$domain/messages";

    $postData = [
        'from'    => 'Piston Aviation <postmaster@mg.amelia-i.com>',
        'to'      => "$name <$to>",
        'subject' => $subject,
        'html'    => $htmlBody,
        'o:tracking-clicks' => 'no' // 👈 disables automatic link rewriting
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($info['http_code'] === 200) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $error ?: $result];
    }
}

if ($sendMode === 'test') {
    $result = sendViaMailgunAPI('joe@flypiston.com', 'Joe', $subject, $body);
    if ($result['success']) {
        echo "✅ Test email sent via Mailgun API.";
    } else {
        echo "❌ Mailgun API error: " . $result['error'];
    }
    exit;
}

$stmt = $conn->prepare("SELECT first_name, email FROM wp_students WHERE status = 'active' AND email IS NOT NULL AND email != ''");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent = 0;
$failed = 0;

foreach ($students as $s) {
    $finalBody = str_replace('{{first_name}}', $s['first_name'], $body);
    $result = sendViaMailgunAPI($s['email'], $s['first_name'], $subject, $finalBody);

    $log = $conn->prepare("INSERT INTO wp_email_log (subject, student_name, student_email, status, error_message) VALUES (?, ?, ?, ?, ?)");
    $log->execute([
        $subject,
        $s['first_name'],
        $s['email'],
        $result['success'] ? 'sent' : 'failed',
        $result['success'] ? null : $result['error']
    ]);

    $result['success'] ? $sent++ : $failed++;
    usleep(250000);
}

echo "📬 Live email send complete. Sent: $sent | Failed: $failed";
