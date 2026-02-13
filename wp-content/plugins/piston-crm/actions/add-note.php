<?php
require_once(__DIR__ . '/../lib/lead-functions.php');
require_once(__DIR__ . '/../lib/comm-functions.php');

$lead_id = $_POST['lead_id'] ?? null;
$note = trim($_POST['note'] ?? '');

if ($lead_id && $note) {
  addLeadNote($lead_id, $note);
  logCommHistory($lead_id, 'note', 'outbound', $note);
}

header("Location: ../lead-detail.php?id=$lead_id");
exit;
