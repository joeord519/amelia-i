<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once PISTON_SPINNER_LOCAL_DBCONNECT;

if (SPINNER_ALLOW_ORIGIN) {
  header('Access-Control-Allow-Origin: ' . SPINNER_ALLOW_ORIGIN);
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

/* Payload:
 * { "student_id":123, "reward_token":"uuid", "hours_purchased":5.0 }
 */
try {
  $prefix = SPINNER_DB_PREFIX;
  $in = json_decode(file_get_contents('php://input'), true);
  $student_id = intval($in['student_id'] ?? 0);
  $token = trim($in['reward_token'] ?? '');
  $hours_purchased = floatval($in['hours_purchased'] ?? 0);
  if ($student_id <= 0 || !$token) throw new Exception('Invalid payload');

  $pdo->beginTransaction();

  $q = $pdo->prepare("SELECT * FROM {$prefix}spin_rewards WHERE token=? FOR UPDATE");
  $q->execute([$token]);
  $r = $q->fetch();
  if (!$r) { $pdo->commit(); echo json_encode(['ok'=>true,'info'=>'unknown_token']); exit; }
  if ((int)$r['student_id'] !== $student_id) { $pdo->commit(); echo json_encode(['ok'=>true,'info'=>'token_student_mismatch']); exit; }
  if ($r['status'] !== 'provisional') { $pdo->commit(); echo json_encode(['ok'=>true,'info'=>'not_provisional']); exit; }

  if (new DateTime() > new DateTime($r['expires_at'])) {
    $pdo->prepare("UPDATE {$prefix}spin_rewards SET status='expired' WHERE id=?")->execute([$r['id']]);
    $pdo->commit(); echo json_encode(['ok'=>true,'expired'=>1]); exit;
  }
  if ($hours_purchased < floatval($r['minimum_purchase_hours'])) {
    $pdo->commit(); echo json_encode(['ok'=>true,'min_purchase_not_met'=>1]); exit;
  }

  // Apply hour bonuses
  $bonus_ac  = floatval($r['bonus_ac_hours']);
  $bonus_cfi = floatval($r['bonus_cfi_hours']);
  if ($bonus_ac > 0 || $bonus_cfi > 0) {
    $up = $pdo->prepare("UPDATE {$prefix}students
      SET aircraft_hours = aircraft_hours + ?,
          instructor_hours = instructor_hours + ?
      WHERE id=?");
    $up->execute([$bonus_ac, $bonus_cfi, $student_id]);
  }

  $pdo->prepare("UPDATE {$prefix}spin_rewards SET status='claimed', claimed_at=NOW() WHERE id=?")
      ->execute([$r['id']]);

  $pdo->commit();
  echo json_encode(['ok'=>true,'claimed'=>1]);
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
