<?php
// /wp-content/plugins/pistonpay/webhook.php
require_once(__DIR__ . '/config.php');             // provides Stripe init + getDB()
require_once(__DIR__ . '/generate_receipt.php');   // unchanged; used in hours flow if needed

$endpoint_secret = 'whsec_p5ChUW3fphXiyJySsxnT92IhhsPY6m1n'; // Test mode key

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

// 🐛 Log raw payload for sanity check
error_log("📥 Stripe Webhook received:\n" . $payload);

try {
  $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
  error_log("✅ Stripe webhook signature verified: {$event->type}");
} catch (\UnexpectedValueException $e) {
  error_log("❌ Invalid payload: " . $e->getMessage());
  http_response_code(400);
  exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
  error_log("❌ Signature verification failed: " . $e->getMessage());
  http_response_code(400);
  exit;
}

if ($event->type === 'checkout.session.completed') {
  $session = $event->data->object;

  // 🧠 Metadata
  $md = $session->metadata ?? (object)[];
  error_log("🧠 Metadata: " . json_encode($md));

  // ===== Time Building branch =====
  if (!empty($md->type) && $md->type === 'time_building') {
    try {
      $reservationIds = [];
      if (!empty($md->reservation_ids)) {
        $reservationIds = array_map('intval', explode(',', (string)$md->reservation_ids));
      }
      $amountPaid = isset($session->amount_total) ? ($session->amount_total / 100.0) : null;
      $paymentRef = $session->payment_intent ?? ('cs_' . ($session->id ?? 'unknown'));

      if ($reservationIds) {
        // 1) Mark as paid, assign aircraft, block calendar
        $_POST = [
          'payment_ref' => $paymentRef,
          'amount_paid' => $amountPaid,
        ];
        foreach ($reservationIds as $rid) {
          $_POST['reservation_ids'][] = $rid;
        }

        $handler = __DIR__ . '/../time-building/payment_webhook.php';
        if (file_exists($handler)) {
          include $handler; // echoes JSON; do not exit here
        } else {
          error_log("❌ Time-building handler not found at $handler");
        }

        // 2) Send DocuSign (email route) with prefilled fields + log envelopeId
        try {
          require_once __DIR__ . '/docusign_send.php';
          $db = getDB();

          // Pull student contact from Stripe session
          $studentEmail = $session->customer_details->email ?? '';
          $studentName  = trim(($session->customer_details->name ?? '') ?: 'Time Building Student');

          // Build SelectedWeeks text from DB
          $weeksText = '';
          if (!empty($reservationIds)) {
            $in = implode(',', array_fill(0, count($reservationIds), '?'));
            $q = $db->prepare("
              SELECT DISTINCT w.week_start, w.week_end
              FROM wp_tb_weeks w
              JOIN wp_tb_reservations r ON r.week_id = w.id
              WHERE r.id IN ($in)
              ORDER BY w.week_start ASC
            ");
            $q->execute($reservationIds);
            $ranges = $q->fetchAll(PDO::FETCH_ASSOC);
            if ($ranges) {
              $weeksText = implode(', ', array_map(function($r){
                return $r['week_start'].'–'.$r['week_end'];
              }, $ranges));
            }
          }

          if (!empty($studentEmail)) {
            $envId = docusign_send_timebuilding_envelope($studentName, $studentEmail, $weeksText, $amountPaid);
            if ($envId) {
              error_log("✍️ DocuSign envelope sent: $envId to $studentEmail");
              // Log for later embedded redirect on success page
              try {
                $ins = $db->prepare("INSERT IGNORE INTO wp_tb_envelopes (envelope_id, lead_id, student_email) VALUES (?, ?, ?)");
                $ins->execute([$envId, (int)($md->lead_id ?? 0), $studentEmail]);
              } catch (Throwable $ie) {
                error_log("⚠️ Could not log envelopeId: ".$ie->getMessage());
              }
            } else {
              error_log("⚠️ DocuSign did not return an envelopeId");
            }
          } else {
            error_log("⚠️ Missing student email; skipped DocuSign send.");
          }
        } catch (Throwable $dx) {
          error_log("🔥 DocuSign send/log error: ".$dx->getMessage());
          // non-fatal for Stripe
        }
      } else {
        error_log("⚠️ No reservation_ids in metadata for time_building payment.");
      }

      http_response_code(200);
      echo 'ok';
      exit;

    } catch (Throwable $e) {
      error_log("🔥 Time-building processing error: " . $e->getMessage());
      // Still return 200 to avoid Stripe retry storms if payment was captured;
      // optionally return 500 if you want Stripe to retry.
      http_response_code(200);
      echo 'ok';
      exit;
    }
  }

  // ===== Original student-hours branch (unchanged) =====
  $student_id = (int) ($md->student_id ?? 0);
  $aircraft_hours = (float) ($md->aircraft_hours ?? 0);
  $instructor_hours = (float) ($md->instructor_hours ?? 0);
  $coupon_code = $md->coupon_code ?? null;
  $student_email = $md->student_email ?? '';
  $amount_paid = $session->amount_total / 100;

  error_log("💳 Processing payment: StudentID={$student_id}, Email={$student_email}, Aircraft={$aircraft_hours}, Instructor={$instructor_hours}, Amount=\${$amount_paid}");

  try {
    $db = getDB();
    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE wp_students SET aircraft_hours_remaining = aircraft_hours_remaining + ?, instructor_hours_remaining = aircraft_hours_remaining + 0 WHERE student_id = ?");
    // ^ Keep your original behavior? If you want to split both, use the line below instead of the one above:
    // $stmt = $db->prepare("UPDATE wp_students SET aircraft_hours_remaining = aircraft_hours_remaining + ?, instructor_hours_remaining = instructor_hours_remaining + ? WHERE student_id = ?");
    $stmt->execute([$aircraft_hours, $student_id]);

    // If using split update, uncomment:
    // $stmt->execute([$aircraft_hours, $instructor_hours, $student_id]);

    error_log("✅ Student account updated.");

    $stmt = $db->prepare("INSERT INTO wp_payments (student_id, method, amount, aircraft_hours, instructor_hours, coupon_code, purchased_by, stripe_session_id) VALUES (?, 'stripe', ?, ?, ?, ?, 'student', ?)");
    $stmt->execute([$student_id, $amount_paid, $aircraft_hours, $instructor_hours, $coupon_code, $session->id]);
    $payment_id = $db->lastInsertId();
    error_log("✅ Payment logged (ID: $payment_id)");

    $db->commit();

    // Try to save Stripe's hosted receipt URL
    try {
      $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
      $charges = $paymentIntent->charges->data;

      if (!empty($charges)) {
        $receiptUrl = $charges[0]->receipt_url ?? null;

        if ($receiptUrl) {
          $stmt = $db->prepare("UPDATE wp_payments SET receipt_file = ? WHERE id = ?");
          $stmt->execute([$receiptUrl, $payment_id]);
          error_log("🧾 Stripe receipt saved: $receiptUrl");
        } else {
          error_log("⚠️ Stripe receipt URL not available.");
        }
      } else {
        error_log("⚠️ No charge data found for payment intent.");
      }
    } catch (Exception $e) {
      error_log("❌ Error retrieving Stripe receipt: " . $e->getMessage());
    }

    $stmt = $db->prepare("SELECT aircraft_hours_remaining, instructor_hours_remaining FROM wp_students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $balances = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("📊 Updated balances: " . json_encode($balances));

    http_response_code(200);
    echo 'ok';
    exit;
  } catch (Exception $e) {
    $db->rollBack();
    error_log("🔥 DB Error: " . $e->getMessage());
    http_response_code(500);
    exit;
  }
}

http_response_code(200);
echo 'ok';