<?php
require_once 'db_connect.php';

$conn = getDB();
$results = [];
$successCount = 0;
$failures = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $uploadDir = __DIR__ . '/imports/';
    $uploadPath = $uploadDir . basename($file['name']);

    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Only allow CSV files
    $fileType = strtolower(pathinfo($uploadPath, PATHINFO_EXTENSION));
    if ($fileType !== 'csv') {
        $failures[] = "❌ Only CSV files are allowed.";
    } elseif (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Process CSV
        $handle = fopen($uploadPath, 'r');
        $header = fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $studentId = trim($row[0]);
            $assignedCfiId = trim($row[3]);

            if (!$studentId || !$assignedCfiId) {
                $failures[] = "Missing student or CFI ID: " . implode(', ', $row);
                continue;
            }

            $stmt = $conn->prepare("SELECT wing_id FROM wp_cfis WHERE cfi_id = ?");
            $stmt->execute([$assignedCfiId]);
            $wingId = $stmt->fetchColumn();

            if (!$wingId) {
                $failures[] = "❌ No wing_id found for CFI $assignedCfiId (Student $studentId)";
                continue;
            }

            $stmt = $conn->prepare("UPDATE wp_students SET assigned_cfi_id = ?, wing_id = ? WHERE student_id = ?");
            $stmt->execute([$assignedCfiId, $wingId, $studentId]);

            if ($stmt->rowCount()) {
                $successCount++;
            } else {
                $failures[] = "❌ Failed to update Student $studentId";
            }
        }

        fclose($handle);
    } else {
        $failures[] = "❌ Upload failed. Check permissions.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload CFI Assignments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">📤 Upload Student CFI Assignments</h2>
    <form method="POST" enctype="multipart/form-data" class="mb-5">
        <div class="mb-3">
            <input type="file" name="csv_file" accept=".csv" class="form-control" required>
        </div>
        <button class="btn btn-primary">Upload and Import</button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-success"><strong>✅ Import Complete:</strong> <?php echo $successCount; ?> students updated.</div>

        <?php if (!empty($failures)): ?>
            <div class="alert alert-warning">
                <h5>❌ Issues Encountered:</h5>
                <ul>
                    <?php foreach ($failures as $fail): ?>
                        <li><?php echo htmlspecialchars($fail); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No errors encountered.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
