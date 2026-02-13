<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

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
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .back-btn {
            background-color: #007bff;
            color: white;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
            display: inline-block;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
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
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn.edit { background-color: #ff9800; color: white; }
        .btn.available { background-color: #d4edda; color: #155724; }
        .btn.maint { background-color: #fff3cd; color: #856404; }
        .btn.out { background-color: #f8d7da; color: #721c24; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

<a href="/wp-content/plugins/checkout/admin-panel.php" class="back-btn">← Back to Dashboard</a>
<h2>Aircraft Management</h2>

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
                    <button class="btn edit" title="Edit Aircraft" onclick='addOrEditAircraft(<?= json_encode($a) ?>)'>✏️</button>
                    <button class="btn available" title="Set Available" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Available')">✅</button>
                    <button class="btn maint" title="Set Maintenance" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Maintenance')">🛠️</button>
                    <button class="btn out" title="Set Out of Service" onclick="updateStatus('<?= $a['tail_number'] ?>', 'Out of Service')">❌</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// ... JS unchanged (except for color picker) ...
</script>
</body>
</html>
