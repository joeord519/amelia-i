<?php
require_once('db_connect.php');
$conn = getDB();

// ✅ Debugging: Turn on error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ Capture and sanitize input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$cleaned_input = preg_replace("/[^0-9]/", "", $input['login_input'] ?? '');

if (!$cleaned_input) {
  echo json_encode(['status' => 'error', 'reason' => 'invalid input']);
  exit;
}

// ✅ Query matching only digits from phone or by student ID
$query = $conn->prepare("
  SELECT student_id, first_name, last_name, phone, email, home_airport,
         aircraft_hours_remaining, instructor_hours_remaining,
         cfi_1_id, cfi_2_id, profile_photo_url, security_word
  FROM wp_students
  WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
");

$query->execute([$cleaned_input]);
$student = $query->fetch(PDO::FETCH_ASSOC);

if (!$student) {
  echo json_encode(['status' => 'error', 'reason' => 'student not found']);
  exit;
}

// ✅ Output full student data
$student['status'] = 'ready';
echo json_encode($student);
exit;
