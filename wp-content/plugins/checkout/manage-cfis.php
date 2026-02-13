<?php
require_once __DIR__ . '/db_connect.php';
$conn = getDB();
$wingStmt = $conn->query("
  SELECT w.id AS wing_id, w.wing_name, COUNT(c.cfi_id) AS current_count, w.max_cfis
  FROM wp_cfi_wings w
  LEFT JOIN wp_cfis c ON w.id = c.wing_id
  WHERE w.status = 'Active'
  GROUP BY w.id, w.wing_name, w.max_cfis
  HAVING current_count < w.max_cfis
  ORDER BY w.wing_name ASC
");
$availableWings = $wingStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_cfi_id'])) {
    $cfiId = $_POST['deactivate_cfi_id'];
    try {
        $stmt = $conn->prepare("UPDATE wp_cfis SET status = 'Inactive' WHERE cfi_id = ?");
        $stmt->execute([$cfiId]);
        echo "<div style='color: green; font-weight: bold;'>✅ CFI deactivated successfully.</div>";
    } catch (PDOException $e) {
        echo "<div style='color: red; font-weight: bold;'>❌ Error deactivating CFI: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CFI Management Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">
      <div class="d-flex justify-content-between mb-3">
        <h2>🧑‍🏫 Manage CFIs</h2>
        <button class="btn btn-success" onclick="openCFIModal('add')">➕ Add New CFI</button>
      </div>


    <table class="table table-striped bg-white shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Rating</th>
                <th>Home Airport</th>
                <th>Status</th>
                <th>Google</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="cfi-table-body"></tbody>
    </table>
</div>

<!-- CFI Modal -->
<div class="modal fade" id="cfiModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 id="cfiModalLabel">CFI Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cfiForm">
                    <input type="hidden" id="cfi_id">
                    <div class="row g-3">
                    <div class="col-md-6">
  <label for="wing_id">Wing Assignment</label>
  <select id="wing_id" class="form-control">
    <option value="">— Assign to Wing —</option>
    <?php foreach ($availableWings as $wing): ?>
      <option value="<?= $wing['wing_id'] ?>">
        <?= htmlspecialchars($wing['wing_name']) ?> (<?= $wing['current_count'] ?>/<?= $wing['max_cfis'] ?>)
      </option>
    <?php endforeach; ?>
  </select>
</div>

                        <!-- Existing Fields -->
                        <div class="col-md-6"><input type="text" id="first_name" class="form-control" placeholder="First Name"></div>
                        <div class="col-md-6"><input type="text" id="last_name" class="form-control" placeholder="Last Name"></div>
                        <div class="col-md-6"><input type="email" id="email" class="form-control" placeholder="Email"></div>
                        <div class="col-md-6"><input type="text" id="phone" class="form-control" placeholder="(###) ###-####" maxlength="14" oninput="formatPhoneNumber(this)"></div>
                        <div class="col-md-6"><input type="text" id="home_airport" class="form-control" placeholder="Home Airport"></div>
                        <div class="col-md-6">
                            <select id="rating" class="form-control">
                                <option value="">Select Rating</option>
                                <option value="CFI">CFI</option>
                                <option value="CFII">CFII</option>
                                <option value="CFI/MEI">CFI/MEI</option>
                                <option value="CFII/MEI">CFII/MEI</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <select id="is_two_year_cfi" class="form-control">
                                <option value="No">Less than 2 Years as CFI</option>
                                <option value="Yes">2+ Years as CFI</option>
                            </select>
                        </div>
                        <div class="col-md-6"><input type="text" id="cfi_cert_id" class="form-control" placeholder="CFI Certificate ID"></div>
                        <div class="col-md-6"><input type="date" id="cfi_cert_expiration" class="form-control"></div>
                        <div class="col-md-6"><input type="date" id="med_expiration" class="form-control"></div>
                        <div class="col-md-6"><input type="text" id="max_student_level" class="form-control" placeholder="Max Student Level"></div>
                        <div class="col-md-6"><input type="text" id="cfi_certifications" class="form-control" placeholder="CFI Certifications"></div>
                        <div class="col-md-6">
                            <select id="can_train_cfi" class="form-control">
                                <option value="No">Cannot Train CFIs</option>
                                <option value="Yes">Can Train CFIs</option>
                            </select>
                        </div>
                        <div class="col-md-6"><input type="text" id="google_calendar_id" class="form-control" placeholder="Google Calendar ID"></div>
                        <div class="col-md-6">
                            <select id="status" class="form-control">
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </div>

                        <!-- 💰 NEW PAYROLL FIELDS -->
                        <div class="col-md-6"><input type="number" step="0.01" id="base_pay_rate" class="form-control" placeholder="Base Pay Rate (e.g. 45.00)"></div>
                        <div class="col-md-6"><input type="number" step="0.01" id="pay_cap_override" class="form-control" placeholder="Pay Cap Override (optional)"></div>
                        <div class="col-md-6">
                            <select id="on_draw" class="form-control">
                                <option value="No">Not On Draw</option>
                                <option value="Yes">On Draw</option>
                            </select>
                        </div>
                        <div class="col-md-6"><input type="number" step="0.01" id="draw_amount" class="form-control" placeholder="Draw Amount (e.g. 1200.00)"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="submitCFI()">Save</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<form id="deactivateCfiForm" method="post" style="display: none;">
  <input type="hidden" name="deactivate_cfi_id" id="deactivateCfiIdField" value="">
</form>
<script>
function fetchCFIs(){
    fetch('cfi-database.php?t=' + new Date().getTime()).then(res=>res.json()).then(data=>{
        let html='';
        data.forEach(cfi=>{
            const googleStatus = (cfi.google_refresh_token && parseInt(cfi.google_token_expires) > Math.floor(Date.now() / 1000)) ? '✅' : '❌';
            const reconnectUrl = `/wp-content/plugins/calendar/appointment-booking/cfi-connect.html?cfi_id=${cfi.cfi_id}`;
            html+=`<tr>
                <td>${cfi.first_name} ${cfi.last_name}</td>
                <td>${cfi.email}</td>
                <td>${cfi.rating}</td>
                <td>${cfi.home_airport}</td>
                <td>${cfi.status}</td>
                <td>${googleStatus}</td>
                <td>
                  <button class="btn btn-sm btn-primary" onclick='openCFIModal("edit",${JSON.stringify(cfi)})'>Edit</button>
                  <button class="btn btn-sm btn-warning" onclick="window.open('${reconnectUrl}','_blank')">Reconnect</button>
                  <button class="btn btn-sm btn-info" onclick="sendReconnectEmail(${cfi.cfi_id}, '${cfi.email}', '${cfi.first_name}')">Send Email</button>
                </td>
            </tr>`;
        });
        document.getElementById('cfi-table-body').innerHTML=html;
    });
}

function openCFIModal(action, cfi = {}) {
  document.getElementById('cfiForm').reset();
  document.getElementById('cfi_id').value = cfi.cfi_id || '';

  Object.keys(cfi).forEach(key => {
    if (document.getElementById(key)) {
      document.getElementById(key).value = cfi[key];
    }
  });

  document.getElementById('cfiModalLabel').textContent = action === 'add' ? 'Add New CFI' : 'Edit CFI';
  new bootstrap.Modal('#cfiModal').show();
}

function submitCFI() {
  const payload = {};

  document.querySelectorAll('#cfiForm input, #cfiForm select').forEach(el => {
    payload[el.id] = el.value;
  });

  payload.wing_id = document.getElementById('wing_id').value; // ✅ Now properly included
  payload.action = payload.cfi_id ? 'edit' : 'add';
  payload.id = payload.cfi_id;

  fetch('cfi-database.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(() => {
    Swal.fire('Success!', 'CFI details saved.', 'success');
    fetchCFIs();
    bootstrap.Modal.getInstance(document.getElementById('cfiModal')).hide();
  });
}

function formatPhoneNumber(input){
    let numbers=input.value.replace(/\D/g,'');
    let char={0:'(',3:') ',6:'-'};
    input.value='';
    for(let i=0;i<numbers.length;i++) input.value+=(char[i]||'')+numbers[i];
}

function deactivateCfi(cfiId) {
  if (confirm('Are you sure you want to deactivate this CFI?')) {
    document.getElementById('deactivateCfiIdField').value = cfiId;
    document.getElementById('deactivateCfiForm').submit();
  }
}

function sendReconnectEmail(cfiId, email, name) {
  fetch('send_cfi_email.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cfi_id: cfiId, email: email, name: name })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Swal.fire('Email Sent!', data.message, 'success');
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  });
}

