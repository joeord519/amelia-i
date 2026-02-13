<?php
require_once(__DIR__ . '/lib/step-functions.php');
require_once(__DIR__ . '/lib/flow-functions.php');

$feedback = '';
$step = null;
$flow_id = $_GET['flow_id'] ?? null;

// Handle delete
if (isset($_GET['delete'])) {
  deleteStep($_GET['delete']);
  header("Location: flow-builder.php?edit=" . $_GET['flow_id']);
  exit;
}

// Handle edit
if (isset($_GET['step_id'])) {
  $step = getStepById($_GET['step_id']);
  $flow_id = $step['flow_id'];
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $method = $_POST['method'] ?? 'email';

  $emailBody = $_POST['message'] ?? '';

if ($method === 'email' && isset($_POST['opt_out_footer'])) {
  $emailBody .= "<p style='font-size: small; color: gray;'>—<br><a href='{{unsubscribe_link}}'>Unsubscribe from future emails</a></p>";
}


  $data = [
    'flow_id'     => $_POST['flow_id'],
    'step_order'  => intval($_POST['step_order']),
    'method'      => $method,
    'subject'     => $_POST['subject'] ?? '',
    'reply_to'    => $_POST['reply_to'] ?? '',
    'message'     => $emailBody,
    'frequency'   => $_POST['frequency'],
    'send_days'   => isset($_POST['send_days']) && is_array($_POST['send_days']) ? implode(',', $_POST['send_days']) : '',
    'delay_from_step_id' => $_POST['delay_from_step_id'] ?? null,
    'delay_type' => $_POST['delay_type'] ?? 'days',
    'delay_value' => intval($_POST['delay_value'] ?? 0),
    'send_time_window' => $_POST['send_time_window'] ?? '',
    'active'      => isset($_POST['active']) ? 1 : 0
  ];

  if (!empty($_POST['step_id'])) {
    updateStep($_POST['step_id'], $data);
    $feedback = '<div class="alert alert-info">Step updated successfully.</div>';
  } else {
    addStep($data);
    $feedback = '<div class="alert alert-success">Step added successfully.</div>';
  }

  header("Location: flow-builder.php?edit=" . $_POST['flow_id'] . "&_t=" . time());
  exit;
}

$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$selectedDays = explode(',', $step['send_days'] ?? '');
$method = $step['method'] ?? '';
$flowSteps = getStepsByFlowId($flow_id);
?>

<!-- HTML begins -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $step ? 'Edit Step' : 'Add Step' ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <div class="row">
    <?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

    <div class="col-md-9">

  <div class="mb-4 d-flex justify-content-between align-items-center">
    <h2 class="mb-0"><?= $step ? '✏️ Edit Step' : '➕ Add New Step' ?></h2>
    <a href="flow-builder.php?edit=<?= $flow_id ?>" class="btn btn-outline-dark btn-sm">← Back to Flow</a>
  </div>

  <?= $feedback ?>

  <form method="POST">
    <?php if ($step): ?>
      <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
    <?php endif; ?>
    <input type="hidden" name="flow_id" value="<?= $flow_id ?>">

    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Step Order</label>
        <input type="number" name="step_order" class="form-control" required value="<?= htmlspecialchars($step['step_order'] ?? 1) ?>">
      </div>
      <div class="col-md-3">
  <label class="form-label">Delay Value</label>
  <input type="number" name="delay_value" class="form-control" value="<?= htmlspecialchars($step['delay_value'] ?? 0) ?>">
</div>
<div class="col-md-3">
  <label class="form-label">Delay Type</label>
  <select name="delay_type" class="form-select">
    <option value="minutes" <?= ($step['delay_type'] ?? '') === 'minutes' ? 'selected' : '' ?>>Minutes</option>
    <option value="hours" <?= ($step['delay_type'] ?? '') === 'hours' ? 'selected' : '' ?>>Hours</option>
    <option value="days" <?= ($step['delay_type'] ?? 'days') === 'days' ? 'selected' : '' ?>>Days</option>
  </select>
</div>

      <div class="col-md-3">
        <label class="form-label">Frequency</label>
        <select name="frequency" class="form-select" id="frequencySelect">
          <option value="once" <?= ($step['frequency'] ?? '') === 'once' ? 'selected' : '' ?>>Once</option>
          <option value="daily" <?= ($step['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
          <option value="weekly" <?= ($step['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
          <option value="custom" <?= ($step['frequency'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom Days</option>
        </select>
      </div>
    </div>

    <div id="customDaysBox" class="mb-3" style="display: none;">
      <label class="form-label">Send Days</label>
      <div class="row">
        <?php foreach ($days as $day): ?>
          <div class="col-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="send_days[]" value="<?= $day ?>" <?= in_array($day, $selectedDays) ? 'checked' : '' ?>>
              <label class="form-check-label"><?= $day ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Delay From Step</label>
        <select name="delay_from_step_id" class="form-select">
          <option value="">— From Lead Creation —</option>
          <?php foreach ($flowSteps as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($step['delay_from_step_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
              #<?= $s['step_order'] ?> <?= ucfirst($s['method']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
            <div class="col-md-4">
        <label class="form-label">Send Time Window</label>
        <input type="text" name="send_time_window" class="form-control" placeholder="e.g. 08:00-18:00" value="<?= htmlspecialchars($step['send_time_window'] ?? '') ?>">
      </div>
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
      <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($step['subject'] ?? '') ?>">
    </div>

    <div id="replyToBlock" class="mb-3" style="display: none;">
      <label class="form-label">Reply-To Email</label>
      <input type="email" name="reply_to" class="form-control" placeholder="you@flypiston.com" value="<?= htmlspecialchars($step['reply_to'] ?? '') ?>">
    </div>

    <div id="emailEditorBlock" class="mb-3" style="display: none;">
      <label class="form-label">Email Body</label>
      <textarea name="message" id="email_body" rows="6"><?= htmlspecialchars($step['message'] ?? '') ?></textarea>
    </div>

    <div id="plainMessageBlock" class="mb-3" style="display: none;">
      <label class="form-label">Message</label>
      <textarea name="message" id="plain_message" rows="4" class="form-control"><?= htmlspecialchars($step['message'] ?? '') ?></textarea>
    </div>

    <div class="form-check mb-3" id="optOutBlock" style="display: none;">
      <input class="form-check-input" type="checkbox" name="opt_out_footer" id="opt_out_footer" checked>
      <label class="form-check-label" for="opt_out_footer">Include unsubscribe link</label>
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($step['active']) || $step['active'] ? 'checked' : '' ?>>
      <label class="form-check-label" for="active">Active</label>
    </div>

    <button type="submit" class="btn btn-primary"><?= $step ? '💾 Update Step' : '➕ Create Step' ?></button>
  </form>
</div>

<script src="https://cdn.tiny.cloud/1/dcn2x687vile0857j1azvvn32ctmju42bg6mxodv8lj8ixio/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
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

    freqSelect.addEventListener('change', toggleCustomDays);
    methodSelect.addEventListener('change', toggleMessageField);

    toggleCustomDays();
    toggleMessageField();
  });
</script>




