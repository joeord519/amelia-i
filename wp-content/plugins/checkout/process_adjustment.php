<?php
require_once(__DIR__ . '/db_connect.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/calendar.v2/vendor/autoload.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$db = getDB();

// AWS Config
$bucket = 'piston-logbooks';
$region = 'us-east-2';
$accessKey = 'AKIAZ3MGNBSXJ2IEQRZH';
$secretKey = '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';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$student_id = intval($_POST['student_id'] ?? 0);
$aircraft = $_POST['adjust_aircraft_hours'] ?? null;
$instructor = $_POST['adjust_instructor_hours'] ?? null;
$reason = trim($_POST['adjust_reason'] ?? '');
$file = $_FILES['adjust_file'] ?? null;

if (!$student_id || $reason === '') {
  echo json_encode(['success' => false, 'message' => 'Missing student ID or reason']);
  exit;
}

try {
  $stmt = $db->prepare("SELECT aircraft_hours_remaining, instructor_hours_remaining FROM wp_students WHERE student_id = ?");
  $stmt->execute([$student_id]);
  $current = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$current) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
  }

  $new_aircraft = is_numeric($aircraft) ? round(floatval($aircraft), 2) : null;
  $new_instructor = is_numeric($instructor) ? round(floatval($instructor), 2) : null;

  $adjusted = false;
  $lastFlightLogId = null;

  $db->beginTransaction();

  // Aircraft adjustment
  if ($new_aircraft !== null && $new_aircraft != $current['aircraft_hours_remaining']) {
    $stmt = $db->prepare("UPDATE wp_students SET aircraft_hours_remaining = ? WHERE student_id = ?");
    $stmt->execute([$new_aircraft, $student_id]);

    $diff = $new_aircraft - $current['aircraft_hours_remaining'];
    $stmt = $db->prepare("INSERT INTO wp_flight_logs (student_id, total_flight_time, flight_category, instructor_notes, status)
                          VALUES (?, ?, 'Manual Adjustment', ?, 'Completed')");
    $stmt->execute([$student_id, $diff, $reason]);
    $lastFlightLogId = $db->lastInsertId();
    $adjusted = true;
  }

  // Instructor adjustment
  if ($new_instructor !== null && $new_instructor != $current['instructor_hours_remaining']) {
    $stmt = $db->prepare("UPDATE wp_students SET instructor_hours_remaining = ? WHERE student_id = ?");
    $stmt->execute([$new_instructor, $student_id]);

    $diff = $new_instructor - $current['instructor_hours_remaining'];
    $stmt = $db->prepare("INSERT INTO wp_flight_logs (student_id, ground_time, flight_category, instructor_notes, status)
                          VALUES (?, ?, 'Manual Adjustment', ?, 'Completed')");
    $stmt->execute([$student_id, $diff, $reason]);
    $lastFlightLogId = $db->lastInsertId();
    $adjusted = true;
  }

  // Upload file to S3 and reference wp_logbook_uploads
  if ($file && $file['tmp_name']) {
    $filename = basename($file['name']);
    $mime = mime_content_type($file['tmp_name']);

    $s3 = new S3Client([
      'region' => $region,
      'version' => 'latest',
      'credentials' => [
        'key' => $accessKey,
        'secret' => $secretKey,
      ]
    ]);

    $key = 'logbooks/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    $s3->putObject([
      'Bucket' => $bucket,
      'Key' => $key,
      'SourceFile' => $file['tmp_name'],
      'ContentType' => $mime,
      'ACL' => 'private'
    ]);

    $s3_url = "https://$bucket.s3.$region.amazonaws.com/$key";

    $stmt = $db->prepare("INSERT INTO wp_logbook_uploads 
  (flight_log_id, student_id, file_url, file_type, upload_type) 
  VALUES (?, ?, ?, ?, 'adjustment')");

$stmt->execute([
  $lastFlightLogId ?? 0,
  $student_id,
  $s3_url,
  $mime
]);

  }

  $db->commit();
  echo json_encode(['success' => $adjusted]);
} catch (Exception $e) {
  if ($db->inTransaction()) $db->rollBack();
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

