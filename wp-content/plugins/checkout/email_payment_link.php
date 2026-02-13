<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/mailgun_helper.php');

header('Content-Type: application/json');

$student_id = intval($_POST['student_id'] ?? 0);
$payment_url = trim($_POST['payment_url'] ?? '');

if (!$student_id || !$payment_url) {
  echo json_encode([
    'success' => false,
    'message' => 'Missing student ID or payment URL'
  ]);
  exit;
}

try {
  $db = getDB();
  $stmt = $db->prepare("SELECT first_name, email FROM wp_students WHERE student_id = ?");
  $stmt->execute([$student_id]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$student || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid or missing student email for ID: ' . $student_id
    ]);
    exit;
  }

  $subject = "Your Piston Aviation Payment Link";
  $body = "
    Hi {$student['first_name']},<br><br>
    Your instructor created a secure payment link for you:<br><br>
    <a href=\"{$payment_url}\" target=\"_blank\" style=\"display:inline-block;padding:12px 24px;background:#007bff;color:#fff;border-radius:5px;text-decoration:none;\">Click here to pay now</a><br><br>
    If the button above doesn't work, copy and paste this URL into your browser:<br>
    {$payment_url}<br><br>
    Thank you,<br>
    <strong>Piston Aviation</strong>
  ";

  $result = sendViaMailgunAPI($student['email'], $student['first_name'], $subject, $body);

  if (!is_array($result)) {
    echo json_encode([
      'success' => false,
      'message' => 'Mailgun call did not return a result array.'
    ]);
    exit;
  }

  if ($result['success']) {
    echo json_encode(['success' => true]);
  } else {
    $debugDetails = [
      'student_id' => $student_id,
      'email' => $student['email'],
      'mailgun_result' => $result
    ];

    @file_put_contents(__DIR__ . '/mailgun_error_log.txt', print_r($debugDetails, true), FILE_APPEND);

    echo json_encode([
      'success' => false,
      'message' => $result['error'] ?? 'Unknown error from Mailgun'
    ]);
  }

} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'PHP Exception: ' . $e->getMessage()
  ]);
}
