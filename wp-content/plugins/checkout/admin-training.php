<?php
// /wp-content/plugins/checkout/admin-training.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
$conn = getDB();
$table = 'wp_amelia_knowledge_base';
$uploadDir = '/wp-content/uploads/amelia_resources/';
$uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;

if (!file_exists($uploadPath)) {
  mkdir($uploadPath, 0755, true);
}

$feedback = '';

// ✅ Handle delete
if (isset($_POST['delete_id'])) {
  $stmt = $conn->prepare("UPDATE $table SET active = 0 WHERE id = ?");
  $stmt->execute([intval($_POST['delete_id'])]);
  $feedback = '<div class="alert alert-warning text-center">🗑️ Entry marked inactive.</div>';
}

// ✅ Handle hard delete
if (isset($_POST['hard_delete_id'])) {
  $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
  $stmt->execute([intval($_POST['hard_delete_id'])]);
  $feedback = '<div class="alert alert-danger text-center">❌ Entry permanently removed.</div>';
}

// ✅ Handle add or update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'], $_POST['answer'])) {
  $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
  $question = trim($_POST['question']);
  $answer = trim($_POST['answer']);
  $category = $_POST['category'] ?? 'general';
  $conversational = !empty($_POST['conversational']) ? 1 : 0;
  $active = !empty($_POST['active']) ? 1 : 0;
  $always_include = !empty($_POST['always_include']) ? 1 : 0;
  $flagged_review = !empty($_POST['flagged_review']) ? 1 : 0;

  $source_url = $_POST['source_url'] ?? null;
  $mandatory_link = $_POST['mandatory_link'] ?? null;

  // 🔗 Optional: Generate HTML link from fields
  $linkHTML = '';
  if (!empty($_POST['custom_link_url']) && !empty($_POST['custom_link_text'])) {
    $url = trim($_POST['custom_link_url']);
    $text = trim($_POST['custom_link_text']);
    $class = trim($_POST['custom_link_class']);

    $linkHTML = '<a href="' . htmlspecialchars($url) . '" target="_blank" class="' . htmlspecialchars($class) . '">' . htmlspecialchars($text) . '</a>';

    // Save to mandatory_link if flagged
    if ($always_include) {
      $mandatory_link = $linkHTML;
    }
  }

  // 🔄 Append to answer for human review (optional, can be removed if not needed)
  if (!empty($linkHTML)) {
    $answer .= "\n\n" . $linkHTML;
  }

  // 📎 File uploads
  $uploadDir = '/wp-content/uploads/amelia_resources/';
  $uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;
  if (!file_exists($uploadPath)) mkdir($uploadPath, 0755, true);

  $pdf_url = $_POST['existing_pdf'] ?? null;
  if (!empty($_FILES['pdf_file']['name'])) {
    $pdfName = basename($_FILES['pdf_file']['name']);
    $pdfTarget = $uploadPath . $pdfName;
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdfTarget)) {
      $pdf_url = $uploadDir . $pdfName;
    }
  }

  $image_url = $_POST['existing_image'] ?? null;
  if (!empty($_FILES['image_file']['name'])) {
    $imgName = basename($_FILES['image_file']['name']);
    $imgTarget = $uploadPath . $imgName;
    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $imgTarget)) {
      $image_url = $uploadDir . $imgName;
    }
  }

  // 💾 Save to database
  try {
    if ($id > 0) {
  // UPDATE logic stays as-is
  $stmt = $conn->prepare("UPDATE $table SET question=?, answer=?, category=?, conversational=?, active=?, source_url=?, pdf_url=?, image_url=?, mandatory_link=?, always_include=?, flagged_review=? WHERE id=?");
  $stmt->execute([$question, $answer, $category, $conversational, $active, $source_url, $pdf_url, $image_url, $mandatory_link, $always_include, $flagged_review, $id]);
  $feedback = '<div class="alert alert-info text-center">✏️ Entry updated successfully.</div>';
} else {
  // 🔍 Check for duplicate question
  $checkStmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE question = ?");
  $checkStmt->execute([$question]);
  $exists = $checkStmt->fetchColumn();

  if ($exists > 0) {
    $feedback = '<div class="alert alert-warning text-center">⚠️ This question already exists in the training database.</div>';
  } else {
    // ✅ Safe to insert
    $stmt = $conn->prepare("INSERT INTO $table (question, answer, category, conversational, active, source_url, pdf_url, image_url, mandatory_link, always_include, flagged_review) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$question, $answer, $category, $conversational, $active, $source_url, $pdf_url, $image_url, $mandatory_link, $always_include, $flagged_review]);
    // 🧹 Clean up unanswered list if match exists
    $conn->prepare("DELETE FROM wp_amelia_unanswered WHERE question = ?")->execute([$question]);

    $feedback = '<div class="alert alert-success text-center">✅ Entry added successfully.</div>';
  }
}

  } catch (PDOException $e) {
    file_put_contents(__DIR__ . '/sql_error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
    $feedback = '<div class="alert alert-danger text-center">❌ ' . $e->getMessage() . '</div>';
  }
}

// Fetch entries
$entries = [];
try {
  $stmt = $conn->query("SELECT * FROM $table ORDER BY id DESC");
  $entries = $stmt->fetchAll();
} catch (PDOException $e) {
  $feedback .= '<div class="alert alert-danger text-center">⚠️ Failed to load entries.</div>';
}
?>
<!-- ✅ Load after PHP block -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<div class="container-fluid mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>
    <div class="col-md-9">

  <?php echo $feedback; ?>

<div class="mb-4 text-end">
  <a href="admin-panel.php" class="btn btn-outline-dark me-2">
    ← Back to Admin Panel
  </a>
  <a href="test-conversation.php" class="btn btn-outline-primary me-2">
    🧪 Test Amelia Conversation
  </a>
  <a href="unanswered-review.php" class="btn btn-outline-secondary">
    ❓ View Unanswered Questions
  </a>
</div>

  <div class="card shadow-sm mb-4">
  <div class="card-body">
    <h2 class="card-title">🧠 Add or Edit Training Entry</h2>
    <form method="POST" enctype="multipart/form-data" id="entryForm">
      <input type="hidden" name="id" id="entry_id" value="">
      <input type="hidden" name="existing_pdf" id="existing_pdf" value="">
      <input type="hidden" name="existing_image" id="existing_image" value="">

      <div class="mb-3">
        <label class="form-label">Question</label>
        <input type="text" name="question" id="question" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Answer</label>
        <textarea name="answer" id="answer" rows="4" class="form-control" required></textarea>
      </div>

      <!-- Category Selection -->
<div class="mb-3">
  <label class="form-label">Category</label>
  <select name="category" id="category" class="form-select">
    <option value="general">General</option>
    <option value="accelerated_ppl">Accelerated PPL</option>
    <option value="discovery_flights">Discovery Flights</option>
    <option value="zero_to_hero">Zero to Hero</option>
    <option value="ground_school">Ground School Bootcamp</option>
    <option value="rusty_pilot">Rusty Pilot</option>
    <option value="pinch_hitter">Pinch Hitter</option>
    <option value="purdue_global">Purdue Global</option>
    <option value="financing">Financing Options</option>
    <option value="pay_as_you_go">Pay as you Go</option>
    <option value="private_pilot_package">Private Pilot Package</option>
    <option value="boeing_package">Boeing Package</option>
    <option value="multi_engine">Multi Engine</option>
    <option value="maintenance">Maintenance Talk</option>
    <option value="pricing">Pricing</option>
    <option value="joe">About Joe</option>
    <option value="meghen">About Meghen</option>
    <option value="cfis">For CFIs</option>
  </select>
</div>

<!-- Optional Link Bundle -->
<div class="row mb-3">
  <div class="col-md-6">
    <label class="form-label">Optional Link URL</label>
    <input type="url" name="custom_link_url" class="form-control" placeholder="https://...">
  </div>
  <div class="col-md-6">
    <label class="form-label">Link Text</label>
    <input type="text" name="custom_link_text" class="form-control" placeholder="Link label...">
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-6">
    <label class="form-label">Optional Link Style</label>
    <select name="custom_link_class" class="form-select">
      <option value="">Plain text</option>
      <option value="btn btn-primary">Blue Button</option>
      <option value="btn btn-success">Green Button</option>
      <option value="btn btn-outline-dark">Outline Button</option>
      <option value="text-danger">Red Text</option>
    </select>
  </div>
  <div class="col-md-6 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="always_include" id="always_include" value="1">
      <label class="form-check-label" for="always_include">
        Always include this in GPT replies (if matched)
      </label>
    </div>
  </div>
</div>

<!-- Uploads -->
<div class="mb-3">
  <label class="form-label">PDF Upload</label>
  <input type="file" name="pdf_file" accept=".pdf" class="form-control">
</div>

<div class="mb-3">
  <label class="form-label">Image Upload</label>
  <input type="file" name="image_file" accept="image/*" class="form-control">
</div>

<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" name="flagged_review" id="flagged_review" value="1">
  <label class="form-check-label" for="flagged_review">
    🚩 Marked for Follow-up
  </label>
</div>

<!-- Flags -->
<div class="mb-3">
  <label class="form-label"><strong>Flags</strong></label><br>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="conversational" id="conversational" checked>
    <label class="form-check-label" for="conversational">Conversational</label>
  </div>
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" name="active" id="active" checked>
    <label class="form-check-label" for="active">Active</label>
  </div>
</div>


      <button type="submit" class="btn btn-success">💾 Save Entry</button>
    </form>
  </div>
</div>

  <div class="card shadow-sm">
    <div class="card-body">
      <h3 class="card-title">📋 Current Training Entries</h3>
      <table id="trainingTable" class="table table-bordered table-hover">
        <thead class="table-light">
          <tr>
            <th>Flag</th>
            <th>ID</th>
            <th>Question</th>
            <th>Answer</th>
            <th>Category</th>
            <th>Conversational</th>
            <th>Active</th>
            <th>Actions</th>
          </tr>
        </thead>
       <tbody>
  <?php if ($entries): ?>
    <?php foreach ($entries as $entry): ?>
      <tr>
        <td><?php echo $entry['flagged_review'] ? '🚩' : ''; ?></td>
        <td><?php echo $entry['id']; ?></td>
        <td><?php echo htmlspecialchars($entry['question']); ?></td>
        <td><?php echo htmlspecialchars($entry['answer']); ?></td>
        <td><?php echo htmlspecialchars($entry['category']); ?></td>
        <td><?php echo $entry['conversational'] ? '✅' : '—'; ?></td>
        <td><?php echo $entry['active'] ? '🟢' : '❌'; ?></td>
        <td class="text-nowrap">
          <button
            class="btn btn-sm btn-primary edit-entry"
            data-id="<?php echo $entry['id']; ?>"
            data-question="<?php echo htmlspecialchars($entry['question'], ENT_QUOTES); ?>"
            data-answer="<?php echo htmlspecialchars($entry['answer'], ENT_QUOTES); ?>"
            data-category="<?php echo $entry['category']; ?>"
            data-conversational="<?php echo $entry['conversational']; ?>"
            data-active="<?php echo $entry['active']; ?>"
            data-flagged_review="<?php echo $entry['flagged_review']; ?>"
          >✏️ Edit</button>

          <form method="POST" class="d-inline me-1">
            <input type="hidden" name="delete_id" value="<?php echo $entry['id']; ?>">
            <button type="submit" class="btn btn-sm btn-danger">🗑️ Delete</button>
          </form>

          <form method="POST" class="d-inline">
            <input type="hidden" name="hard_delete_id" value="<?php echo $entry['id']; ?>">
            <button type="submit" class="btn btn-sm btn-outline-dark" onclick="return confirm('Are you sure you want to permanently delete this entry?')">❌ Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr>
      <td colspan="7" class="text-center text-muted">No entries yet.</td>
    </tr>
  <?php endif; ?>
</tbody>

      </table>
    </div>
  </div>
    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->


<script>
  // 👇 JavaScript to pre-fill form for editing
  document.querySelectorAll('.edit-entry').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('entry_id').value = button.dataset.id;
      document.getElementById('question').value = button.dataset.question;
      document.getElementById('answer').value = button.dataset.answer;
      document.getElementById('category').value = button.dataset.category;
      document.getElementById('conversational').checked = button.dataset.conversational === "1";
      document.getElementById('active').checked = button.dataset.active === "1";
      document.getElementById('flagged_review').checked = button.dataset.flagged_review === "1";
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
</script>

<script>
  jQuery(document).ready(function ($) {
    $('#trainingTable').DataTable({
      order: [[1, 'desc']],
      pageLength: 25
    });
  });
</script>



