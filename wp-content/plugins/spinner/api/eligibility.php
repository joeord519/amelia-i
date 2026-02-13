<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once PISTON_SPINNER_LOCAL_DBCONNECT;

if (defined('SPINNER_ALLOW_ORIGIN') && SPINNER_ALLOW_ORIGIN) {
  header('Access-Control-Allow-Origin: ' . SPINNER_ALLOW_ORIGIN);
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

try {
  $prefix = SPINNER_DB_PREFIX;

  $in = json_decode(file_get_contents('php://input'), true);
  $student_id = intval($in['student_id'] ?? 0);
  $context = preg_replace('/[^a-z_]/i', '', ($in['context'] ?? ''));
  if ($student_id <= 0 || !$context) throw new Exception('Invalid parameters');

  // Helper: pick first existing field name from a list
  $pick = function(array $row, array $candidates, $default = null) {
    foreach ($candidates as $k) {
      if (array_key_exists($k, $row)) return $row[$k];
    }
    return $default;
  };

  // Load student row (no hard-coded columns)
  $stmt = $pdo->prepare("SELECT * FROM {$prefix}students WHERE student_id = ? LIMIT 1");
  $stmt->execute([$student_id]);
  $stu = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$stu) { echo json_encode(['eligible'=>false,'reason'=>'student_not_found']); exit; }

  // Optional blocks (tolerant to missing columns)
  $do_not_spin = (int)($pick($stu, ['do_not_spin','spin_block','no_spin'], 0) ?? 0);
  if ($do_not_spin) { echo json_encode(['eligible'=>false,'reason'=>'do_not_spin']); exit; }

  $program_code = (string)$pick($stu, ['program_code','program','cohort_code'], '');
  if ($program_code && in_array($program_code, $GLOBALS['PISTON_SPINNER_BLOCKED_PROGRAMS'] ?? [], true)) {
    echo json_encode(['eligible'=>false,'reason'=>'program_block']); exit;
  }

  // Hour balances (try multiple common field names; default 0)
  $ac  = (float)$pick($stu, ['aircraft_hours','aircraft_balance','ac_hours','aircraft_hours_remaining'], 0);
  $cfi = (float)$pick($stu, ['instructor_hours','instructor_balance','cfi_hours','instructor_hours_remaining'], 0);

  // High-balance gate (optional)
  if ($ac >= 20 && $cfi >= 20) { echo json_encode(['eligible'=>false,'reason'=>'high_balance']); exit; }

  // Cooldown per-context
  $hours = ($context === 'checkout_success')
    ? PISTON_SPINNER_CHECKOUT_COOLDOWN_HOURS
    : PISTON_SPINNER_BOOKING_COOLDOWN_HOURS;

  $cd = $pdo->prepare("SELECT created_at FROM {$prefix}spin_eligibility
                       WHERE student_id=? AND context=? ORDER BY id DESC LIMIT 1");
  $cd->execute([$student_id, $context]);
  if ($row = $cd->fetch(PDO::FETCH_ASSOC)) {
    $until = (new DateTime($row['created_at']))->modify("+{$hours} hours");
    if (new DateTime() < $until) {
      echo json_encode(['eligible'=>false,'reason'=>'cooldown','cooldown_until'=>$until->format('Y-m-d H:i:s')]); exit;
    }
  }

  // Monthly cap
  $monthStart = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
  $cap = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}spin_rewards
                        WHERE student_id=? AND created_at >= ?");
  $cap->execute([$student_id, $monthStart]);
  if (PISTON_SPINNER_MONTHLY_REWARD_LIMIT > 0 && (int)$cap->fetchColumn() >= PISTON_SPINNER_MONTHLY_REWARD_LIMIT) {
    echo json_encode(['eligible'=>false,'reason'=>'monthly_cap']); exit;
  }

  // If we got here, they’re eligible
  echo json_encode(['eligible'=>true, 'profile_key'=>'standard_nudge']);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['eligible'=>false,'error'=>$e->getMessage()]);
}
