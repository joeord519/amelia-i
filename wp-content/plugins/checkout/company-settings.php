<?php
require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        $stmt = $conn->prepare("REPLACE INTO wp_company_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())");
        $stmt->execute([$key, $value]);
    }
    header("Location: company-settings.php?success=1");
    exit;
}

// Fetch current settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM wp_company_settings");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Company Settings</title>

  <!-- Bootstrap & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    label {
      margin-top: 1rem;
      font-weight: 500;
    }

    button[type="submit"] {
      margin-top: 2rem;
    }
  </style>
</head>
<body class="bg-light">

<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">⚙️ Company Settings</h2>
        <a class="btn btn-sm btn-dark" href="admin-panel.php">← Back to Dashboard</a>
      </div>

      <form method="POST" class="card p-4 shadow-sm bg-white">
        <div class="row">
          <div class="col-md-6">
            <label for="aircraft_hourly_rate">Aircraft Hourly Rate ($/hr)</label>
            <input type="number" step="0.01" name="aircraft_hourly_rate" class="form-control" id="aircraft_hourly_rate" value="<?= htmlspecialchars($settings['aircraft_hourly_rate'] ?? '') ?>" />
          </div>

          <div class="col-md-6">
            <label for="instructor_hourly_rate">Instructor Hourly Rate ($/hr)</label>
            <input type="number" step="0.01" name="instructor_hourly_rate" class="form-control" id="instructor_hourly_rate" value="<?= htmlspecialchars($settings['instructor_hourly_rate'] ?? '') ?>" />
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <label for="discovery_flight_price">Discovery Flight Price ($)</label>
            <input type="number" step="0.01" name="discovery_flight_price" class="form-control" id="discovery_flight_price" value="<?= htmlspecialchars($settings['discovery_flight_price'] ?? '') ?>" />
          </div>
        </div>

        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
      </form>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success mt-3">✅ Settings saved successfully!</div>
      <?php endif; ?>
    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

</body>
</html>



