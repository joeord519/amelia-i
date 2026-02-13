<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../lib/lead-functions.php');
require_once(__DIR__ . '/../lib/flow-functions.php');
require_once(__DIR__ . '/../lib/step-functions.php');
require_once(__DIR__ . '/../lib/comm-functions.php');
require_once(__DIR__ . '/../lib/sakari.php');
require_once(__DIR__ . '/../lib/mailgun.php');

date_default_timezone_set('America/Chicago');
header('Content-Type: text/plain; charset=utf-8');

// Logging (optional): track last cron run for diagnostics
function logCronRun() {
  $db = getDB();
  $stmt = $db->prepare("INSERT INTO wp_cron_logs (last_run) VALUES (NOW())");
  $stmt->execute();
}

function getRandomSMSFooter() {
  $footers = [
    "Questions? Just reply. STOP to opt out.",
    "This is a real human text. Text STOP to stop."
  ];
  return $footers[array_rand($footers)];
}

$leads = getAllLeads(); // ✅ No filtering by created_at
$today = new DateTime();
$todayDay = $today->format('D');

foreach ($leads as $lead) {
  $leadId     = $lead['id'];
  $program    = trim($lead['training_program'] ?? '');
  $createdAt  = new DateTime($lead['created_at'] ?? 'now');
  $daysSince  = $createdAt->diff($today)->days;

  echo "\n👤 Lead #{$leadId} | Program: {$program} | Created: {$createdAt->format('Y-m-d')} ({$daysSince} days ago)";

  if (empty($program)) {
    echo "\n   ⚠️ Skipping lead — no training program assigned";
    continue;
  }

  $flows = getAllFlows($program);
  foreach ($flows as $flow) {
    if (!$flow['active']) {
      echo "\n   🚫 Skipping inactive flow '{$flow['title']}'";
      continue;
    }

    echo "\n➞  Flow '{$flow['title']}' [Flow ID: {$flow['id']}]";
    $steps = getStepsByFlowId($flow['id']);
    if (empty($steps)) {
      echo "\n   ⚠️ No steps defined for this flow.";
      continue;
    }

    foreach ($steps as $step) {
      echo "\n   🔄 Step #{$step['id']} (Order {$step['step_order']} | {$step['method']})";

      if (!$step['active']) {
        echo "\n      ⚠️ Skipped: step is inactive";
        continue;
      }

      // --- Delay Logic ---
      $now = new DateTime();
      $delayType  = $step['delay_type'] ?? 'days';
      $delayValue = intval($step['delay_value'] ?? 0);
      $fromStepId = $step['delay_from_step_id'] ?? null;
      $sendWindow = $step['send_time_window'] ?? '';

      $baseDate = clone $createdAt;
      if ($fromStepId) {
        $db = getDB();
        $q = $db->prepare("SELECT sent_at FROM wp_communication_events WHERE lead_id = :lead AND step_id = :step");
        $q->execute(['lead' => $leadId, 'step' => $fromStepId]);
        $sentRow = $q->fetch();
        if (!empty($sentRow['sent_at'])) {
          $baseDate = new DateTime($sentRow['sent_at']);
        } else {
          echo "\n      ⏳ Skipped: waiting on step {$fromStepId} to send first.";
          continue;
        }
      }

      $delayInterval = match ($delayType) {
        'minutes' => "PT{$delayValue}M",
        'hours'   => "PT{$delayValue}H",
        default   => "P{$delayValue}D"
      };
      $targetDate = clone $baseDate;
      $targetDate->add(new DateInterval($delayInterval));

      if ($now < $targetDate) {
        echo "\n      ⏳ Skipped: delay not met yet (wait until {$targetDate->format('Y-m-d H:i')})";
        continue;
      }

      if ($sendWindow && preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $sendWindow, $m)) {
        $startTime = DateTime::createFromFormat('H:i', $m[1])->format('Hi');
        $endTime   = DateTime::createFromFormat('H:i', $m[2])->format('Hi');
        $nowTime   = $now->format('Hi');
        if ($nowTime < $startTime || $nowTime > $endTime) {
          echo "\n      ⏳ Skipped: current time outside allowed window ($sendWindow)";
          continue;
        }
      }

      if (wasStepAlreadySent($leadId, $step['id'])) {
        if ($step['frequency'] === 'once') {
          echo "\n      📌 Skipped: already sent and frequency is once";
          continue;
        } else {
          echo "\n      🔁 Already sent before, but repeating due to frequency: {$step['frequency']}";
        }
      }

      if ($step['frequency'] === 'custom') {
        $allowedDays = array_map('trim', explode(',', $step['send_days'] ?? ''));
        if (!in_array($todayDay, $allowedDays)) {
          echo "\n      🗖️ Skipped: today ({$todayDay}) not in send_days: {$step['send_days']}";
          continue;
        }
      }

      // --- Compose & Send ---
      $message = trim($step['message'] ?? '');
      $sent = false;

      if (empty($message)) {
        echo "\n      ⚠️ Skipped: step has no message content.";
        continue;
      }

      $message = replaceMergeTags($message, $lead);

      if ($step['method'] === 'email') {
        $subject = $step['subject'] ?: 'Message from Piston Aviation';
        $replyTo = $step['reply_to'] ?: 'replies@mail.flypiston.com';

        if (empty($lead['email'])) {
          echo "\n      ⚠️ Skipped: no email address on file.";
          continue;
        }

        $message = applyUnsubscribeLink($message, $lead);
        echo "\n      📤 Sending EMAIL to {$lead['email']} (Subject: {$subject})";
        $sent = sendMailgunEmail($lead['email'], $subject, $message, $replyTo);
      }

      elseif ($step['method'] === 'sms') {
        if (!empty($lead['sms_blocked'])) {
          echo "\n      ⛔ Skipped: SMS is blocked for this lead";
          continue;
        }

        if (empty($lead['phone'])) {
          echo "\n      ⚠️ Skipped: no phone number on file.";
          continue;
        }

        $message = strip_tags($message);
        $message = html_entity_decode($message);
        $message = preg_replace('/\s+/', ' ', $message);

        if (!str_contains(strtolower($message), 'stop')) {
          $message .= "\n\n—\n" . getRandomSMSFooter();
        }

        echo "\n      📤 Sending SMS to {$lead['phone']}";
        $sent = sendSakariSMS($lead['phone'], $message);
      }

      elseif ($step['method'] === 'manual' || $step['method'] === 'phone') {
        echo "\n      📝 Logged-only step (manual/phone)";
        $sent = true;
      }

      if ($sent) {
        echo "\n      ✅ Sent + logged";
        logCommHistory($leadId, $step['method'], 'outbound', $message);
        markStepEventAsSent($leadId, $step['id'], (new DateTime())->format('Y-m-d H:i:s'));
      } else {
        echo "\n      ❌ Failed to send";
      }
    }
  }
}

logCronRun();
echo "\n\n✅ Cron finished at " . date('Y-m-d H:i:s') . "\n";