fetchCFIs();
</script>
<script>
function showPayCFIModal() {
  Swal.fire({
    title: 'Pay a CFI',
    html: `
      <label for="cfiSelect">Select CFI:</label><br/>
      <select id="cfiSelect" class="swal2-select" style="width:100%; margin-top:8px;"></select><br/>
      <label for="payAmount">Amount to Pay ($):</label>
      <input type="number" id="payAmount" class="swal2-input" step="0.01">
      <label for="payNotes">Notes:</label>
      <textarea id="payNotes" class="swal2-textarea" placeholder="e.g., SOF + Dual Jan 1–15"></textarea>
    `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Submit Payment',
    didOpen: () => {
      fetch('get_cfi_list.php')
        .then(res => res.json())
        .then(cfis => {
          const select = document.getElementById('cfiSelect');
          cfis.forEach(cfi => {
            const opt = document.createElement('option');
            opt.value = cfi.cfi_id;
            opt.textContent = cfi.name;
            select.appendChild(opt);
          });
        });
    },
    preConfirm: () => {
      const cfi_id = document.getElementById('cfiSelect').value;
      const amount = document.getElementById('payAmount').value;
      const notes = document.getElementById('payNotes').value;

      if (!cfi_id || !amount) {
        Swal.showValidationMessage('CFI and Amount are required.');
        return false;
      }

      return fetch('pay_cfi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cfi_id, amount, notes })
      })
        .then(res => res.json())
        .then(data => {
          if (data.status !== 'success') throw new Error(data.message);
          return data;
        })
        .catch(err => {
          Swal.showValidationMessage(err.message);
        });
    }
  }).then(result => {
    if (result.isConfirmed) {
      Swal.fire('✅ Payment Recorded!', '', 'success');
    }
  });
}
</script>
    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

</body>
</html>
