<?php
require_once(__DIR__ . '/lib/flow-functions.php');
require_once(__DIR__ . '/lib/lead-functions.php');
require_once(__DIR__ . '/lib/step-functions.php');

$feedback = '';
$editing = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $emailBody = $_POST['email_body'] ?? $_POST['message'] ?? '';
  $includeOptOut = isset($_POST['opt_out_footer']);

  if ($includeOptOut && ($_POST['method'] ?? '') === 'email') {
    $emailBody .= "<p style='font-size: small; color: gray;'>—<br><a href='{{unsubscribe_link}}'>Unsubscribe from future emails</a></p>";
  }

  $data = [
  'program_name'       => $_POST['program_name'] ?? '',
  'title'              => $_POST['title'] ?? '',
  'method'             => $_POST['method'] ?? 'sms',
  'subject'            => $_POST['subject'] ?? '',
  'reply_to'           => $_POST['reply_to'] ?? '',
  'message'            => $emailBody,
  'frequency'          => $_POST['frequency'] ?? 'once',
  'delay_type'         => $_POST['delay_type'] ?? 'days',
  'delay_value'        => intval($_POST['delay_value'] ?? 0),
  'send_time_window'   => $_POST['send_time_window'] ?? '',
  'send_days'          => is_array($_POST['send_days'] ?? null) ? implode(',', $_POST['send_days']) : ($_POST['send_days'] ?? ''),
  'active'             => isset($_POST['active']) ? 1 : 0
];

  try {
    if (!empty($_POST['flow_id'])) {
      updateFlow($_POST['flow_id'], $data);
      $feedback = '<div class="alert alert-info">Flow updated successfully.</div>';
    } else {
      $flowId = addFlow($data);

      // Create initial step based on flow fields
      addStep([
  'flow_id'            => $flowId,
  'step_order'         => 1,
  'method'             => $data['method'],
  'subject'            => $data['subject'] ?? '',
  'reply_to'           => $data['reply_to'] ?? '',
  'message'            => $data['message'],
  'delay_type'         => $data['delay_type'] ?? 'days',
  'delay_value'        => $data['delay_value'] ?? 0,
  'delay_from_step_id' => null,
  'send_time_window'   => $data['send_time_window'] ?? '',
  'frequency'          => $data['frequency'],
  'send_days'          => $data['send_days'],
  'active'             => $data['active']
]);


      $feedback = '<div class="alert alert-success">Flow and initial step created successfully.</div>';
    }
  } catch (Exception $e) {
    $feedback = '<div class="alert alert-danger">❌ Failed to save flow: ' . $e->getMessage() . '</div>';
  }
}

// Handle edit and delete actions
if (isset($_GET['edit'])) {
  $editing = getFlowById($_GET['edit']);
  $steps = getStepsByFlowId($editing['id']);
}

if (isset($_GET['delete'])) {
  deleteFlow($_GET['delete']);
  $feedback = '<div class="alert alert-danger">Flow deleted.</div>';
}

