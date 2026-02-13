<?php
// stripe-webhook.php – Standalone webhook handler for Joey Deal Agent payments.

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// ---------------------------------------------------------------------
// Includes: PDO connection, config, and SDK
// ---------------------------------------------------------------------
require_once __DIR__ . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-content/secure-config/stripe-config.php';
require_once __DIR__ . '/stripe-php/init.php';

// ---------------------------------------------------------------------
// Basic sanity checks
// ---------------------------------------------------------------------
if (!defined('STRIPE_MODE')) {
    error_log('JOEY_WEBHOOK_ERROR: STRIPE_MODE not defined.');
    http_response_code(500);
    echo json_encode(['error' => 'Config error']);
    exit;
}

if (!defined('STRIPE_SECRET_KEY_LIVE') || !defined('STRIPE_SECRET_KEY_TEST')) {
    error_log('JOEY_WEBHOOK_ERROR: Stripe secret keys not defined.');
    http_response_code(500);
    echo json_encode(['error' => 'Config error']);
    exit;
}

if (!defined('STRIPE_WEBHOOK_SECRET_LIVE') || !defined('STRIPE_WEBHOOK_SECRET_TEST')) {
    error_log('JOEY_WEBHOOK_ERROR: Webhook secrets not defined.');
    http_response_code(500);
    echo json_encode(['error' => 'Config error']);
    exit;
}

if (!class_exists('\Stripe\Stripe')) {
    error_log('JOEY_WEBHOOK_ERROR: Stripe\\Stripe class not found. init.php may not be loaded correctly.');
    http_response_code(500);
    echo json_encode(['error' => 'SDK error']);
    exit;
}

// Choose keys based on mode
\Stripe\Stripe::setApiKey(
    STRIPE_MODE === 'live'
        ? STRIPE_SECRET_KEY_LIVE
        : STRIPE_SECRET_KEY_TEST
);

$endpoint_secret = STRIPE_MODE === 'live'
    ? STRIPE_WEBHOOK_SECRET_LIVE
    : STRIPE_WEBHOOK_SECRET_TEST;

// ---------------------------------------------------------------------
// Read & verify the incoming event
// ---------------------------------------------------------------------
$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event      = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch (\UnexpectedValueException $e) {
    // Invalid payload
    error_log('JOEY_WEBHOOK_ERROR: Invalid payload: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    error_log('JOEY_WEBHOOK_ERROR: Invalid signature: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ---------------------------------------------------------------------
// Handle checkout.session.completed
// ---------------------------------------------------------------------
$type = $event->type;

if ($type === 'checkout.session.completed') {
    /** @var \Stripe\Checkout\Session $session */
    $session = $event->data->object;

    if ($session->payment_status !== 'paid') {
        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    $metadata = $session->metadata ?? null;

    if ($metadata && isset($metadata->joey_deal_id, $metadata->student_id)) {
        $deal_id    = (int)$metadata->joey_deal_id;
        $student_id = (int)$metadata->student_id;

        if ($deal_id > 0 && $student_id > 0) {
            try {
                $pdo = getDB(); // from db_connect.php
            } catch (Throwable $e) {
                error_log('JOEY_WEBHOOK_ERROR: DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'DB connection failed']);
                exit;
            }

            handle_joey_deal_payment($pdo, $deal_id, $student_id);
        } else {
            error_log("JOEY_WEBHOOK_ERROR: Invalid deal_id or student_id in metadata.");
        }
    } else {
        // Not a Joey deal – ignore or handle other flows here
        error_log('JOEY_WEBHOOK_INFO: checkout.session.completed without joey_deal_id metadata.');
    }
}

// Always return 200 so events aren’t retried forever
http_response_code(200);
echo json_encode(['received' => true]);
exit;


// =====================================================================
// Helper: Apply a Joey deal after successful payment (using PDO)
// =====================================================================
function handle_joey_deal_payment(PDO $pdo, int $deal_id, int $student_id): void
{
    // Tables
    $deal_table     = 'wp_joey_deals';
    $students_table = 'wp_students'; // adjust if your prefix differs

    // 1) Load deal
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$deal_table} WHERE id = :id");
        $stmt->execute([':id' => $deal_id]);
        $deal = $stmt->fetch(PDO::FETCH_OBJ);
    } catch (Throwable $e) {
        error_log("JOEY_WEBHOOK_ERROR: Failed to load deal {$deal_id}: " . $e->getMessage());
        return;
    }

    if (!$deal) {
        error_log("JOEY_WEBHOOK_ERROR: Deal id {$deal_id} not found.");
        return;
    }

    // 2) Idempotency: skip if already applied
    if (isset($deal->status) && $deal->status === 'applied') {
        error_log("JOEY_WEBHOOK_INFO: Deal id {$deal_id} already applied.");
        return;
    }

    // 3) Mark as paid if not yet
    if (!isset($deal->status) || $deal->status !== 'paid') {
        try {
            $stmt = $pdo->prepare("
                UPDATE {$deal_table}
                SET status = 'paid', paid_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $deal_id]);
        } catch (Throwable $e) {
            error_log("JOEY_WEBHOOK_ERROR: Failed to mark deal {$deal_id} as paid: " . $e->getMessage());
            // continue anyway, so we still attempt to apply hours
        }
    }

    // 4) Compute hours to add (paid + bonus)
    $aircraft_to_add   = (float)$deal->aircraft_hours   + (float)$deal->bonus_aircraft_hours;
    $instructor_to_add = (float)$deal->instructor_hours + (float)$deal->bonus_instructor_hours;

    // 5) Update student hour balances
    // NOTE: adjust column names if they differ. Based on joey_chat.php
    // you likely have aircraft_hours_remaining / instructor_hours_remaining.
    try {
        $stmt = $pdo->prepare("
            UPDATE {$students_table}
            SET aircraft_hours_remaining   = aircraft_hours_remaining   + :a,
                instructor_hours_remaining = instructor_hours_remaining + :i
            WHERE student_id = :sid
        ");
        $stmt->execute([
            ':a'   => $aircraft_to_add,
            ':i'   => $instructor_to_add,
            ':sid' => $student_id,
        ]);
    } catch (Throwable $e) {
        error_log("JOEY_WEBHOOK_ERROR: Failed to update student {$student_id} balances: " . $e->getMessage());
        return;
    }

    // 6) Mark deal as applied
    try {
        $stmt = $pdo->prepare("
            UPDATE {$deal_table}
            SET status = 'applied', applied_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $deal_id]);
    } catch (Throwable $e) {
        error_log("JOEY_WEBHOOK_ERROR: Failed to mark deal {$deal_id} as applied: " . $e->getMessage());
        return;
    }

    error_log("JOEY_WEBHOOK_INFO: Applied deal {$deal_id} to student {$student_id}: +{$aircraft_to_add} aircraft, +{$instructor_to_add} instructor.");
}
