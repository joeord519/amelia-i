<?php
session_start();
require_once(__DIR__ . '/db_connect.php');
$db = getDB();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$cfi_id = $_SESSION['cfi_id'] ?? 0;
if (!$cfi_id) {
  echo "<p style='padding:20px;'>Access denied. Please log in.</p>";
  exit;
}

// Fetch rate data
$stmt = $db->prepare("SELECT base_pay_rate, pay_cap_override, starting_pay_rate FROM wp_cfis WHERE cfi_id = :id");
$stmt->execute([':id' => $cfi_id]);
$rateData = $stmt->fetch(PDO::FETCH_ASSOC);
$starting_rate = floatval($rateData['starting_pay_rate'] ?? 45.00);

$stmt = $db->prepare("
  SELECT SUM(total_flight_time)
  FROM wp_flight_logs
  WHERE cfi_id = :id AND flight_category = 'Student Flight'
");
$stmt->execute([':id' => $cfi_id]);
$total_hours = floatval($stmt->fetchColumn() ?? 0);

$rate = $starting_rate;
if ($total_hours >= 1000) { $rate = 50; }
elseif ($total_hours >= 900) { $rate = 49; }
elseif ($total_hours >= 800) { $rate = 48; }
elseif ($total_hours >= 700) { $rate = 47; }
elseif ($total_hours >= 600) { $rate = 46; }

if (!empty($rateData['pay_cap_override'])) {
  $rate = floatval($rateData['pay_cap_override']);
}

$stmt = $db->prepare("SELECT setting_value FROM wp_company_settings WHERE setting_key = 'cfi_pay_cutoff_date' LIMIT 1");
$stmt->execute();
$cutoff_date = $stmt->fetchColumn() ?: '2025-05-31';

echo "<h2>💰 CFI Payroll Summary</h2>";
echo "<p>Logs after <strong>$cutoff_date</strong> are included in 'Outstanding Pay'.</p>";

echo "<h3>👷 My Current Pay Rate</h3>";
echo "<p><strong>\$" . number_format($rate, 2) . "/hr</strong></p>";

$next_raise = null;
if ($total_hours < 600) $next_raise = 600 - $total_hours;
elseif ($total_hours < 700) $next_raise = 700 - $total_hours;
elseif ($total_hours < 800) $next_raise = 800 - $total_hours;
elseif ($total_hours < 900) $next_raise = 900 - $total_hours;
elseif ($total_hours < 1000) $next_raise = 1000 - $total_hours;

echo $next_raise !== null ?
  "<p>You need <strong>" . number_format($next_raise, 1) . "</strong> more dual hours to reach your next raise.</p>" :
  "<p>🎉 You've hit the max raise tier. Great work!</p>";

$stmt = $db->prepare("
  SELECT flight_category, SUM(total_flight_time) AS flight, SUM(ground_time) AS ground
  FROM wp_flight_logs
  WHERE cfi_id = :id AND flight_date > :cutoff
  GROUP BY flight_category
");
$stmt->execute([':id' => $cfi_id, ':cutoff' => $cutoff_date]);
$logResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPay = 0;
$sof_pay = 0;
$sof_ground = 0;

echo "<h3>🧾 Outstanding Pay</h3>";
echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Type</th><th>Flight</th><th>Ground</th><th>Pay</th></tr>";
foreach ($logResults as $row) {
  $flight = floatval($row['flight']);
  $ground = floatval($row['ground']);
  $type = $row['flight_category'];
  $pay = 0;
  if ($type === 'SOF Time') {
    $sof_pay = $ground * ($rate * 0.5);
    $sof_ground = $ground;
    continue;
  } else {
    $pay = ($flight + $ground) * $rate;
  }
  $totalPay += $pay;
  echo "<tr><td>{$type}</td><td>{$flight}</td><td>{$ground}</td><td>$" . number_format($pay, 2) . "</td></tr>";
}
if ($sof_pay > 0) {
  $totalPay += $sof_pay;
  echo "<tr><td>SOF Time (50%)</td><td>0</td><td>{$sof_ground}</td><td>$" . number_format($sof_pay, 2) . "</td></tr>";
}
echo "<tr><td colspan='3'><strong>Total Due</strong></td><td><strong>$" . number_format($totalPay, 2) . "</strong></td></tr>";
echo "</table>";

// Payment History with JS pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
  SELECT * FROM wp_cfi_payments
  WHERE cfi_id = :id
  ORDER BY pay_date DESC
  LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $cfi_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count_stmt = $db->prepare("SELECT COUNT(*) FROM wp_cfi_payments WHERE cfi_id = :id");
$count_stmt->execute([':id' => $cfi_id]);
$total_rows = intval($count_stmt->fetchColumn());
$total_pages = ceil($total_rows / $limit);

echo "<h3 style='margin-top:30px;'>📜 My Payment History</h3>";
if ($payments) {
  echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>Pay Date</th><th>Period Start</th><th>Period End</th><th>Amount</th><th>Notes</th></tr>";
  foreach ($payments as $p) {
    $pay_date = $p['pay_date'];
    $start = $p['pay_period_start'] ?? '';
    $end = $p['pay_period_end'] ?? '';
    $amount = number_format($p['amount'], 2);
    $notes = htmlspecialchars($p['notes'] ?? '');
    echo "<tr><td>$pay_date</td><td>$start</td><td>$end</td><td>$$amount</td><td>$notes</td></tr>";
  }
  echo "</table><div style='margin-top:10px;'>";

  if ($page > 1) {
    echo "<a href='#' onclick='loadPaySummary(" . ($page - 1) . ")'>&laquo; Prev</a> ";
  }
  if ($page < $total_pages) {
    echo "<a href='#' onclick='loadPaySummary(" . ($page + 1) . ")'>Next &raquo;</a>";
  }
  echo "</div>";
} else {
  echo "<p>No payment records found.</p>";
}
?>
