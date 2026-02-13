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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Aircraft</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <style>
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

<body class="bg-light">
  <div class="container mt-4">
    <div class="row">
      <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>
      <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0">✈️ Aircraft Management</h2>
          <a href="/wp-content/plugins/checkout/admin-panel.php" class="btn btn-sm btn-dark">← Back to Dashboard</a>
        </div>

        <table>
          <thead>
            <tr>
              <th>Tail #</th>
              <th>Model</th>
              <th>Type</th>
              <th>Manufacturer</th>
              <th>Airport</th>
              <th>Hobbs</th>
              <th>Tach</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($aircraft as $a): 
              $row_class = ($a['status'] === 'Maintenance') ? 'row-maintenance' : (($a['status'] === 'Out of Service') ? 'row-out' : '');
            ?>
            <tr class="<?= $row_class ?>">
              <td><?= htmlspecialchars($a['tail_number'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['model'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['aircraft_type'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['manufacturer'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['home_airport'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['latest_hobbs_time'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['latest_tach_time'] ?? '') ?></td>
              <td><strong><?= htmlspecialchars($a['status'] ?? '') ?></strong></td>
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
      </div> <!-- end col-md-9 -->
    </div> <!-- end row -->
  </div> <!-- end container -->

<script>
function updateStatus(tail, newStatus) {
  fetch("", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ tail_number: tail, new_status: newStatus })
  }).then(() => {
    window.location.href = window.location.href.split('?')[0] + '?t=' + new Date().getTime();
  });
}

function addOrEditAircraft(data = {}) {
  Swal.fire({
    title: data.tail_number ? 'Edit Aircraft' : 'Add Aircraft',
    html: `
      <div><strong>Tail Number:</strong> ${data.tail_number || "N/A"}</div>
      <div><strong>Model:</strong> ${data.model || "N/A"}</div>
      <div><strong>Type:</strong> ${data.aircraft_type || "N/A"}</div>
      <div><strong>Manufacturer:</strong> ${data.manufacturer || "N/A"}</div>
      <div><strong>Home Airport:</strong> <span id="homeAirport">${data.home_airport || "N/A"}</span>
        <button id="changeAirportBtn">Change</button></div>
      <div><strong>Equipment:</strong>
        <button id="editEquipmentBtn">Check Equipment</button></div>
      <label>Aircraft Color:
        <input type="color" id="labelColorPicker" style="width: 100%; height: 40px; border: none;">
      </label>
      <input id="hobbs" class="swal2-input" placeholder="Latest Hobbs" value="${data.latest_hobbs_time || ''}">
      <input id="tach" class="swal2-input" placeholder="Latest Tach" value="${data.latest_tach_time || ''}">
    `,
    confirmButtonText: data.tail_number ? 'Update' : 'Add',
    didOpen: () => {
      document.getElementById("labelColorPicker").value = data.label_color || "#2563eb";
      document.getElementById('changeAirportBtn')?.addEventListener('click', () => {
        changeAirport(data.tail_number, data.home_airport);
      });
      document.getElementById('editEquipmentBtn')?.addEventListener('click', () => {
        editEquipment(data.tail_number, data.equipment);
      });
    },
    preConfirm: () => {
      return fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "add_or_update",
          tail_number: data.tail_number,
          model: data.model,
          aircraft_type: data.aircraft_type,
          latest_hobbs_time: document.getElementById("hobbs").value,
          latest_tach_time: document.getElementById("tach").value,
          label_color: document.getElementById("labelColorPicker").value
        })
      }).then(r => r.text());
    }
  }).then(result => {
    if (result.isConfirmed) Swal.fire("✅ Saved!", "", "success").then(() => location.reload());
  });
}

function editEquipment(tail, current) {
  const prompt = current && current.trim() !== ""
    ? {
        title: "Current Equipment for " + tail,
        text: current,
        showCancelButton: true,
        confirmButtonText: "Edit Equipment"
      }
    : {
        title: "No Equipment Listed",
        text: "Please enter the equipment for this aircraft.",
        icon: "info",
        confirmButtonText: "Update Equipment"
      };

  Swal.fire(prompt).then(result => {
    if (result.isConfirmed) {
      showEquipmentEditPrompt(tail, current || "");
    }
  });
}

function showEquipmentEditPrompt(tailNumber, value) {
  Swal.fire({
    title: "Edit Equipment for " + tailNumber,
    input: "textarea",
    inputValue: value,
    showCancelButton: true,
    confirmButtonText: "Save",
    preConfirm: (newEquip) => {
      return fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "update_equipment",
          tail_number: tailNumber,
          new_equipment: newEquip
        })
      }).then(r => r.text());
    }
  }).then(result => {
    if (result.isConfirmed) Swal.fire("✅ Updated", "", "success").then(() => location.reload());
  });
}

function changeAirport(tail, current) {
  Swal.fire({
    title: "Change Home Airport for " + tail,
    input: "text",
    inputLabel: "New Airport Code",
    inputValue: current || "",
    showCancelButton: true,
    confirmButtonText: "Update",
    preConfirm: (code) => {
      return fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "update_airport",
          tail_number: tail,
          new_home_airport: code
        })
      }).then(r => r.text());
    }
  }).then(result => {
    if (result.isConfirmed) Swal.fire("✅ Airport Changed", "", "success").then(() => location.reload());
  });
}
</script>
</body>
</html>

