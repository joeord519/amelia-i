<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../checkout/db_connect.php';

// Fetch employees
$employees = $conn->query("SELECT id, full_name FROM wp_employees WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle filters
$selected_employee = $_GET['employee_id'] ?? '';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

$query = "
  SELECT t.*, e.full_name
  FROM wp_time_log t
  JOIN wp_employees e ON t.employee_id = e.id
  WHERE DATE(t.checkin_time) BETWEEN :start AND :end
";

$params = [':start' => $start_date, ':end' => $end_date];

if ($selected_employee) {
  $query .= " AND t.employee_id = :emp";
  $params[':emp'] = $selected_employee;
}

$query .= " ORDER BY t.checkin_time DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Employee Timesheets</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
    th { background-color: #f4f4f4; }
    .auto { color: red; font-weight: bold; }
  </style>
</head>
<body>

<h2>🧾 Employee Timesheet Dashboard</h2>

<form method="get">
  <label>Employee:
    <select name="employee_id">
      <option value="">All</option>
      <?php foreach ($employees as $emp): ?>
        <option value="<?= $emp['id'] ?>" <?= ($emp['id'] == $selected_employee) ? 'selected' : '' ?>>
          <?= htmlspecialchars($emp['full_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Start Date:
    <input type="date" name="start" value="<?= $start_date ?>">
  </label>

  <label>End Date:
    <input type="date" name="end" value="<?= $end_date ?>">
  </label>

  <button type="submit">🔍 Filter</button>
</form>

<table>
  <tr>
    <th>Employee</th>
    <th>Clock In</th>
    <th>Clock Out</th>
    <th>Total Hours</th>
    <th>Status</th>
  </tr>
  <?php foreach ($logs as $log): 
    $hours = '';
    $status = '✅';
    if ($log['checkin_time'] && $log['checkout_time']) {
      $checkin = strtotime($log['checkin_time']);
      $checkout = strtotime($log['checkout_time']);
      $hours = round(($checkout - $checkin) / 3600, 2);
    } else {
      $status = '⏳ Still clocked in';
    }
    if ($log['auto_checkout']) $status = '<span class="auto">⚠️ Auto-Chec_]()
