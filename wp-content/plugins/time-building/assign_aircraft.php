<?php
// /wp-content/plugins/time-building/assign_aircraft.php
// expects global $pdo

function assignAircraftForWeek(int $weekId) {
  global $pdo;

  // Program aircraft in priority order
  $planes = $pdo->query("
    SELECT tail_number
    FROM wp_tb_aircraft
    WHERE is_active = 1
    ORDER BY priority_order ASC
  ")->fetchAll(PDO::FETCH_COLUMN);

  if (!$planes) return;

  // Current per-aircraft counts (holds+paid with a tail already set)
  $cntStmt = $pdo->prepare("
    SELECT tail_number, COUNT(*) AS c
    FROM wp_tb_reservations
    WHERE week_id = :wid AND status IN ('hold','paid') AND tail_number IS NOT NULL
    GROUP BY tail_number
  ");
  $cntStmt->execute([':wid'=>$weekId]);
  $counts = array_fill_keys($planes, 0);
  foreach ($cntStmt as $r) $counts[$r['tail_number']] = (int)$r['c'];

  // Paid, unassigned reservations—lock while assigning
  $resStmt = $pdo->prepare("
    SELECT id
    FROM wp_tb_reservations
    WHERE week_id = :wid AND status = 'paid' AND tail_number IS NULL
    ORDER BY id ASC
    FOR UPDATE
  ");
  $resStmt->execute([':wid'=>$weekId]);

  $upd = $pdo->prepare("UPDATE wp_tb_reservations SET tail_number = :tail WHERE id = :id");

  while ($row = $resStmt->fetch()) {
    foreach ($planes as $tail) {
      if ($counts[$tail] < 2) { // 2 per plane
        $upd->execute([':tail'=>$tail, ':id'=>(int)$row['id']]);
        $counts[$tail]++;
        break;
      }
    }
    // If all planes at 2, we silently stop assigning (week at capacity).
  }
}
