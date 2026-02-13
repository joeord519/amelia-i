<?php
require_once 'db_connect.php';

// Get student ID
$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    header('Location: admin-student-management.php');
    exit;
}

// Fetch student data
try {
    $conn = getDB();

    // Handle deactivation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_student'])) {
        $studentId = $_POST['deactivate_student_id'] ?? '';
        if (!empty($studentId)) {
            $stmt = $conn->prepare("UPDATE wp_students SET student_status = 'Inactive', status = 'inactive', email = NULL WHERE student_id = ?");
            $stmt->execute([$studentId]);
            echo "<div style='color: green; font-weight: bold; margin-bottom: 10px;'>✅ Student deactivated successfully.</div>";
            echo "<a href='/wp-content/plugins/checkout/admin-panel.php' style='display:inline-block;padding:8px 16px;background:#3498db;color:#fff;text-decoration:none;border-radius:5px;'>⬅️ Back to Admin Panel</a>";
            exit;
        }
    }

    $stmt = $conn->prepare("SELECT * FROM wp_students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header('Location: admin-student-management.php');
        exit;
    }

    // Home airports
    $locationStmt = $conn->prepare("SELECT airport_code, name FROM wp_locations ORDER BY name ASC");
    $locationStmt->execute();
    $locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

    // CFIs
    $cfiStmt = $conn->prepare("SELECT cfi_id, CONCAT(first_name, ' ', last_name) AS cfi_name FROM wp_cfis ORDER BY last_name ASC");
    $cfiStmt->execute();
    $cfis = $cfiStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $homeAirport = $_POST['home_airport'] ?? '';
    $aircraftHours = $_POST['aircraft_hours_remaining'] ?? 0;
    $instructorHours = $_POST['instructor_hours_remaining'] ?? 0;
    $assignedCfiId = $_POST['assigned_cfi_id'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $studentStatus = $_POST['student_status'] ?? '';

    if ($assignedCfiId) {
        $wingStmt = $conn->prepare("SELECT wing_id FROM wp_cfis WHERE cfi_id = ?");
        $wingStmt->execute([$assignedCfiId]);
        $wingId = $wingStmt->fetchColumn();
    }

    try {
        $updateStmt = $conn->prepare("UPDATE wp_students SET 
            first_name = ?, last_name = ?, email = ?, phone = ?, home_airport = ?, 
            aircraft_hours_remaining = ?, instructor_hours_remaining = ?, assigned_cfi_id = ?, 
            status = ?, student_status = ?
            WHERE student_id = ?");
        $updateStmt->execute([
            $firstName, $lastName, $email, $phone, $homeAirport,
            $aircraftHours, $instructorHours, $assignedCfiId,
            $status, $studentStatus, $studentId
        ]);
        header('Location: admin-student-management.php?toast=student_updated');
        exit;
    } catch (PDOException $e) {
        die("Update error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Student | Piston Aviation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light">
<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="admin-student-management.php" class="btn btn-outline-secondary">← Back to Student Management</a>
      </div>

      <h2 class="mb-4">✏️ Edit Student</h2>

      <form method="POST">
        <input type="hidden" id="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">

        <div class="mb-4">
          <h5>Current Status: 
            <span class="badge 
              <?= $student['status'] === 'active' ? 'bg-success' : ($student['status'] === 'inactive' ? 'bg-secondary' : 'bg-danger') ?>">
              <?= ucfirst($student['status']) ?>
            </span>
            <?php if (!empty($student['student_status'])): ?>
              <span class="badge bg-info text-dark ms-2"><?= htmlspecialchars($student['student_status']) ?></span>
            <?php endif; ?>
          </h5>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label>First Name</label>
            <input name="first_name" class="form-control" value="<?= htmlspecialchars($student['first_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label>Last Name</label>
            <input name="last_name" class="form-control" value="<?= htmlspecialchars($student['last_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label>Email</label>
            <input name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>">
          </div>
          <div class="col-md-6">
            <label>Phone</label>
            <input name="phone" class="form-control" value="<?= htmlspecialchars($student['phone']) ?>">
          </div>
          <div class="col-md-6">
            <label>Home Airport</label>
            <select name="home_airport" class="form-select">
              <?php foreach ($locations as $loc): ?>
                <option value="<?= $loc['airport_code'] ?>" <?= $student['home_airport'] == $loc['airport_code'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($loc['airport_code'] . ' - ' . $loc['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label>Assigned CFI</label>
            <select name="assigned_cfi_id" class="form-select">
              <option value="">-- None --</option>
              <?php foreach ($cfis as $cfi): ?>
                <option value="<?= $cfi['cfi_id'] ?>" <?= $student['assigned_cfi_id'] == $cfi['cfi_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cfi['cfi_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label>Aircraft Hours Remaining</label>
            <input type="number" class="form-control" id="aircraft_hours" name="aircraft_hours_remaining" readonly value="<?= htmlspecialchars($student['aircraft_hours_remaining']) ?>">
          </div>

          <div class="col-md-6">
            <label>Instructor Hours Remaining</label>
            <input type="number" class="form-control" id="instructor_hours" name="instructor_hours_remaining" readonly value="<?= htmlspecialchars($student['instructor_hours_remaining']) ?>">
          </div>

          <div class="col-12 text-end">
            <button type="button" class="btn btn-warning btn-sm" onclick="openAdjustModal()">Adjust Hours</button>
          </div>

          <div class="col-md-6">
            <label>Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $student['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              <option value="banned" <?= $student['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
            </select>
          </div>

          <div class="col-md-6">
            <label>Student Status (Label)</label>
            <input name="student_status" class="form-control" value="<?= htmlspecialchars($student['student_status']) ?>">
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-success" type="submit">💾 Save Changes</button>
          <a href="admin-student-management.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

<!-- Deactivate Student (Floating Button) -->
<form method="post" onsubmit="return confirm('Are you sure you want to deactivate this student?');" style="position: fixed; bottom: 60px; right: 30px; z-index: 9999;">
  <input type="hidden" name="deactivate_student_id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
  <button type="submit" name="deactivate_student" style="background-color:#c0392b;color:white;padding:10px 20px;border:none;border-radius:5px;box-shadow:0 4px 8px rgba(0,0,0,0.2);">
    🚫 Deactivate Student
  </button>
</form>

<!-- Adjust Hours Modal -->
<div class="modal fade" id="adjustHoursModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form id="adjustHoursForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Adjust Student Hours</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="adjust_student_id" name="student_id">
          <div class="mb-3">
            <label>Aircraft Hours</label>
            <input type="number" step="0.01" class="form-control" id="adjust_aircraft_hours" name="adjust_aircraft_hours">
          </div>
          <div class="mb-3">
            <label>Instructor Hours</label>
            <input type="number" step="0.01" class="form-control" id="adjust_instructor_hours" name="adjust_instructor_hours">
          </div>
          <div class="mb-3">
            <label>Reason</label>
            <textarea class="form-control" name="adjust_reason" required></textarea>
          </div>
          <div class="mb-3">
            <label>Upload File (optional)</label>
            <input type="file" name="adjust_file" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Submit</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openAdjustModal() {
  const studentId = document.getElementById('student_id').value;
  const aircraft = document.getElementById('aircraft_hours').value;
  const instructor = document.getElementById('instructor_hours').value;

  document.getElementById('adjust_student_id').value = studentId;
  document.getElementById('adjust_aircraft_hours').value = aircraft;
  document.getElementById('adjust_instructor_hours').value = instructor;

  const modal = new bootstrap.Modal(document.getElementById('adjustHoursModal'));
  modal.show();
}

document.getElementById('adjustHoursForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch('process_adjustment.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(response => {
    if (response.success) {
      Swal.fire('✅ Adjustment Complete', 'Student hours updated successfully.', 'success').then(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('adjustHoursModal'));
        modal.hide();
        window.location.reload(true);
      });
    } else {
      Swal.fire("Error", response.message || "Could not process adjustment.", "error");
    }
  })
  .catch(err => {
    Swal.fire("Error", "Something went wrong.", "error");
    console.error(err);
  });
});
</script>

</body>
</html>

