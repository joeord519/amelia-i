<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  $id        = intval($input['id'] ?? 0);
  $original  = floatval($input['original_time'] ?? 0);
  $flight    = floatval($input['flight_time'] ?? 0);
  $reason    = trim($input['discrepancy_reason'] ?? '');
  $ground    = floatval($input['ground_time'] ?? 0);
  $notes     = trim($input['notes'] ?? '');
  $signature = trim($input['signature'] ?? '');
  $reviewed  = intval($input['reviewed'] ?? 0);

  if (!$id || !$notes || !$signature) {
    throw new Exception("Missing required fields.");
  }

  if ($flight !== $original && $reason === '') {
    throw new Exception("Discrepancy reason required if flight time changed.");
  }

  $db = getDB();

  $check = $db->prepare("SELECT cfi_log_submitted FROM wp_flight_logs WHERE id = :id LIMIT 1");
  $check->execute([':id' => $id]);
  if ($check->fetchColumn()) {
    echo json_encode(['status' => 'error', 'message' => 'This log has already been submitted.']);
    exit;
  }

  $signaturePath = null;
  if (preg_match('/^data:image\/png;base64,/', $signature)) {
    $signature = base64_decode(preg_replace('/^data:image\/png;base64,/', '', $signature));
    $sigFile = "signatures/cfi_sig_{$id}_" . time() . ".png";
    file_put_contents(__DIR__ . '/' . $sigFile, $signature);
    $signaturePath = $sigFile;
  } else {
    throw new Exception("Invalid or missing signature data.");
  }

  $stmt = $db->prepare("
    UPDATE wp_flight_logs
    SET
      total_flight_time = :flight,
      instructor_discrepancy_reason = :reason,
      ground_time = :ground,
      instructor_notes = :notes,
      cfi_signature = :signature,
      reviewed = :reviewed,
      cfi_log_submitted = 1
    WHERE id = :id
  ");
  $stmt->execute([
    ':flight'    => $flight,
    ':reason'    => $reason,
    ':ground'    => $ground,
    ':notes'     => $notes,
    ':signature' => $signaturePath,
    ':reviewed'  => $reviewed,
    ':id'        => $id
  ]);

  $stmt = $db->prepare("SELECT student_id FROM wp_flight_logs WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $log = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!empty($log['student_id']) && $ground > 0) {
    $studentId = $log['student_id'];
    $stmt = $db->prepare("
      UPDATE wp_students
      SET instructor_hours_remaining = instructor_hours_remaining - :ground
      WHERE student_id = :student_id
    ");
    $stmt->execute([
      ':ground' => $ground,
      ':student_id' => $studentId
    ]);
  }

  echo json_encode(['status' => 'success', 'message' => '✅ CFI log submitted and ground time deducted.']);

} catch (Throwable $e) {
  error_log("❌ ERROR: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>









