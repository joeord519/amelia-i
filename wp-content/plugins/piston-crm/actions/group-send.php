<?php
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/../lib/lead-functions.php');
require_once(__DIR__ . '/../lib/comm-functions.php');
require_once(__DIR__ . '/../lib/sakari.php');
require_once(__DIR__ . '/../lib/mailgun.php');

$programs = array_unique(array_filter(array_column(getAllLeads(), 'training_program')));
sort($programs);
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $type = $_POST['type'] ?? '';
  $program = $_POST['program'] ?? '';
  $count = 0;

  if ($type === 'email') {
    $subject = $_POST['subject'] ?? '';
    $html = $_POST['message'] ?? '';
    $replyTo = $_POST['reply_to'] ?? 'replies@mail.flypiston.com';
    $includeOptOut = isset($_POST['opt_out_footer']);

    if ($program && $subject && $html) {
      $leads = getLeadsByProgram($program);
      foreach ($leads as $lead) {
        if (empty($lead['email'])) continue;

        $finalMessage = replaceMergeTags($html, $lead);
        if ($includeOptOut) {
          $finalMessage = applyUnsubscribeLink($finalMessage, $lead);
        }

        $sent = sendMailgunEmail($lead['email'], $subject, $finalMessage, $replyTo);
        if ($sent) {
          logCommHistory($lead['id'], 'email', 'outbound', $finalMessage);
          $count++;
        }
      }
      header("Location: group-send.php?sent=email&count={$count}&program=" . urlencode($program));
      exit;
    }
  }

  if ($type === 'sms') {
    $message = $_POST['sms_message'] ?? '';
    $includeFooter = isset($_POST['opt_out_footer_sms']);

    if ($program && $message) {
  $leads = getLeadsByProgram($program);
  foreach ($leads as $lead) {
    $leadId = $lead['id'];
    $phone = preg_replace('/[^0-9]/', '', $lead['phone'] ?? '');
    if (strlen($phone) === 10) {
      $phone = '+1' . $phone;
    }
    if (empty($phone)) continue;

    $personalized = replaceMergeTags($message, $lead);
    $cleaned = strip_tags(html_entity_decode($personalized));

    if ($includeFooter && !str_contains(strtolower($cleaned), 'stop')) {
      $cleaned .= "\n\n—\nText STOP to unsubscribe.";
    }

    $sent = sendSakariSMS($phone, $cleaned);
    
    // ✅ Always log what happened — success or fail
    logCommHistory($leadId, 'sms', $sent ? 'outbound' : 'failed', $cleaned);

    if ($sent) {
      $count++;
    }
  }

  header("Location: group-send.php?sent=sms&count={$count}&program=" . urlencode($program));
  exit;
}

  }
}

function getLeadsByProgram($program) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_leads WHERE training_program = :program");
  $stmt->execute(['program' => $program]);
  return $stmt->fetchAll();
}

$preSelectedProgram = $_GET['program'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Group Messaging</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.tiny.cloud/1/dcn2x687vile0857j1azvvn32ctmju42bg6mxodv8lj8ixio/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    tinymce.init({
      selector: '#message',
      height: 300,
      plugins: 'link lists code',
      toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link | code',
      branding: false
    });
  </script>
</head>
<body>
<div class="container py-4" style="max-width: 900px;">
  <h3 class="mb-4">📣 Group Messaging</h3>

  <div class="mb-3">
    <a href="../dashboard.php" class="btn btn-outline-dark btn-sm">← Back to Dashboard</a>
  </div>

  <?php if (isset($_GET['sent'])): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
      ✅ Sent <?= htmlspecialchars($_GET['sent']) ?> to <?= htmlspecialchars($_GET['count']) ?> lead(s) in "<?= htmlspecialchars($_GET['program']) ?>"
      <span>
        <a href="../dashboard.php" class="btn btn-sm btn-outline-dark ms-3">← Back</a>
        <a href="group-send.php" class="btn btn-sm btn-outline-primary ms-2">↻ New Message</a>
      </span>
    </div>
  <?php endif; ?>

  <ul class="nav nav-pills mb-4" id="tabs">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="pill" href="#emailTab">📧 Email</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="pill" href="#smsTab">📱 SMS</a>
    </li>
  </ul>

  <div class="tab-content">
    <!-- EMAIL TAB -->
    <div class="tab-pane fade show active" id="emailTab">
      <form method="POST">
        <input type="hidden" name="type" value="email">

        <div class="mb-3">
          <label class="form-label">Program</label>
          <select name="program" class="form-select" required>
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $prog): ?>
              <option value="<?= htmlspecialchars($prog) ?>" <?= $preSelectedProgram === $prog ? 'selected' : '' ?>>
                <?= htmlspecialchars($prog) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Subject</label>
          <input type="text" name="subject" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Reply-To</label>
          <input type="email" name="reply_to" class="form-control" placeholder="replies@mail.flypiston.com">
        </div>

        <div class="mb-3">
          <label class="form-label">Message</label>
          <textarea name="message" id="message" class="form-control"></textarea>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="opt_out_footer" id="opt_out_footer" checked>
          <label class="form-check-label" for="opt_out_footer">Include unsubscribe link</label>
        </div>

        <button type="submit" class="btn btn-primary">📨 Send Group Email</button>
      </form>
    </div>

    <!-- SMS TAB -->
    <div class="tab-pane fade" id="smsTab">
      <form method="POST">
        <input type="hidden" name="type" value="sms">

        <div class="mb-3">
          <label class="form-label">Program</label>
          <select name="program" class="form-select" required>
            <option value="">— Select Program —</option>
            <?php foreach ($programs as $prog): ?>
              <option value="<?= htmlspecialchars($prog) ?>" <?= $preSelectedProgram === $prog ? 'selected' : '' ?>>
                <?= htmlspecialchars($prog) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">SMS Message</label>
          <textarea name="sms_message" rows="4" class="form-control" required></textarea>
          <small class="text-muted">Merge tags supported: <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{email}}</code></small>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="opt_out_footer_sms" id="opt_out_footer_sms" checked>
          <label class="form-check-label" for="opt_out_footer_sms">Include STOP to unsubscribe</label>
        </div>

        <button type="submit" class="btn btn-primary">📲 Send Group SMS</button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
