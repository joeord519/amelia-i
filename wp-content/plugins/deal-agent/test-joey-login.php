<?php
// test-joey-login.php
// Simple test page to pick a student and launch Joey in a SweetAlert chat.

require_once __DIR__ . '/db_connect.php';

$error = '';
$student = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

    if ($studentId > 0) {
        try {
            $pdo = getDB();
            $sql = "SELECT * FROM wp_students WHERE student_id = :student_id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':student_id' => $studentId]);
            $student = $stmt->fetch();

            if (!$student) {
                $error = "No student found with ID {$studentId}.";
            }
        } catch (Throwable $e) {
            $error = 'DB error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please enter a valid numeric student ID.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Joey Deal Agent – Test Login</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      margin: 0;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #fff;
      padding: 24px 32px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      max-width: 480px;
      width: 100%;
    }
    h1 {
      margin-top: 0;
      color: #003883;
      font-size: 22px;
    }
    label {
      font-weight: 600;
      display: block;
      margin-bottom: 6px;
    }
    input[type="number"] {
      width: 100%;
      padding: 8px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
      margin-bottom: 12px;
    }
    button {
      padding: 8px 16px;
      font-size: 14px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .btn-primary {
      background: #003883;
      color: #fff;
    }
    .btn-joey {
      background: #00bf63;
      color: #fff;
      margin-top: 16px;
    }
    .error {
      color: #b30000;
      margin-bottom: 10px;
      font-size: 13px;
    }
    .student-info {
      margin-top: 12px;
      padding: 10px;
      background: #f1f7ff;
      border-radius: 4px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Joey Deal Agent – Test Login</h1>

    <form method="post">
      <label for="student_id">Enter Student ID</label>
      <input type="number" id="student_id" name="student_id"
             value="<?php echo isset($_POST['student_id']) ? (int)$_POST['student_id'] : ''; ?>"
             placeholder="e.g. 177">
      <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <button type="submit" class="btn-primary">Load Student</button>
    </form>

    <?php if ($student): ?>
      <div class="student-info">
        <strong>Loaded Student:</strong><br>
        ID: <?php echo (int)$student['student_id']; ?><br>
        Name: <?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?><br>
        Program: <?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?><br>
        Status: <?php echo htmlspecialchars($student['status'] ?? ''); ?>
      </div>

      <button type="button"
              class="btn-joey"
              onclick="openJoey(<?php echo (int)$student['student_id']; ?>)">
        Talk to Joey 🤠
      </button>
    <?php endif; ?>
  </div>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Joey chat UI -->
  <script src="/wp-content/plugins/deal-agent/chat/joey.js"></script>
</body>
</html>
