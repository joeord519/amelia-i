<?php
require_once(__DIR__ . '/lib/lead-functions.php');

$leadId = $_GET['lead_id'] ?? null;
$email = $_GET['email'] ?? null;
$confirmed = false;

if ($leadId && $email) {
  $lead = getLeadById($leadId);
  if ($lead && strtolower(trim($lead['email'])) === strtolower(trim($email))) {
    // Either mark unsubscribed:
    // $db = getDB();
    // $stmt = $db->prepare("UPDATE wp_leads SET opt_out = 1 WHERE id = :id");
    // $stmt->execute(['id' => $leadId]);

    // Or permanently delete:
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM wp_leads WHERE id = :id");
    $stmt->execute(['id' => $leadId]);
    $confirmed = true;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Unsubscribe</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5 text-center" style="max-width: 600px;">
  <h2 class="mb-4">Email Preferences</h2>

  <?php if ($confirmed): ?>
    <div class="alert alert-success">
      ✅ You've been successfully unsubscribed. You won’t receive future messages from us.
    </div>
  <?php else: ?>
    <div class="alert alert-danger">
      ⚠️ Sorry, we couldn’t confirm your unsubscribe request.
    </div>
  <?php endif; ?>
</div>
</body>
</html>
