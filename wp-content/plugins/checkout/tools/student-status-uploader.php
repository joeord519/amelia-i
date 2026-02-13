<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../db_connect.php';
$conn = getDB();

$resultMessage = '';
$skipped = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_csv'])) {
    $file = $_FILES['student_csv']['tmp_name'];
    $handle = fopen($file, 'r');
    if ($handle === false) {
        $resultMessage = "❌ Failed to open uploaded file.";
    } else {
        $header = fgetcsv($handle);
        $updated = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $fullName = $data[0];
            $email = trim($data[2]);
            $status = strtolower(trim($data[7])) === 'active' ? 'active' : 'inactive';

            $nameParts = explode(' ', $fullName, 2);
            $firstName = trim($nameParts[0]);
            $lastName = trim($nameParts[1] ?? '');

            $stmt = $conn->prepare("SELECT * FROM wp_students WHERE first_name = ? AND last_name = ?");
            $stmt->execute([$firstName, $lastName]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $studentId = null;
            if (count($matches) === 1) {
                $studentId = $matches[0]['student_id'];
            } elseif (count($matches) > 1 && $email) {
                foreach ($matches as $match) {
                    if (strtolower($match['email']) === strtolower($email)) {
                        $studentId = $match['student_id'];
                        break;
                    }
                }
            }

            if (!empty($studentId)) {
                $update = $conn->prepare("UPDATE wp_students SET status = ? WHERE student_id = ?");
                $update->execute([$status, $studentId]);
                $updated++;
            } else {
                $skipped[] = $fullName;
            }
        }

        fclose($handle);
        $resultMessage = "✅ Updated $updated students.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Student Status Uploader</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
  <h2>📤 Upload CSV to Update Student Status</h2>

  <?php if (!empty($resultMessage)): ?>
    <div class="alert alert-success"><?= $resultMessage ?></div>
  <?php endif; ?>

  <?php if (!empty($skipped)): ?>
    <div class="alert alert-warning">
      ⚠️ Skipped the following students:
      <ul>
        <?php foreach ($skipped as $name): ?>
          <li><?= htmlspecialchars($name) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="student_csv" accept=".csv" class="form-control mb-3" required>
    <button type="submit" class="btn btn-success">Update Status</button>
  </form>
</body>
</html>
