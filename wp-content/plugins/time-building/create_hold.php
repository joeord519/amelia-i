<?php
// /wp-content/plugins/time-building/create_hold.php
require_once __DIR__ . '/db_connect.php'; // provides $pdo
header('Content-Type: application/json');

$leadId  = (int)($_POST['lead_id'] ?? 0);
$weekIds = array_map('intval', $_POST['week_ids'] ?? []);
$weekIds = array_slice(array_unique($weekIds), 0, 4); // max 4

if (!$leadId || !$weekIds) {
  http_response_code(400);
  echo json_encode(['status'=>'error','message'=>'Missing lead_id or week_ids']);
  exit;
}

try {
  $pdo->beginTransaction();

  // 1) Lock weeks & check capacity: count PAID + ACTIVE holds only
  $in = implode(',', array_fill(0, count($weekIds), '?'));
  $qry = $pdo->prepare("
    SELECT
      w.id,
      w.total_capacity,
      COALESCE(SUM(
        CASE
          WHEN r.status = 'paid' THEN 1
          WHEN r.status = 'hold' AND r.expires_at > NOW() THEN 1
          ELSE 0
        END
      ), 0) AS reserved
    FROM wp_tb_weeks w
    LEFT JOIN wp_tb_reservations r ON r.week_id = w.id
    WHERE w.id IN ($in)
    GROUP BY w.id
    FOR UPDATE
  ");
  $qry->execute($weekIds);

  $okWeeks = [];
  while ($row = $qry->fetch(PDO::FETCH_ASSOC)) {
    if ((int)$row['reserved'] < (int)$row['total_capacity']) {
      $okWeeks[] = (int)$row['id'];
    }
  }
  if (!$okWeeks) {
    $pdo->rollBack();
    echo json_encode(['status'=>'error','message'=>'No capacity available for selected weeks']);
    exit;
  }

  // 2) Upsert holds (15-min expiration) — idempotent per (lead, week)
  //    Requires UNIQUE KEY (week_id, lead_id)
  $upsert = $pdo->prepare("
    INSERT INTO wp_tb_reservations (week_id, lead_id, status, expires_at)
    VALUES (?, ?, 'hold', DATE_ADD(NOW(), INTERVAL 15 MINUTE))
    ON DUPLICATE KEY UPDATE
      status = VALUES(status),
      expires_at = VALUES(expires_at),
      id = LAST_INSERT_ID(id)
  ");

  $reservationIds = [];
  foreach ($okWeeks as $wid) {
    $upsert->execute([$wid, $leadId]);
    $reservationIds[] = (int)$pdo->lastInsertId(); // existing id on duplicate
  }

  $pdo->commit();
  echo json_encode([
    'status' => 'ok',
    'held_count' => count($okWeeks),
    'reservation_ids' => $reservationIds
  ]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}


