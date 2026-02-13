<?php
require_once(__DIR__ . '/db_connect.php');
date_default_timezone_set('America/Chicago');

$conn = getDB();

$aircraftMultipliers = [
  'FMX' => 2.0,
  'N1847J' => 1.5,
  'N2723B' => 1.0,
  'N5714F' => 1.0,
  'N1830J' => 1.0,
  'N445LM' => 1.5,
  'N374EA' => 1.3,
  'N447EA' => 1.2,
  'N144AA' => 1.5,
  'N33SA' => 2.0,
  'N7970F' => 1.1,
  'N7294A' => 1.2
];

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// ✅ Daily logs
$logStmt = $conn->prepare("SELECT f.*, s.first_name AS student_name, s.last_name AS student_last, c.first_name AS cfi_first, c.last_name AS cfi_last, c.wing_id FROM wp_flight_logs f LEFT JOIN wp_students s ON f.student_id = s.student_id LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id WHERE f.flight_date = ?");
$logStmt->execute([$today]);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$wings = [];
foreach ($logs as $log) {
  if (isset($log['flight_type']) && stripos($log['flight_type'], 'CFI-Event') !== false) continue;
  $wingId = $log['wing_id'] ?? 0;
  if (!isset($wings[$wingId])) $wings[$wingId] = ['flight_hours' => 0, 'ground_hours' => 0, 'score' => 0];
  $duration = floatval($log['total_flight_time']);
  $tail = strtoupper(trim($log['tail_number']));
  $type = strtolower($log['appointment_type'] ?? '');
  $multiplier = $aircraftMultipliers[$tail] ?? 1.0;
  if ($type === 'ground') {
    $wings[$wingId]['ground_hours'] += $duration;
    $wings[$wingId]['score'] += $duration;
  } else {
    $wings[$wingId]['flight_hours'] += $duration;
    $wings[$wingId]['score'] += $duration * $multiplier;
  }
}

// ✅ Monthly logs
$monthStmt = $conn->prepare("SELECT f.*, c.wing_id FROM wp_flight_logs f LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id WHERE f.flight_date BETWEEN ? AND ?");
$monthStmt->execute([$monthStart, $today]);
$monthLogs = $monthStmt->fetchAll(PDO::FETCH_ASSOC);

$monthlyWings = [];
foreach ($monthLogs as $log) {
  if (isset($log['flight_type']) && stripos($log['flight_type'], 'CFI-Event') !== false) continue;
  $wingId = $log['wing_id'] ?? 0;
  if (!isset($monthlyWings[$wingId])) $monthlyWings[$wingId] = ['flight_hours' => 0, 'ground_hours' => 0, 'score' => 0];
  $duration = floatval($log['total_flight_time']);
  $tail = strtoupper(trim($log['tail_number']));
  $type = strtolower($log['appointment_type'] ?? '');
  $multiplier = $aircraftMultipliers[$tail] ?? 1.0;
  if ($type === 'ground') {
    $monthlyWings[$wingId]['ground_hours'] += $duration;
    $monthlyWings[$wingId]['score'] += $duration;
  } else {
    $monthlyWings[$wingId]['flight_hours'] += $duration;
    $monthlyWings[$wingId]['score'] += $duration * $multiplier;
  }
}

$wingMeta = [];
$wingStmt = $conn->prepare("SELECT * FROM wp_cfi_wings WHERE status = 'Active'");
$wingStmt->execute();
foreach ($wingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $wingMeta[$row['id']] = $row;
}

$schedStmt = $conn->prepare("SELECT fs.*, s.first_name, s.last_name, c.first_name AS cfi_first, c.last_name AS cfi_last FROM wp_flight_schedule fs LEFT JOIN wp_students s ON fs.student_id = s.student_id LEFT JOIN wp_cfis c ON fs.cfi_id = c.cfi_id WHERE DATE(fs.start_time) = ? ORDER BY fs.start_time");
$schedStmt->execute([$today]);
$scheduledFlights = array_filter($schedStmt->fetchAll(PDO::FETCH_ASSOC), function($f) {
  return !(isset($f['flight_type']) && stripos($f['flight_type'], 'CFI-Event') !== false);
});

