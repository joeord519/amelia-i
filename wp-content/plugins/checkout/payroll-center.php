<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connect.php';
$conn = getDB();

function getCompanySetting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM wp_company_settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    return $stmt->fetchColumn();
}

$cutoff = getCompanySetting($conn, 'cfi_pay_cutoff_date');
$cfis = $conn->query("SELECT * FROM wp_cfis WHERE status = 'Active' ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);

$cfi_data = [];

foreach ($cfis as $cfi) {
    $cfi_id = $cfi['cfi_id'];
    $rate = floatval($cfi['base_pay_rate'] ?? 0);
    $cap = floatval($cfi['pay_cap_override'] ?? 0);
    $draw = $cfi['on_draw'] === 'Yes';
    $draw_amount = floatval($cfi['draw_amount'] ?? 0);

    $stmt = $conn->prepare("
        SELECT flight_category, SUM(total_flight_time) AS flight, SUM(ground_time) AS ground
        FROM wp_flight_logs
        WHERE cfi_id = :id AND flight_date > :cutoff AND (cfi_payment_id IS NULL OR cfi_payment_id = 0 OR cfi_payment_id = '')
        GROUP BY flight_category
    ");
    $stmt->execute([':id' => $cfi_id, ':cutoff' => $cutoff]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dual_flight = 0;
    $dual_ground = 0;
    $sof_ground = 0;

    foreach ($logs as $log) {
        $cat = $log['flight_category'];
        $flight = floatval($log['flight']);
        $ground = floatval($log['ground']);
        if ($cat === 'SOF Time') {
            $sof_ground += $ground;
        } else {
            $dual_flight += $flight;
            $dual_ground += $ground;
        }
    }

    $dual_total = ($dual_flight + $dual_ground) * $rate;
    $sof_total = $sof_ground * ($rate * 0.5);

    $stmt = $conn->prepare("SELECT SUM(amount) FROM wp_cfi_pay_adjustments WHERE cfi_id = :id");
    $stmt->execute([':id' => $cfi_id]);
    $adjustment = floatval($stmt->fetchColumn() ?? 0);

    $total_due = $dual_total + $sof_total;
    if ($cap && $total_due > $cap) $total_due = $cap;

    $final_due = $draw ? max($draw_amount, $total_due) : $total_due;
    $final_due += $adjustment;

    $cfi_data[] = [
        'id' => $cfi_id,
        'name' => "{$cfi['first_name']} {$cfi['last_name']}",
        'dual_flight_pay' => round($dual_flight * $rate, 2),
        'dual_ground_pay' => round($dual_ground * $rate, 2),
        'sof_pay' => round($sof_total, 2),
        'earned' => round($total_due, 2),
        'draw' => $draw ? 'Yes' : 'No',
        'draw_amount' => $draw_amount,
        'adjustment' => $adjustment,
        'final_due' => round($final_due, 2)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CFI Payroll Console</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .paycheck {
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 4px 10px;
      cursor: pointer;
      user-select: none;
      text-align: center;
    }
    .paycheck.active {
      background-color: #198754;
      color: white;
      border-color: #198754;
    }
    .tooltip-box {
      position: absolute;
      background: #fff;
      border: 1px solid #ccc;
      padding: 10px;
      max-width: 220px;
      z-index: 1000;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
      display: none;
      white-space: normal;
      word-wrap: break-word;
    }
    .tooltip-box ul {
      padding-left: 18px;
      margin: 0;
    }
    .tooltip-box ul li {
      list-style-type: disc;
      margin-bottom: 5px;
      font-size: 14px;
      color: #333;
    }
  </style>
</head>

<body class="bg-light">
  <div class="container mt-4">
    <div class="row">
      <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>
      <div class="col-md-9">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0">💸 CFI Payroll Console</h2>
          <a href="admin-panel.php" class="btn btn-sm btn-dark">← Back to Dashboard</a>
        </div>

        <table class="table table-bordered table-hover bg-white">
          <thead class="table-dark">
            <tr>
              <th>CFI</th>
              <th>Earned</th>
              <th>On Draw</th>
              <th>Draw Amount</th>
              <th>Adjustment</th>
              <th>Amount to Pay</th>
              <th>Check #</th>
              <th>Payment Type</th>
              <th>Notes</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cfi_data as $row): ?>
              <tr>
                <td>
                  <span class="cfi-name" data-breakdown='<?= json_encode([
                    "flight" => $row["dual_flight_pay"],
                    "ground" => $row["dual_ground_pay"],
                    "sof" => $row["sof_pay"]
                  ]) ?>'>
                    <?= htmlspecialchars($row['name']) ?>
                  </span>
                </td>
                <td>$<?= number_format($row['earned'], 2) ?></td>
                <td><?= $row['draw'] ?></td>
                <td><?= $row['draw'] === 'Yes' ? '$' . number_format($row['draw_amount'], 2) : '-' ?></td>
                <td><?= $row['adjustment'] !== 0.0 ? ($row['adjustment'] > 0 ? '+' : '-') . '$' . number_format(abs($row['adjustment']), 2) : '-' ?></td>
                <td><input type="number" step="0.01" class="form-control" value="<?= $row['final_due'] ?>" id="amount_<?= $row['id'] ?>"></td>
                <td><input type="text" class="form-control" placeholder="Check #" id="check_<?= $row['id'] ?>"></td>
                <td>
                  <select class="form-select" id="type_<?= $row['id'] ?>">
                    <option value="full">Full</option>
                    <option value="partial">Partial</option>
                  </select>
                </td>
                <td><input type="text" class="form-control" placeholder="Optional notes" id="notes_<?= $row['id'] ?>"></td>
                <td><button class="paycheck" data-id="<?= $row['id'] ?>">Pay</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-end">Total Payroll</th>
              <th colspan="6"><span id="totalPayrollAmount">0.00</span></th>
            </tr>
          </tfoot>
        </table>

        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-secondary me-2" onclick="payAllCFIs()">🧾 Pay All</button>
          <button class="btn btn-primary" onclick="paySelectedCFIs()">💸 Pay Selected CFIs</button>
        </div>

      </div> <!-- end col-md-9 -->
    </div> <!-- end row -->
  </div> <!-- end container -->

<script>
// Tooltip + Payroll Calculation
document.addEventListener("DOMContentLoaded", function () {
  const tooltip = document.createElement("div");
  tooltip.className = "tooltip-box";
  document.body.appendChild(tooltip);

  document.querySelectorAll(".cfi-name").forEach(el => {
    const rawData = el.dataset.breakdown || '{}';
    let breakdown = {};
    try {
      breakdown = JSON.parse(rawData);
    } catch (e) { breakdown = { flight: 0, ground: 0, sof: 0 }; }

    el.addEventListener("mouseenter", function () {
      tooltip.innerHTML = "<ul>" +
        "<li>Flight: $" + (breakdown.flight || 0).toFixed(2) + "</li>" +
        "<li>Ground: $" + (breakdown.ground || 0).toFixed(2) + "</li>" +
        "<li>SOF: $" + (breakdown.sof || 0).toFixed(2) + "</li>" +
      "</ul>";
      const rect = el.getBoundingClientRect();
      tooltip.style.top = (window.scrollY + rect.top) + "px";
      tooltip.style.left = (rect.right + 10) + "px";
      tooltip.style.display = "block";
    });

    el.addEventListener("mouseleave", function () {
      tooltip.style.display = "none";
    });
  });

  bindPaycheckToggles();
});

function bindPaycheckToggles() {
  document.querySelectorAll(".paycheck").forEach(button => {
    if (!button.dataset.bound) {
      button.addEventListener("click", function () {
        button.classList.toggle("active");
        recalculateTotalPayroll();
      });
      button.dataset.bound = "true";
    }
  });
}

function recalculateTotalPayroll() {
  let total = 0;
  document.querySelectorAll(".paycheck.active").forEach(button => {
    const cfiId = button.dataset.id;
    const amountInput = document.getElementById("amount_" + cfiId);
    if (!amountInput) return;
    const amount = parseFloat(amountInput.value || "0");
    if (!isNaN(amount)) total += amount;
  });
  const totalDisplay = document.getElementById("totalPayrollAmount");
  if (totalDisplay) totalDisplay.textContent = total.toFixed(2);
}

function payAllCFIs() {
  document.querySelectorAll(".paycheck").forEach(button => {
    button.classList.add("active");
  });
  recalculateTotalPayroll();
}

function paySelectedCFIs() {
  const selected = [];

  document.querySelectorAll('.paycheck.active').forEach(button => {
    const cfiId = button.dataset.id;
    const amountInput = document.getElementById('amount_' + cfiId);
    const notesInput = document.getElementById('notes_' + cfiId);
    const drawValue = parseFloat(amountInput?.dataset.newDraw || "0");
    const amount = parseFloat(amountInput?.value || "0");
    const notes = notesInput?.value || "";

    if (!cfiId || isNaN(amount)) return;

    selected.push({
      cfi_id: parseInt(cfiId),
      amount: amount,
      new_draw_balance: drawValue,
      notes: notes
    });
  });

  if (selected.length === 0) {
    Swal.fire("No CFIs selected", "Please select at least one CFI to pay.", "warning");
    return;
  }

  Swal.fire({
    title: 'Confirm Payment',
    html: `You are about to pay <strong>${selected.length}</strong> CFI(s).`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Pay Now',
    cancelButtonText: 'Cancel'
  }).then(result => {
    if (result.isConfirmed) {
      fetch('process_payroll.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(selected)
      })
      .then(res => res.json())
      .then(data => {
        if (Array.isArray(data) || data.success) {
          Swal.fire("Success", "CFIs marked as paid.", "success").then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire("Error", data.message || "Something went wrong.", "error");
        }
      })
      .catch(err => {
        console.error(err);
        Swal.fire("Error", "Server error occurred.", "error");
      });
    }
  });
}
</script>

</body>
</html>
