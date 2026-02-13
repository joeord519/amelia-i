<?php
require_once(__DIR__ . '/lib/lead-functions.php');
require_once(__DIR__ . '/lib/comm-functions.php');
require_once(__DIR__ . '/lib/mailgun.php');

$leadId = $_GET['lead_id'] ?? null;
$lead = $leadId ? getLeadById($leadId) : null;
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
  $subject = $_POST['subject'] ?? 'No Subject';
  $message = $_POST['message'];
  $leadId = $_POST['lead_id'];
  $includeOptOut = isset($_POST['opt_out_footer']);
  $replyTo = trim($_POST['reply_to'] ?? 'replies@mail.flypiston.com');

  $lead = getLeadById($leadId);
  $email = $lead['email'];
  $name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));

  // Replace tokens
  $messageFinal = str_replace(
    ['{{first_name}}', '{{last_name}}', '{{email}}'],
    [$lead['first_name'], $lead['last_name'], $lead['email']],
    $message
  );

  if ($includeOptOut) {
    $optOutUrl = buildUnsubscribeLink($lead);
    $messageFinal .= "<p style='font-size: small; color: gray;'>—<br><a href='$optOutUrl'>Unsubscribe from future emails</a></p>";
  }

  $sent = sendMailgunEmail($email, $subject, $messageFinal, $replyTo);
  if ($sent) {
    logCommHistory($leadId, 'email', 'outbound', $messageFinal);
    $feedback = '<div class="alert alert-success">✅ Email sent successfully to ' . htmlspecialchars($name) . '</div>';
  } else {
    $feedback = '<div class="alert alert-danger">❌ Failed to send email.</div>';
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Builder</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.tiny.cloud/1/dcn2x687vile0857j1azvvn32ctmju42bg6mxodv8lj8ixio/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    tinymce.init({
      selector: '#email_body',
      height: 300,
      menubar: false,
      plugins: [
        'advlist autolink lists link image charmap anchor',
        'searchreplace visualblocks code fullscreen',
        'insertdatetime media table paste help wordcount'
      ],
      toolbar: 'undo redo | formatselect | bold italic underline | fontsizeselect forecolor backcolor | ' +
               'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
               'link image table | removeformat | help',
      branding: false
    });
  </script>
</head>
<body>
<div class="container py-4" style="max-width: 800px;">
  <div class="mb-4 d-flex justify-content-between align-items-center">
    <h3>Email Builder</h3>
    <a href="dashboard.php" class="btn btn-sm btn-outline-dark">← Back to Dashboard</a>
  </div>

  <?= $feedback ?>

  <?php if (!$lead): ?>
    <div class="alert alert-warning">No lead selected. Please provide a <code>?lead_id=###</code> in the URL.</div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
          <input type="hidden" name="send_email" value="1">

          <div class="mb-3">
            <label class="form-label">To</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($lead['email']) ?>" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Reply-To Email</label>
            <input type="email" name="reply_to" class="form-control" placeholder="you@flypiston.com" value="<?= htmlspecialchars($_POST['reply_to'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea id="email_body" name="message" class="form-control" rows="6" required><?= htmlspecialchars($_POST['message'] ?? "Hi {{first_name}},<br><br>Just wanted to follow up with you...") ?></textarea>
          </div>

          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="opt_out_footer" id="opt_out_footer" checked>
            <label class="form-check-label" for="opt_out_footer">Include unsubscribe link</label>
          </div>

          <button type="submit" class="btn btn-primary">📨 Send Email</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>


