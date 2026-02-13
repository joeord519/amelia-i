<?php
// /wp-content/plugins/checkout/unanswered-review.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

$feedback = '';
$unansweredTable = 'wp_amelia_unanswered';
$trainingTable = 'wp_amelia_knowledge_base';

// ✅ Handle training submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['train_answer'], $_POST['train_question'], $_POST['train_category'])) {
  $question = trim($_POST['train_question']);
  $answer = trim($_POST['train_answer']);
  $category = $_POST['train_category'];
  $source_id = intval($_POST['source_id']);
  $flagged_review = isset($_POST['flagged_review']) ? 1 : 0;

  $stmt = $conn->prepare("INSERT INTO $trainingTable (question, answer, category, conversational, active, flagged_review)
                          VALUES (?, ?, ?, 1, 1, ?)");
  $stmt->execute([$question, $answer, $category, $flagged_review]);

  $conn->prepare("DELETE FROM $unansweredTable WHERE id = ?")->execute([$source_id]);
  $feedback = '<div class="alert alert-success text-center">✅ Answer saved and added to training.</div>';
}

// ❌ Delete from unanswered
if (isset($_POST['delete_id'])) {
  $stmt = $conn->prepare("DELETE FROM $unansweredTable WHERE id = ?");
  $stmt->execute([$_POST['delete_id']]);
  $feedback = '<div class="alert alert-warning text-center">🗑️ Entry removed from unanswered list.</div>';
}

// 🔄 Fetch entries
$stmt = $conn->query("SELECT * FROM $unansweredTable ORDER BY date_logged DESC");
$entries = $stmt->fetchAll();
?>

<!-- ✅ Load CSS & JS outside PHP -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<div class="container py-4">
  <h2 class="mb-4">❓ Unanswered Questions</h2>
  <div class="mb-4 text-end">
  <a href="admin-training.php" class="btn btn-outline-dark">
    ← Back to Training Panel
  </a>
</div>
  <?php echo $feedback; ?>

  <?php if (count($entries)): ?>
    <table id="unansweredTable" class="table table-bordered table-hover">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Question</th>
          <th>Category</th>
          <th>Date</th>
          <th>Answer & Save</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $row): ?>
          <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['question']); ?></td>
            <td><?php echo htmlspecialchars($row['category']); ?></td>
            <td><?php echo $row['date_logged']; ?></td>
            <td>
              <form method="POST" class="d-flex flex-column gap-2">
                <input type="hidden" name="train_question" value="<?php echo htmlspecialchars($row['question'], ENT_QUOTES); ?>">
                <input type="hidden" name="train_category" value="<?php echo htmlspecialchars($row['category']); ?>">
                <input type="hidden" name="source_id" value="<?php echo $row['id']; ?>">
                <textarea name="train_answer" rows="2" class="form-control" placeholder="Type Amelia's answer here..." required></textarea>
                <input type="checkbox" name="flagged_review" value="1">
                <button type="submit" class="btn btn-success btn-sm">💾 Save to Training</button>
              </form>
            </td>
            <td class="text-center">
              <form method="POST">
                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑️ Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-info text-center">🎉 No unanswered questions at the moment.</div>
  <?php endif; ?>
</div>

<script>
  jQuery(document).ready(function ($) {
    $('#unansweredTable').DataTable({
      order: [[0, 'desc']]
    });
  });
</script>


