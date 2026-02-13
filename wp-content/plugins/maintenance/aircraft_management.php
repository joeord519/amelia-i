<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/includes/db_connect.php');
$conn = getDB();

session_start();
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['tech_id'])) {
  echo "<p style='padding:20px;'>Access denied. Please log in as an admin or maintenance tech.";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tail_number'], $_POST['new_status'])) {
        $stmt = $conn->prepare("UPDATE wp_aircraft SET status = ? WHERE tail_number = ?");
        $stmt->execute([$_POST['new_status'], $_POST['tail_number']]);
        echo '✅ Status Updated';
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_or_update') {
        $data = array_map('trim', $_POST);
        unset($data['action']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM wp_aircraft WHERE tail_number = ?");
        $stmt->execute([$data['tail_number']]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE wp_aircraft SET model = ?, aircraft_type = ?, latest_hobbs_time = ?, latest_tach_time = ?, label_color = ? WHERE tail_number = ?");
            $stmt->execute([$data['model'], $data['aircraft_type'], $data['latest_hobbs_time'], $data['latest_tach_time'], $data['label_color'], $data['tail_number']]);
            echo '✅ Aircraft Updated';
        } else {
            $stmt = $conn->prepare("INSERT INTO wp_aircraft (tail_number, model, aircraft_type, latest_hobbs_time, latest_tach_time, status, label_color) VALUES (?, ?, ?, ?, ?, 'Available', ?)");
            $stmt->execute([$data['tail_number'], $data['model'], $data['aircraft_type'], $data['latest_hobbs_time'], $data['latest_tach_time'], $data['label_color']]);
            echo '✅ Aircraft Added';
        }
        exit;
    }

    if ($_POST['action'] === 'update_equipment') {
        $stmt = $conn->prepare("UPDATE wp_aircraft SET equipment = ? WHERE tail_number = ?");
        $stmt->execute([$_POST['new_equipment'], $_POST['tail_number']]);
        echo '✅ Equipment Updated';
        exit;
    }

    if ($_POST['action'] === 'update_airport') {
        $stmt = $conn->prepare("UPDATE wp_aircraft SET home_airport = ? WHERE tail_number = ?");
        $stmt->execute([$_POST['new_home_airport'], $_POST['tail_number']]);
        echo '✅ Airport Updated';
        exit;
    }
}