$flyingStmt = $conn->prepare("SELECT f.*, s.first_name, s.last_name, c.first_name AS cfi_first, c.last_name AS cfi_last, c.wing_id FROM wp_flight_logs f LEFT JOIN wp_students s ON f.student_id = s.student_id LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id WHERE f.status = 'Flying'");
$flyingStmt->execute();
$flyingNow = array_filter($flyingStmt->fetchAll(PDO::FETCH_ASSOC), function($f) {
  return !(isset($f['flight_type']) && stripos($f['flight_type'], 'CFI-Event') !== false);
});
?>

<!DOCTYPE html>
<html>
<head>
  <title>CFI Wing Leaderboard</title>
  <meta charset="UTF-8">
  <style>
    body { background: #000; color: #fff; font-family: 'Orbitron', sans-serif; overflow: hidden; margin: 0; padding: 0; }
    .section { height: 100vh; width: 100%; display: none; flex-direction: column; align-items: center; justify-content: center; }
    .slide-container { position: relative; width: 100%; height: 100vh; }
    .leaderboard-box { width: 90%; max-width: 1200px; margin: 0 auto; text-align: center; }
    .score-row { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #555; }
    .card { background: #111; border-radius: 10px; padding: 10px 20px; margin: 10px 0; font-size: 1.2em; }
  </style>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const slideDurations = [5000, 15000, 7000, 8000];
      const slides = document.querySelectorAll('.section');
      let currentSlide = 0;
      function showSlide(index) {
        slides.forEach((slide, i) => {
          slide.style.display = i === index ? 'flex' : 'none';
        });
        setTimeout(() => {
          currentSlide = (index + 1) % slides.length;
          showSlide(currentSlide);
        }, slideDurations[index]);
      }
      showSlide(0);
    });
  </script>
</head>
<body>
<div class="slide-container">
  <div class="section">
    <div class="leaderboard-box">
      <h1>🏆 Wing Scores (Today)</h1>
      <?php foreach ($wings as $wingId => $data): ?>
        <?php $meta = $wingMeta[$wingId] ?? ['wing_name' => 'Unknown', 'logo_url' => '']; ?>
        <div class="score-row">
          <img src="<?= $meta['logo_url'] ?>" alt="Logo" height="40">
          <span><?= $meta['wing_name'] ?></span>
          <span><?= round($data['flight_hours'], 1) ?> hrs</span>
          <span>Ground: <?= round($data['ground_hours'], 1) ?> hrs</span>
          <span>Score: <?= round($data['score'], 1) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="leaderboard-box">
      <h1>🗓️ Scheduled Flights Today</h1>
      <?php foreach ($scheduledFlights as $f): ?>
        <div class="card">
          <?= $f['first_name'] . ' ' . $f['last_name'] ?> ➞ <?= $f['cfi_first'] . ' ' . $f['cfi_last'] ?>
          (<?= $f['tail_number'] ?> @ <?= date("g:i A", strtotime($f['start_time'])) ?>)
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="leaderboard-box">
      <h1>🛫 Aircraft Currently Flying</h1>
      <?php foreach ($flyingNow as $f): ?>
        <?php $wingName = $wingMeta[$f['wing_id']]['wing_name'] ?? 'Unknown'; ?>
        <div class="card">
          <?= $f['tail_number'] ?> - <?= $f['first_name'] . ' ' . $f['last_name'] ?> ➞ <?= $f['cfi_first'] . ' ' . $f['cfi_last'] ?> (<?= $wingName ?>)
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div class="leaderboard-box">
      <h1>🎉 Month-To-Date Wing Scores</h1>
      <?php foreach ($monthlyWings as $wingId => $data): ?>
        <?php $meta = $wingMeta[$wingId] ?? ['wing_name' => 'Unknown', 'logo_url' => '']; ?>
        <div class="score-row">
          <img src="<?= $meta['logo_url'] ?>" alt="Logo" height="40">
          <span><?= $meta['wing_name'] ?></span>
          <span><?= round($data['flight_hours'], 1) ?> hrs</span>
          <span>Ground: <?= round($data['ground_hours'], 1) ?> hrs</span>
          <span>Score: <?= round($data['score'], 1) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</body>
</html>
