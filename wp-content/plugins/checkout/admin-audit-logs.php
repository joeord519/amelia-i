<?php
require_once 'db_connect.php';

try {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 200");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | Piston Aviation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">📋 Audit Logs</span>
        <a href="admin-panel.php" class="btn btn-outline-light">← Back to Dashboard</a>
    </div>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">🕵️ Admin Audit Trail</h2>
    
    <?php if (count($logs) === 0): ?>
        <div class="alert alert-warning text-center">No audit logs found.</div>
    <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Timestamp</th>
                    <th>Event Type</th>
                    <th>Description</th>
                    <th>Actor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                        <td><?php echo htmlspecialchars($log['actor']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
