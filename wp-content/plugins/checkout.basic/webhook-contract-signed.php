<?php
header('Content-Type: application/json');

$SHARED_SECRET = '4xCu4Y0Kh#xpSh)gVfbY!{@U;@E(6-';

$raw = file_get_contents('php://input');

file_put_contents(
  __DIR__ . '/contract_webhook_log.txt',
  "[" . date('c') . "] RAW: " . $raw . "\nSIG: " . ($_SERVER['HTTP_X_PISTON_SIGNATURE'] ?? 'NONE') . "\n\n",
  FILE_APPEND
);

$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
  exit;
}

$providedSig = $_SERVER['HTTP_X_PISTON_SIGNATURE'] ?? '';
$computedSig = hash_hmac('sha256', $raw, $SHARED_SECRET);
if (!hash_equals($computedSig, $providedSig)) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Bad signature']);
  exit;
}

$studentId = intval($data['student_id'] ?? 0);
if ($studentId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid student_id']);
  exit;
}

define('WP_USE_THEMES', false);
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

global $wpdb;

// Candidate tables
$prefixed = $wpdb->prefix . 'students';
$fallback = 'wp_students';

// Detect which table exists
$prefixedExists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $prefixed)) === $prefixed);
$fallbackExists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $fallback)) === $fallback);

if ($prefixedExists) {
  $table = $prefixed;
} elseif ($fallbackExists) {
  $table = $fallback;
} else {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'No students table found',
    'tried' => [$prefixed, $fallback],
    'db' => $wpdb->dbname
  ]);
  exit;
}

file_put_contents(
  __DIR__ . '/contract_webhook_log.txt',
  "[" . date('c') . "] DB=" . $wpdb->dbname . " prefix=" . $wpdb->prefix . " USING_TABLE=" . $table . " student_id=" . $studentId . "\n",
  FILE_APPEND
);

$updated = $wpdb->update(
  $table,
  [
    'latest_contract_signed'    => 1,
    'latest_contract_signed_at' => current_time('mysql')
  ],
  [ 'student_id' => $studentId ],
  [ '%d', '%s' ],
  [ '%d' ]
);

file_put_contents(
  __DIR__ . '/contract_webhook_log.txt',
  "[" . date('c') . "] UPDATE_RESULT=" . var_export($updated, true) . " LAST_ERROR=" . $wpdb->last_error . "\n\n",
  FILE_APPEND
);

if ($updated === false) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'DB update failed',
    'db_error' => $wpdb->last_error,
    'table' => $table
  ]);
  exit;
}

echo json_encode([
  'ok' => true,
  'student_id' => $studentId,
  'table' => $table
]);