$aircraft = $conn->query("SELECT * FROM wp_aircraft ORDER BY tail_number ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>

<html>
<head>
<title>Manage Aircraft</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
    .back-btn {
      background-color: #007bff;
      color: white;
      padding: 8px 14px;
      text-decoration: none;
      border-radius: 6px;
      margin-bottom: 20px;
      display: inline-block;
    }
    h2 { text-align: center; margin-bottom: 10px; }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 40px;
    }

    th {
      background-color: #333;
      color: #fff;
      font-weight: normal;
      padding: 12px;
      text-align: center;
    }

    td {
      padding: 10px;
      text-align: center;
      border-bottom: 1px solid #eee;
    }

    tr.row-maintenance { background-color: #fff8e1; }
    tr.row-out { background-color: #ffebee; }

    .btn-group {
      display: flex;
      justify-content: center;
      gap: 6px;
    }

    .btn {
      font-size: 16px;
      padding: 8px 10px;
      font-weight: bold;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: 0.15s;
    }
    .btn:hover { transform: scale(1.1); opacity: 0.9; }
    .btn.edit { background-color: #ff9800; color: white; }
    .btn.available { background-color: #d4edda; color: #155724; }
    .btn.maint { background-color: #fff3cd; color: #856404; }
    .btn.out { background-color: #f8d7da; color: #721c24; }

    .tab-button {
      padding: 10px 16px;
      font-weight: bold;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      background: #ccc;
      color: #333;
      transition: 0.2s ease;
    }
    .tab-button.active {
      background: #2563eb;
      color: white;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
<a class="back-btn" href="/wp-content/plugins/maintenance/tech-login.php">← Log Out</a>
<h2>Aircraft Dashboard</h2>
<!-- TAB NAVIGATION -->
<div style="display:flex; gap:20px; margin-bottom:20px;">
<button class="tab-button active" id="tabAircraft" onclick="switchTab('aircraftTab')">✈️ Aircraft List</button>
<button class="tab-button" id="tabMaintenance" onclick="switchTab('maintenanceTab')">🛠 Scheduled Maintenance</button>
</div>
<!-- TAB 1: Aircraft Management -->
<div id="aircraftTab">
<table>
<thead>
<tr><th>Tail #</th><th>Model</th><th>Type</th><th>Manufacturer</th><th>Airport</th><th>Hobbs</th><th>Tach</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($aircraft as $a): 
        $row_class = ($a['status'] === 'Maintenance') ? 'row-maintenance' : (($a['status'] === 'Out of Service') ? 'row-out' : '');
      ?>
<tr class="<?= $row_class ?>">
<td><?= htmlspecialchars($a['tail_number'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['model'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['aircraft_type'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['manufacturer'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['home_airport'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['latest_hobbs_time'] ?? '', ENT_QUOTES) ?></td>
<td><?= htmlspecialchars($a['latest_tach_time'] ?? '', ENT_QUOTES) ?></td>
<td><strong><?= htmlspecialchars($a['status'] ?? '', ENT_QUOTES) ?></strong></td>
<td>
<div class="btn-group">
<button class="btn edit" onclick="addOrEditAircraft(<?= json_encode($a) ?>)" title="Edit Aircraft">✏️</button>
<button class="btn available" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Available')" title="Set Available">✅</button>
<button class="btn maint" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Maintenance')" title="Set Maintenance">🛠️</button>
<button class="btn out" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Out of Service')" title="Set Out of Service">❌</button>
<button class="btn maint" onclick="openMaintenanceModal('<?= $a['tail_number'] ?>')">🗓️</button>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<!-- TAB 2: Scheduled Maintenance -->
<div id="maintenanceTab" style="display:none;">
<h3>Upcoming Maintenance Events</h3>
<button onclick="openMaintenanceModal()">➕ Add Maintenance Event</button>
<form method="GET" style="margin-bottom: 20px;">
<label for="filter_tail">Filter by Aircraft:</label>
<select id="filter_tail" name="filter_tail" onchange="this.form.submit()">
<option value="">Show All</option>
<?php
    $tails = $conn->query("SELECT DISTINCT tail_number FROM wp_aircraft ORDER BY tail_number ASC")->fetchAll();
    foreach ($tails as $t) {
  $selected = ($_GET['filter_tail'] ?? '') === $t['tail_number'] ? 'selected' : '';
  echo "<option $selected value='{$t['tail_number']}'>{$t['tail_number']}</option>";
}

    ?>
  </select>
</form>
<table>
<thead>
<tr><th>Tail #</th><th>Type</th><th>Tracking</th><th>Due In</th><th>Next Due</th><th>Notes</th><th></th></tr>
</thead>
<tbody>
<?php
      $filterTail = $_GET['filter_tail'] ?? '';
$query = "
  SELECT ms.*, ac.latest_tach_time 
  FROM wp_maintenance_schedule ms
  JOIN wp_aircraft ac ON ms.tail_number = ac.tail_number
";

if (!empty($filterTail)) {
  $query .= " WHERE ms.tail_number = :tail ";
}

$query .= " ORDER BY 
  CASE 
    WHEN ms.tracking_type = 'TACH' THEN (ms.last_performed_tach + ms.interval_hours - ac.latest_tach_time)
    WHEN ms.tracking_type = 'DATE' THEN DATEDIFF(DATE_ADD(ms.last_performed_date, INTERVAL ms.interval_days DAY), CURDATE())
    ELSE 9999
  END ASC
";

$stmt = $conn->prepare($query);
if (!empty($filterTail)) {
  $stmt->execute([':tail' => $filterTail]);
} else {
  $stmt->execute();
}

$events = $stmt->fetchAll();

      foreach ($events as $e):
        $dueIn = "N/A";
        $nextDue = "N/A";

        if ($e['tracking_type'] === 'TACH') {
          $next = $e['last_performed_tach'] + $e['interval_hours'];
          $dueIn = round($next - $e['latest_tach_time'], 1) . " hrs";
          $nextDue = number_format($next, 1) . " Tach";
        } elseif ($e['tracking_type'] === 'DATE') {
          $nextDate = date('Y-m-d', strtotime("{$e['last_performed_date']} +{$e['interval_days']} days"));
          $dueIn = (new DateTime())->diff(new DateTime($nextDate))->format('%r%a days');
          $nextDue = $nextDate;
        }
      ?>
      <tr>
<td><?= htmlspecialchars($e['tail_number']) ?></td>
<td><?= htmlspecialchars($e['event_type']) ?></td>
<td><?= $e['tracking_type'] ?></td>
<td><?= $dueIn ?></td>
<td><?= $nextDue ?></td>
<td><?= htmlspecialchars($e['notes']) ?></td>
<td><?php /* Work Session button will go here */ ?>
</td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<script>
function switchTab(id) {
  document.getElementById('aircraftTab').style.display = 'none';
  document.getElementById('maintenanceTab').style.display = 'none';
  document.getElementById(id).style.display = 'block';

  document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
  if (id === 'aircraftTab') document.getElementById('tabAircraft').classList.add('active');
  if (id === 'maintenanceTab') document.getElementById('tabMaintenance').classList.add('active');
}

// 👇 Force correct tab on page load if filter is present
window.addEventListener('DOMContentLoaded', () => {
  const isFiltered = new URLSearchParams(window.location.search).has('filter_tail');
  if (isFiltered) switchTab('maintenanceTab');
});
</script>
</body>
</html>
