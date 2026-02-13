<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once PISTON_SPINNER_LOCAL_DBCONNECT;

if (defined('SPINNER_ALLOW_ORIGIN') && SPINNER_ALLOW_ORIGIN) {
  header('Access-Control-Allow-Origin', SPINNER_ALLOW_ORIGIN);
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

function uuidv4() {
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
  $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

try {
  $prefix = SPINNER_DB_PREFIX;

  $in = json_decode(file_get_contents('php://input'), true);
  $student_id  = intval($in['student_id'] ?? 0);
  $profile_key = preg_replace('/[^a-z_]/i', '', ($in['profile_key'] ?? 'standard_nudge'));
  if ($student_id <= 0) throw new Exception('Invalid student');

  // One active provisional at a time
  $already = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}spin_rewards
                            WHERE student_id=? AND status='provisional' AND expires_at > NOW()");
  $already->execute([$student_id]);
  if ($already->fetchColumn() > 0) {
    echo json_encode(['ok'=>false,'msg'=>'You already have an unclaimed reward.']); exit;
  }

  // Load slices
  $prof = $pdo->prepare("SELECT slices_json FROM {$prefix}spin_profiles WHERE `key`=? AND active=1");
  $prof->execute([$profile_key]);
  $row = $prof->fetch();
  if (!$row) throw new Exception('Profile not found or inactive');

  $slices = json_decode($row['slices_json'], true);
  if (!is_array($slices) || !count($slices)) throw new Exception('Invalid profile');

  // Weighted pick
  $sum = 0; foreach ($slices as $s) $sum += (int)$s['w'];
  $r = random_int(1, max(1,$sum));
  $acc = 0; $pick = $slices[0];
  foreach ($slices as $s) { $acc += (int)$s['w']; if ($r <= $acc) { $pick = $s; break; } }

  // Expiry: store local DB datetime, but return UTC timestamp/ISO for the client
  $expires_db  = (new DateTime('+24 hours'))->format('Y-m-d H:i:s'); // used in SQL checks
  $expires_ts  = time() + 24*60*60;                                  // UTC epoch (seconds)
  $expires_iso = gmdate('c', $expires_ts);                            // e.g. 2025-08-27T03:25:00Z

  $token = uuidv4();

  $ins = $pdo->prepare("INSERT INTO {$prefix}spin_rewards
    (student_id, profile_key, prize_code, prize_label, discount_percent, bonus_ac_hours, bonus_cfi_hours,
     minimum_purchase_hours, token, expires_at, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,'provisional')");
  $ins->execute([
    $student_id, $profile_key,
    $pick['code'], $pick['label'],
    floatval($pick['disc'] ?? 0),
    floatval($pick['ac'] ?? 0),
    floatval($pick['cfi'] ?? 0),
    floatval(PISTON_SPINNER_MIN_PURCHASE_HOURS),
    $token, $expires_db
  ]);

  $purchase_url = PISTON_SPINNER_PURCHASE_URL
    . (strpos(PISTON_SPINNER_PURCHASE_URL, '?') === false ? '?' : '&')
    . 'reward_token=' . urlencode($token) . '&sid=' . intval($student_id);

  echo json_encode([
    'ok'           => true,
    'prize_code'   => $pick['code'],
    'prize_label'  => $pick['label'],
    'token'        => $token,
    // legacy/local string (kept for debugging/compat)
    'expires_at'   => $expires_db,
    // ✅ timezone-safe fields to use in JS
    'expires_ts'   => $expires_ts,
    'expires_iso'  => $expires_iso,
    'purchase_url' => $purchase_url
  ]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
