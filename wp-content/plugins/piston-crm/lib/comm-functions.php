<?php
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/step-functions.php'); // Needed for getStepById()

// Get full communication history for a lead
function getCommHistoryByLead($lead_id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT * FROM wp_communication_history WHERE lead_id = :id ORDER BY timestamp DESC");
  $stmt->execute(['id' => $lead_id]);
  return $stmt->fetchAll();
}

// Log new interaction (email, sms, phone, note)
function logCommHistory($lead_id, $type, $direction, $message) {
  $db = getDB();
  $stmt = $db->prepare("
    INSERT INTO wp_communication_history (lead_id, type, direction, message) 
    VALUES (:lead_id, :type, :direction, :message)
  ");
  $stmt->execute([
    'lead_id'   => $lead_id,
    'type'      => $type,
    'direction' => $direction,
    'message'   => $message
  ]);

  // Update lead's last_contacted_at timestamp
  $stmt2 = $db->prepare("UPDATE wp_leads SET last_contacted_at = NOW() WHERE id = :id");
  $stmt2->execute(['id' => $lead_id]);
}

// Check if a step was already sent
function wasStepAlreadySent($lead_id, $step_id) {
  $db = getDB();
  $stmt = $db->prepare("SELECT COUNT(*) FROM wp_communication_events WHERE lead_id = :lead AND step_id = :step");
  $stmt->execute(['lead' => $lead_id, 'step' => $step_id]);
  return $stmt->fetchColumn() > 0;
}

// Mark step as sent (log event)
function markStepEventAsSent($leadId, $stepId, $sentAt = null) {
  $db = getDB();

  // Get flow ID from the step
  $step = getStepById($stepId);
  $flowId = $step['flow_id'] ?? null;

  if (!$flowId) {
    error_log("❌ markStepEventAsSent: Missing flow_id for step #$stepId");
    return false;
  }

  $stmt = $db->prepare("
    INSERT INTO wp_communication_events (lead_id, flow_id, step_id, sent_at)
    VALUES (:lead_id, :flow_id, :step_id, :sent_at)
  ");
  return $stmt->execute([
    'lead_id' => $leadId,
    'flow_id' => $flowId,
    'step_id' => $stepId,
    'sent_at' => $sentAt ?? (new DateTime())->format('Y-m-d H:i:s')
  ]);
}

// Replace merge tags like {{first_name}}, {{email}}, etc.
function replaceMergeTags($message, $lead) {
  $replacements = [
    '{{first_name}}' => $lead['first_name'] ?? '',
    '{{last_name}}'  => $lead['last_name'] ?? '',
    '{{email}}'      => $lead['email'] ?? '',
    '{{phone}}'      => $lead['phone'] ?? ''
  ];

  return strtr($message, $replacements);
}

// Replace token with unsubscribe link (for email)
function applyUnsubscribeLink($message, $lead) {
  $url = "https://flypiston.com/crm/unsubscribe.php?lead_id={$lead['id']}&email=" . urlencode($lead['email']);
  return str_replace("{{unsubscribe_link}}", $url, $message);
}

// Build raw unsubscribe link (used in builder view)
function buildUnsubscribeLink($lead) {
  return "https://amelia-i.com/wp-content/plugins/piston-crm/unsubscribe.php?lead_id={$lead['id']}&email=" . urlencode($lead['email']);
}

