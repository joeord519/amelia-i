<?php
require_once 'db_connect.php';

try {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM wp_students ORDER BY last_name ASC");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Students | Piston Aviation</title>

  <!-- ✅ Bootstrap & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- ✅ jQuery + SweetAlert2 -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light">
<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">🎓 Manage Students</h2>
        <a href="add-student.php" class="btn btn-success">➕ Add New Student</a>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <input type="text" id="studentSearch" class="form-control" placeholder="🔎 Search Students by Name, Email, or Phone">
        </div>
        <div class="col-md-6 d-flex gap-2">
          <select id="sortSelect" class="form-select me-2">
            <option value="">🔽 Sort By</option>
            <option value="aircraftAsc">Aircraft Hours (Low → High)</option>
            <option value="aircraftDesc">Aircraft Hours (High → Low)</option>
            <option value="instructorAsc">Instructor Hours (Low → High)</option>
            <option value="instructorDesc">Instructor Hours (High → Low)</option>
          </select>
          <div class="form-check ms-2">
            <input class="form-check-input" type="checkbox" id="hideInactive">
            <label class="form-check-label" for="hideInactive">Hide Inactive Students</label>
          </div>
        </div>
      </div>

      <div class="card shadow-sm p-3 bg-white rounded">
        <table id="studentTable" class="table table-striped table-bordered table-hover mb-0">
          <thead class="table-dark text-center">
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Home Airport</th>
              <th>Aircraft Hours</th>
              <th>Instructor Hours</th>
              <th>Status</th>
              <th>Student Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): ?>
              <tr class="text-center student-row" data-status="<?= $student['status']; ?>">
                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                <td><?= htmlspecialchars($student['email']); ?></td>
                <td><?= htmlspecialchars($student['phone']); ?></td>
                <td><?= htmlspecialchars($student['home_airport']); ?></td>
                <td class="aircraft"><?= htmlspecialchars($student['aircraft_hours_remaining']); ?></td>
                <td class="instructor"><?= htmlspecialchars($student['instructor_hours_remaining']); ?></td>
                <td><?= htmlspecialchars($student['status']); ?></td>
                <td><?= htmlspecialchars($student['student_status']); ?></td>
                <td class="d-flex flex-column gap-1">
                  <a href="edit-student.php?id=<?= $student['student_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                  <button 
                    class="btn btn-warning btn-sm sendPaymentLinkBtn" 
                    data-id="<?= $student['student_id']; ?>" 
                    data-name="<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>" 
                    data-email="<?= htmlspecialchars($student['email']); ?>">
                    💳 Send Payment Link
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="row mt-3">
          <div class="col-md-6 text-start">
            <button id="prevPage" class="btn btn-secondary">← Previous</button>
          </div>
          <div class="col-md-6 text-end">
            <button id="nextPage" class="btn btn-secondary">Next →</button>
          </div>
        </div>
      </div>

    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

<!-- JavaScript Filtering / Sorting -->
<script>
let currentPage = 1;
const rowsPerPage = 25;

function updateTable() {
  const searchValue = document.getElementById('studentSearch').value.toLowerCase();
  const hideInactive = document.getElementById('hideInactive').checked;
  const sortValue = document.getElementById('sortSelect').value;
  const allRows = Array.from(document.querySelectorAll('#studentTable tbody tr'));

  let filteredRows = allRows.filter(row => {
    const text = row.innerText.toLowerCase();
    const isInactive = row.dataset.status === 'inactive';
    return text.includes(searchValue) && (!hideInactive || !isInactive);
  });

  const getCleanNumber = (row, selector) => {
    const td = row.querySelector(selector);
    if (!td) return Number.NEGATIVE_INFINITY;
    const raw = td.innerHTML.trim().replace(/[^\d.\-]/g, '');
    const val = parseFloat(raw);
    return isNaN(val) ? Number.NEGATIVE_INFINITY : val;
  };

  if (sortValue) {
    filteredRows.sort((a, b) => {
      switch (sortValue) {
        case 'aircraftAsc': return getCleanNumber(a, '.aircraft') - getCleanNumber(b, '.aircraft');
        case 'aircraftDesc': return getCleanNumber(b, '.aircraft') - getCleanNumber(a, '.aircraft');
        case 'instructorAsc': return getCleanNumber(a, '.instructor') - getCleanNumber(b, '.instructor');
        case 'instructorDesc': return getCleanNumber(b, '.instructor') - getCleanNumber(a, '.instructor');
        default: return 0;
      }
    });
  }

  allRows.forEach(row => row.style.display = 'none');

  const start = (currentPage - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  const paginatedRows = filteredRows.slice(start, end);
  const tbody = document.querySelector('#studentTable tbody');

  paginatedRows.forEach(row => {
    row.style.display = '';
    tbody.appendChild(row);
  });

  document.getElementById('prevPage').disabled = currentPage === 1;
  document.getElementById('nextPage').disabled = end >= filteredRows.length;
}

document.getElementById('studentSearch').addEventListener('input', () => { currentPage = 1; updateTable(); });
document.getElementById('hideInactive').addEventListener('change', () => { currentPage = 1; updateTable(); });
document.getElementById('sortSelect').addEventListener('change', () => { currentPage = 1; updateTable(); });
document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; updateTable(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; updateTable(); });

updateTable();
</script>

<script>
$(document).on('click', '.sendPaymentLinkBtn', function () {
  const studentId = $(this).data('id');
  const studentName = $(this).data('name');
  const studentEmail = $(this).data('email');
  openPaymentModal(studentId, studentName, studentEmail);
});

function openPaymentModal(studentId, studentName = '', studentEmail = '') {
  Swal.fire({
    title: 'Send Payment Link',
    html: `
      <p>Student: <strong>${studentName}</strong></p>
      <input type="hidden" id="student_id" value="${studentId}">
      <input type="hidden" id="student_email" value="${studentEmail}">
      <input type="number" id="aircraft_hours" placeholder="Aircraft Hours" class="swal2-input">
      <input type="number" id="instructor_hours" placeholder="Instructor Hours" class="swal2-input">
      <input type="text" id="coupon_code" placeholder="Coupon Code (optional)" class="swal2-input">
    `,
    confirmButtonText: 'Generate Link',
    focusConfirm: false,
    preConfirm: () => {
      return {
        student_id: document.getElementById('student_id').value,
        email: document.getElementById('student_email').value,
        aircraft_hours: document.getElementById('aircraft_hours').value,
        instructor_hours: document.getElementById('instructor_hours').value,
        coupon_code: document.getElementById('coupon_code').value
      };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('/wp-content/plugins/pistonpay/create_payment.php', result.value, function (res) {
        if (res.success) {
          Swal.fire({
            title: '✅ Payment Link Ready',
            html: `
              <a href="${res.url}" class="btn btn-primary w-100 mb-2" target="_blank">
                💳 Collect Payment Now
              </a>
              <button id="emailStudentLink" class="btn btn-outline-secondary w-100">
                📧 Email This Link to Student
              </button>
            `,
            icon: 'success',
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Close',
            didOpen: () => {
              $('#emailStudentLink').click(() => {
                $.post('/wp-content/plugins/checkout/email_payment_link.php', {
                  student_id: result.value.student_id,
                  payment_url: res.url
                }, function (emailRes) {
                  Swal.fire(emailRes.success ? '📤 Email Sent' : '❌ Email Failed', emailRes.message, emailRes.success ? 'success' : 'error');
                });
              });
            }
          });
        } else {
          Swal.fire('❌ Error', res.message, 'error');
        }
      });
    }
  });
}
</script>

</body>
</html>


