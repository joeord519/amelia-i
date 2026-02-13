<?php
require_once(__DIR__ . '/lib/lead-functions.php');
require_once(__DIR__ . '/lib/comm-functions.php');
require_once(__DIR__ . '/lib/sakari.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_id'])) {
  $leadId = $_POST['lead_id'];
  $wasBlocked = getLeadById($leadId)['sms_blocked'];

  $db = getDB();
  $stmt = $db->prepare("UPDATE wp_leads SET sms_blocked = :blocked WHERE id = :id");
  $stmt->execute([
    'blocked' => isset($_POST['sms_blocked']) ? 1 : 0,
    'id' => $leadId
  ]);

  $lead = getLeadById($leadId);
  if ($wasBlocked && !$lead['sms_blocked']) {
    sendSakariSMS($lead['phone'], "✅ You’ve been re-subscribed to receive text messages from Piston Aviation.");
  }
} else {
  $id = $_GET['id'] ?? null;
  if (!$id) die('No lead ID provided.');
  $lead = getLeadById($id);
}

$history = getCommHistoryByLead($lead['id']);
$fullName = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Lead: <?= htmlspecialchars($fullName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4" style="max-width: 800px;">

  <!-- Header -->
  <div class="mb-4 d-flex justify-content-between align-items-center">
    <h2 class="mb-0">📋 Lead Detail – <?= htmlspecialchars($fullName) ?></h2>
    <div class="d-flex gap-2">
      <a href="dashboard.php" class="btn btn-outline-dark btn-sm">← Back to CRM Dashboard</a>
      <form method="POST" action="dashboard.php" onsubmit="return confirm('Are you sure you want to permanently delete this lead? This cannot be undone.')" class="m-0">
        <input type="hidden" name="delete_lead_id" value="<?= $lead['id'] ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm">🗑️ Delete Lead</button>
      </form>
    </div>
  </div>

  <!-- Info -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <p><strong>📞 Phone:</strong> <?= htmlspecialchars($lead['phone'] ?? '') ?></p>
      <p><strong>✉️ Email:</strong> <?= htmlspecialchars($lead['email'] ?? '') ?></p>
      <p><strong>🎓 Program:</strong> <?= htmlspecialchars($lead['training_program'] ?? '') ?></p>

      <?php if ($lead['sms_blocked']): ?>
        <div class="alert alert-warning mt-3">
          ⚠️ This lead is opted out of SMS and will not receive text messages.
        </div>
      <?php endif; ?>

      <!-- SMS Toggle -->
      <form method="POST" class="mt-4">
        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="sms_blocked" id="sms_blocked"
            <?= $lead['sms_blocked'] ? 'checked' : '' ?> onchange="this.form.submit()">
          <label class="form-check-label" for="sms_blocked">
            SMS Blocked (STOP Received)
          </label>
        </div>
      </form>
    </div>
  </div>

  <!-- Notes -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title">🗒️ Add Note</h5>
      <form action="actions/add-note.php" method="POST">
        <textarea name="note" rows="3" class="form-control mb-2" required></textarea>
        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
        <button type="submit" class="btn btn-success">💾 Save Note</button>
      </form>
    </div>
  </div>

  <!-- Manual -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title">📨 Manual Actions</h5>
      <form action="actions/send-sms.php" method="POST" class="mb-3">
        <textarea name="message" rows="2" class="form-control mb-2" placeholder="Quick SMS..." required></textarea>
        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
        <button type="submit" class="btn btn-info text-white">📱 Send SMS</button>
      </form>
      <a href="email-builder.php?lead_id=<?= $lead['id'] ?>" class="btn btn-outline-primary">✉️ Open Email Builder</a>
    </div>
  </div>

  <!-- History -->
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">📚 Communication History</h5>
      <?php if ($history): ?>
        <ul class="list-group">
          <?php foreach ($history as $log): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start">
              <div>
                <strong class="text-uppercase text-muted">[<?= $log['type'] ?>]</strong>
                <?= ucfirst($log['direction']) ?> – <?= nl2br(htmlspecialchars(strip_tags($log['message']))) ?>
              </div>
              <small class="text-muted"><?= date('M j, Y H:i', strtotime($log['timestamp'])) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted">No communication history yet.</p>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
