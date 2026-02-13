<?php
require_once(__DIR__ . '/lib/lead-functions.php');

// Optional filter by training program
$programFilter = $_GET['program'] ?? null;
$leadCreated = false;
$programCreated = false;

// Handle new lead submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_lead'])) {
  $db = getDB();
  $stmt = $db->prepare("
    INSERT INTO wp_leads (first_name, last_name, phone, email, training_program, created_at)
    VALUES (:first_name, :last_name, :phone, :email, :program, NOW())
  ");
  $stmt->execute([
    'first_name' => $_POST['first_name'],
    'last_name'  => $_POST['last_name'],
    'phone'      => $_POST['phone'],
    'email'      => $_POST['email'],
    'program'    => $_POST['training_program']
  ]);
  $leadCreated = true;
}

// Handle new program submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_program'])) {
  $db = getDB();
  $stmt = $db->prepare("INSERT IGNORE INTO wp_program_types (name) VALUES (:name)");
  $stmt->execute(['name' => trim($_POST['program_name'])]);
  $programCreated = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lead_id'])) {
  $db = getDB();
  $stmt = $db->prepare("DELETE FROM wp_leads WHERE id = :id");
  $stmt->execute(['id' => $_POST['delete_lead_id']]);
  header("Location: dashboard.php");
  exit;
}

if (isset($_GET['delete_program'])) {
  $db = getDB();
  $stmt = $db->prepare("DELETE FROM wp_program_types WHERE id = :id");
  $stmt->execute(['id' => $_GET['delete_program']]);
}

$programTypes = getAllProgramTypes();
$leads = getAllLeads($programFilter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Piston CRM Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">

<div class="container mt-4">
  <div class="row">
    
    <!-- Sidebar -->
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <!-- Main Panel -->
    <div class="col-md-9">

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">🚀 Piston CRM – Lead Dashboard</h2>
        <div class="d-flex gap-2">
          <a href="flow-builder.php" class="btn btn-outline-primary btn-sm">⚙️ Manage Flows</a>
          <a href="/wp-content/plugins/checkout/admin-panel.php" class="btn btn-outline-dark btn-sm">← Back to Admin Panel</a>
        </div>
      </div>

      <?php if (!empty($leadCreated)): ?>
        <div class="alert alert-success">✅ Lead added successfully!</div>
      <?php endif; ?>

      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">➕ Add New Lead</h5>
          <form method="POST">
            <input type="hidden" name="new_lead" value="1">
            <div class="row g-2">
              <div class="col-md-3">
                <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
              </div>
              <div class="col-md-3">
                <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
              </div>
              <div class="col-md-3">
                <input type="text" name="phone" class="form-control" id="phone" placeholder="(###) ###-####" required>
              </div>
              <div class="col-md-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
              </div>
            </div>
            <div class="row mt-3 align-items-center">
              <div class="col-md-6">
                <select name="training_program" class="form-select" required>
                  <option value="">— Select Program —</option>
                  <?php foreach ($programTypes as $program): ?>
                    <option value="<?= htmlspecialchars($program['name']) ?>"><?= htmlspecialchars($program['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 text-end">
                <button type="submit" class="btn btn-success">➕ Add Lead</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">🎓 Program Manager</h5>

          <?php if (!empty($programCreated)): ?>
            <div class="alert alert-success">✅ Program added successfully!</div>
          <?php endif; ?>

          <form method="POST" class="row g-2 mb-3 align-items-center">
            <div class="col-md-6">
              <input type="text" name="program_name" class="form-control" placeholder="New Program Name" required>
              <input type="hidden" name="new_program" value="1">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-outline-success">➕ Add Program</button>
            </div>
          </form>

          <table class="table table-bordered table-sm">
            <thead class="table-light">
              <tr>
                <th>Program</th>
                <th style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($programTypes as $program): ?>
                <tr>
                  <td><?= htmlspecialchars($program['name']) ?></td>
                  <td class="d-flex gap-1">
                    <a href="actions/group-send.php?program=<?= urlencode($program['name']) ?>" class="btn btn-sm btn-outline-primary" title="Send to Program">✉️</a>
                    <a href="?delete_program=<?= $program['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this program?')">🗑️</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <form method="get" class="mb-4">
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label for="program" class="form-label">Filter by Program</label>
            <select name="program" id="program" class="form-select" onchange="this.form.submit()">
              <option value="">All Programs</option>
              <?php foreach ($programTypes as $program): ?>
                <option value="<?= htmlspecialchars($program['name']) ?>" <?= $program['name'] == $programFilter ? 'selected' : '' ?>>
                  <?= htmlspecialchars($program['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>

      <div class="card shadow-sm">
        <div class="card-body">
          <table id="leadsTable" class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Program</th>
                <th>Last Contact</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leads as $lead): ?>
                <tr>
                  <td><?= htmlspecialchars(trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''))) ?></td>
                  <td><?= htmlspecialchars($lead['phone'] ?? '') ?></td>
                  <td><?= htmlspecialchars($lead['email'] ?? '') ?></td>
                  <td><?= htmlspecialchars($lead['training_program'] ?? '') ?></td>
                  <td><?= $lead['last_contacted_at'] ? date('M j, Y H:i', strtotime($lead['last_contacted_at'])) : '—' ?></td>
                  <td class="d-flex gap-2">
                    <a href="lead-detail.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-primary">View</a>
                    <form method="POST" action="dashboard.php" onsubmit="return confirm('Delete this lead permanently? This action cannot be undone.')">
                      <input type="hidden" name="delete_lead_id" value="<?= $lead['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  jQuery(document).ready(function ($) {
    $('#leadsTable').DataTable({
      order: [[4, 'desc']],
      pageLength: 25
    });
  });
</script>

<script src="https://unpkg.com/imask"></script>
<script>
  IMask(document.getElementById('phone'), {
    mask: '(000) 000-0000'
  });
</script>

</body>
</html>

