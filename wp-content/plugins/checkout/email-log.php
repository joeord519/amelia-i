<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/db_connect.php';
$conn = getDB();

$stmt = $conn->query("SELECT * FROM wp_email_log ORDER BY sent_at DESC LIMIT 500");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Log</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>📬 Email Log</h2>
    <a href="admin-panel.php" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
  <table class="table table-bordered table-hover table-sm sortable">
    <thead class="table-light">
      <tr>
        <th>Date</th>
        <th>Subject</th>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Error</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= htmlspecialchars($log['sent_at']) ?></td>
          <td><?= htmlspecialchars($log['subject']) ?></td>
          <td><?= htmlspecialchars($log['student_name']) ?></td>
          <td><?= htmlspecialchars($log['student_email']) ?></td>
          <td>
            <?= $log['status'] === 'sent'
              ? '<span class="badge bg-success">Sent</span>'
              : '<span class="badge bg-danger">Failed</span>' ?>
          </td>
          <td style="font-size: 0.85em"><?= htmlspecialchars($log['error_message']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>