$flows = getAllFlows();
$programs = array_unique(array_column(getAllLeads(), 'training_program'));
sort($programs);
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$selected = explode(',', $editing['send_days'] ?? '');
$method = $editing['method'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Flow Builder</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">
      <div class="mb-4 d-flex justify-content-between align-items-center">

    <h2 class="mb-0">🔁 Communication Flow Builder</h2>
    <a href="dashboard.php" class="btn btn-outline-dark btn-sm">← Back to CRM Dashboard</a>
  </div>

  <?= $feedback ?>

  <!-- Existing Flows Table -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title">📋 Existing Flows</h5>
      <table class="table table-bordered table-hover">
        <thead class="table-light">
<tr>
  <th>Program</th>
  <th>Title</th>
  <th>Type</th>
  <th>Frequency</th>
  <th>Delay</th>
  <th>Time Window</th>
  <th>Days</th>
  <th>Status</th>
  <th>Actions</th>
</tr>
</thead>
        <tbody>
        <?php if (empty($flows)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No flows created yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($flows as $flow): ?>
            <tr>
  <td><?= htmlspecialchars($flow['program_name']) ?></td>
  <td><?= htmlspecialchars($flow['title']) ?></td>
  <td><?= ucfirst($flow['method']) ?></td>
  <td><?= ucfirst($flow['frequency']) ?></td>
  <td>
    <?= intval($flow['delay_value'] ?? 0) ?>
    <?= htmlspecialchars($flow['delay_type'] ?? 'days') ?>
  </td>
  <td><?= htmlspecialchars($flow['send_time_window'] ?? '') ?></td>
  <td><?= htmlspecialchars($flow['send_days']) ?></td>
  <td><?= $flow['active'] ? '✅' : '❌' ?></td>
  <td>
    <a href="?edit=<?= $flow['id'] ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
    <a href="?delete=<?= $flow['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this flow?')">🗑️</a>
  </td>
</tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Flow Form -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title"><?= $editing ? '✏️ Edit Flow' : '➕ New Flow' ?></h5>
      <form method="POST">
        <?php if ($editing): ?>
          <input type="hidden" name="flow_id" value="<?= $editing['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label">Program Name</label>
          <select name="program_name" class="form-select" required>
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?= htmlspecialchars($program) ?>" <?= ($editing['program_name'] ?? '') === $program ? 'selected' : '' ?>>
                <?= htmlspecialchars($program) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editing['title'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Method</label>
          <select name="method" class="form-select" id="methodSelect" required>
            <option value="email" <?= $method === 'email' ? 'selected' : '' ?>>Email</option>
            <option value="sms" <?= $method === 'sms' ? 'selected' : '' ?>>SMS</option>
            <option value="phone" <?= $method === 'phone' ? 'selected' : '' ?>>Phone</option>
            <option value="manual" <?= $method === 'manual' ? 'selected' : '' ?>>Manual</option>
          </select>
        </div>

        <div id="subjectBlock" class="mb-3" style="display: none;">
          <label class="form-label">Subject Line</label>
          <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($editing['subject'] ?? '') ?>">
        </div>
        <div class="mb-3" id="replyToBlock" style="display: none;">
  <label class="form-label">Reply-To Email</label>
  <input type="email" name="reply_to" class="form-control" placeholder="you@flypiston.com" value="<?= htmlspecialchars($editing['reply_to'] ?? '') ?>">
</div>
        <!-- Email Editor -->
<div id="emailEditorBlock" class="mb-3" style="display: none;">
  <label class="form-label">Email Body</label>
  <?php
    $emailBody = $editing['message'] ?? '';
    include(__DIR__ . '/components/email-editor.php');
  ?>
</div>

<!-- Plain textarea -->
<div id="plainMessageBlock" class="mb-3" style="display: none;">
  <label class="form-label">Message</label>
  <textarea name="message" rows="3" class="form-control"><?= htmlspecialchars($editing['message'] ?? '') ?></textarea>
</div>


        <!-- Unsubscribe toggle -->
        <div class="form-check mb-3" id="optOutBlock" style="display: none;">
          <input class="form-check-input" type="checkbox" name="opt_out_footer" id="opt_out_footer" checked>
          <label class="form-check-label" for="opt_out_footer">Include unsubscribe link</label>
        </div>

        <!-- Frequency + Delay -->
        <div class="row mb-3">
  <div class="col-md-3">
    <label class="form-label">Frequency</label>
    <select name="frequency" class="form-select" id="frequencySelect">
      <option value="once" <?= ($editing['frequency'] ?? '') === 'once' ? 'selected' : '' ?>>Once</option>
      <option value="daily" <?= ($editing['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
      <option value="weekly" <?= ($editing['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
      <option value="custom" <?= ($editing['frequency'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom Days</option>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label">Delay Value</label>
    <input type="number" name="delay_value" class="form-control" value="<?= htmlspecialchars($editing['delay_value'] ?? 0) ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Delay Type</label>
    <select name="delay_type" class="form-select">
      <option value="minutes" <?= ($editing['delay_type'] ?? '') === 'minutes' ? 'selected' : '' ?>>Minutes</option>
      <option value="hours" <?= ($editing['delay_type'] ?? '') === 'hours' ? 'selected' : '' ?>>Hours</option>
      <option value="days" <?= ($editing['delay_type'] ?? 'days') === 'days' ? 'selected' : '' ?>>Days</option>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label">Time Window (HH:MM-HH:MM)</label>
    <input type="text" name="send_time_window" class="form-control" value="<?= htmlspecialchars($editing['send_time_window'] ?? '') ?>" placeholder="08:00-18:00">
  </div>
</div>


        <!-- Custom Day Selection -->
        <div id="customDaysBox" class="mb-3" style="display: none;">
          <label class="form-label">Send Days</label>
          <div class="row">
            <?php foreach ($days as $day): ?>
              <div class="col-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="send_days[]" value="<?= $day ?>" <?= in_array($day, $selected) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= $day ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($editing['active']) || $editing['active'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="active">Active</label>
        </div>

        <button type="submit" class="btn btn-primary"><?= $editing ? '💾 Update Flow' : '➕ Create Flow' ?></button>
      </form>
    </div>
  </div>

  <?php if (!empty($editing)): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title">🧱 Steps for: <?= htmlspecialchars($editing['title']) ?></h5>
        <div class="mb-3 text-end">
          <a href="step-builder.php?flow_id=<?= $editing['id'] ?>" class="btn btn-sm btn-success">➕ Add Step</a>
        </div>

        <?php if (empty($steps)): ?>
          <p class="text-muted">No steps yet. Add your first message above.</p>
        <?php else: ?>
          <table class="table table-bordered table-hover">
            <thead class="table-light">
  <tr>
  <th>#</th>
  <th>Method</th>
  <th>Delay</th>
  <th>Delay From</th>
  <th>Time Window</th>
  <th>Frequency</th>
  <th>Send Days</th>
  <th>Message</th>
  <th>Status</th>
  <th>Actions</th>
</tr>
</thead>

<tbody>
  <?php foreach ($steps as $step): ?>
    <tr>
      <td><?= $step['step_order'] ?></td>
      <td><?= ucfirst($step['method']) ?></td>
      <td><?= intval($step['delay_value'] ?? 0) ?> <?= htmlspecialchars($step['delay_type'] ?? 'days') ?></td>
      <td><?= $step['delay_from_step_id'] ? 'Step ' . intval($step['delay_from_step_id']) : 'Flow Start' ?></td>
      <td><?= htmlspecialchars($step['send_time_window'] ?? '') ?></td>
      <td><?= ucfirst($step['frequency']) ?></td>
      <td><?= htmlspecialchars($step['send_days']) ?></td>
      <td><?= nl2br(htmlspecialchars(strip_tags($step['message'] ?? ''))) ?></td>
      <td><?= $step['active'] ? '✅' : '❌' ?></td>
      <td>
        <a href="step-builder.php?step_id=<?= $step['id'] ?>&flow_id=<?= $editing['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
        <a href="step-builder.php?delete=<?= $step['id'] ?>&flow_id=<?= $editing['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this step?')">🗑️</a>
      </td>
    </tr>
  <?php endforeach; ?>
</tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- TinyMCE + UI Logic -->
<script src="https://cdn.tiny.cloud/1/dcn2x687vile0857j1azvvn32ctmju42bg6mxodv8lj8ixio/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
  let editorLoaded = false;

  function loadTinyMCE() {
    if (editorLoaded) return;
    tinymce.init({
      selector: '#email_body',
      height: 300,
      menubar: false,
      plugins: [
        'advlist autolink lists link image charmap anchor',
        'searchreplace visualblocks code fullscreen',
        'insertdatetime media table paste help wordcount'
      ],
      toolbar:
        'undo redo | formatselect | bold italic underline | fontsizeselect forecolor backcolor | ' +
        'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
        'link image table | removeformat | help',
      branding: false
    });
    editorLoaded = true;
  }

  function unloadTinyMCE() {
    if (tinymce.get('email_body')) {
      tinymce.get('email_body').remove();
      editorLoaded = false;
    }
  }

  const freqSelect     = document.getElementById('frequencySelect');
  const methodSelect   = document.getElementById('methodSelect');
  const customBox      = document.getElementById('customDaysBox');
  const emailEditor    = document.getElementById('emailEditorBlock');
  const plainText      = document.getElementById('plainMessageBlock');
  const optOutBlock    = document.getElementById('optOutBlock');
  const subjectBlock   = document.getElementById('subjectBlock');
  const replyToBlock   = document.getElementById('replyToBlock');

  function toggleCustomDays() {
    customBox.style.display = freqSelect.value === 'custom' ? 'block' : 'none';
  }

  function toggleMessageField() {
    const method = methodSelect.value;

    if (method === 'email') {
      subjectBlock.style.display   = 'block';
      replyToBlock.style.display   = 'block';
      emailEditor.style.display    = 'block';
      optOutBlock.style.display    = 'block';
      plainText.style.display      = 'none';
      loadTinyMCE();
    } else {
      subjectBlock.style.display   = 'none';
      replyToBlock.style.display   = 'none';
      emailEditor.style.display    = 'none';
      optOutBlock.style.display    = 'none';
      plainText.style.display      = 'block';
      unloadTinyMCE();
    }
  }

  // Run after full DOM load
  document.addEventListener('DOMContentLoaded', function () {
    toggleCustomDays();
    toggleMessageField();
  });
</script>

    </div> <!-- end col-md-9 -->
  </div> <!-- end row -->
</div> <!-- end container -->

</body>
</html>

