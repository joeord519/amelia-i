<?php
// /wp-content/plugins/time-building/payment_webhook.php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/assign_aircraft.php';

$paymentRef = trim($_POST['payment_ref'] ?? '');
$resIds     = array_values(array_filter(array_map('intval', $_POST['reservation_ids'] ?? [])));
$amount     = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : null;

if (!$paymentRef || !$resIds) {
  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>'Missing payment_ref or reservation_ids']);
  exit;
}

try {
  $pdo->beginTransaction();

  // ✅ Safe NOT NULL value for flight_type_id (adjust later if you add a dedicated ID)
  $flightTypeId = 1;

  // 1) Mark holds as PAID (positional params only)
  $in   = implode(',', array_fill(0, count($resIds), '?'));
  $sql  = "
    UPDATE wp_tb_reservations
    SET status = 'paid', payment_ref = ?, amount_paid = ?, expires_at = NULL
    WHERE id IN ($in) AND status = 'hold'
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge([$paymentRef, $amount], $resIds));

  // 2) Get affected weeks
  $wq = $pdo->prepare("SELECT DISTINCT week_id FROM wp_tb_reservations WHERE id IN ($in)");
  $wq->execute($resIds);
  $weeks = array_map('intval', $wq->fetchAll(PDO::FETCH_COLUMN));

  // 3) Assign aircraft for each affected week (2-per-plane rule is inside this helper)
  foreach ($weeks as $wid) {
    assignAircraftForWeek($wid);
  }

  // 4) Insert calendar blockers (one per (week, tail)), deduping by tail+time window+slot_status
  $fetch = $pdo->prepare("
    SELECT w.week_start, w.week_end, r.tail_number
    FROM wp_tb_weeks w
    JOIN wp_tb_reservations r ON r.week_id = w.id
    WHERE w.id = ? AND r.status = 'paid' AND r.tail_number IS NOT NULL
    GROUP BY r.tail_number
  ");

  $exists = $pdo->prepare("
    SELECT `id` FROM `wp_flight_schedule`
    WHERE `tail_number` = ?
      AND `start_time` = ?
      AND `end_time`   = ?
      AND `slot_status` = 'Blocked'
    LIMIT 1
  ");

  $ins = $pdo->prepare("
    INSERT INTO `wp_flight_schedule` (
      `id`, `flight_type_id`, `flight_type`, `tail_number`, `home_airport`, `cfi_id`, `lead_id`, `student_id`,
      `start_time`, `end_time`, `status`, `slot_status`, `event_title`, `created_at`, `updated_at`
    ) VALUES (
      NULL, ?, 'Time Building', ?, '1H0', NULL, NULL, NULL,
      ?, ?, 'Scheduled', 'Blocked', ?, NOW(), NOW()
    )
  ");

  foreach ($weeks as $wid) {
    $fetch->execute([$wid]);
    while ($row = $fetch->fetch(PDO::FETCH_ASSOC)) {
      $start = $row['week_start'] . ' 00:00:00';
      $end   = $row['week_end']   . ' 23:59:59';
      $tail  = $row['tail_number'];
      $title = "Time Building – RESERVED ($tail)";

      $exists->execute([$tail, $start, $end]);
      if ($exists->fetchColumn()) {
        continue; // already blocked for this tail+window
      }

      $ins->execute([$flightTypeId, $tail, $start, $end, $title]);
    }
  }

  $pdo->commit();
  echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
