<?php
// create_deal_checkout.php – create Stripe Checkout Session for a Joey deal
// Stand-alone script: uses db_connect.php (PDO) and stripe-php, no wp-load.

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-content/secure-config/stripe-config.php';
require_once __DIR__ . '/stripe-php/init.php';   // stripe-php in this plugin folder

// ---------------------------------------------------------------------
// Quick sanity checks so we don't hit a hard fatal
// ---------------------------------------------------------------------
if (!defined('STRIPE_MODE')) {
    error_log('JOEY_STRIPE_ERROR: STRIPE_MODE is not defined.');
    echo json_encode(['success' => false, 'error' => 'Stripe configuration missing (mode).']);
    exit;
}

if (!defined('STRIPE_SECRET_KEY_LIVE') || !defined('STRIPE_SECRET_KEY_TEST')) {
    error_log('JOEY_STRIPE_ERROR: Stripe secret keys are not defined.');
    echo json_encode(['success' => false, 'error' => 'Stripe configuration missing (keys).']);
    exit;
}

if (!class_exists('\Stripe\Stripe')) {
    error_log('JOEY_STRIPE_ERROR: Stripe\\Stripe class not found. init.php may not be loaded correctly.');
    echo json_encode(['success' => false, 'error' => 'Stripe library not loaded.']);
    exit;
}

// Choose the correct Stripe key
\Stripe\Stripe::setApiKey(
    STRIPE_MODE === 'live'
        ? STRIPE_SECRET_KEY_LIVE
        : STRIPE_SECRET_KEY_TEST
);

// ---------------------------------------------------------------------
// Read JSON input
// ---------------------------------------------------------------------
$inputRaw = file_get_contents('php://input');
$input    = json_decode($inputRaw, true);

if (!is_array($input)) {
    error_log('JOEY_STRIPE_ERROR: Invalid JSON body: ' . $inputRaw);
    echo json_encode(['success' => false, 'error' => 'No or invalid JSON body']);
    exit;
}

$student_id               = (int)($input['student_id'] ?? 0);
$latest_deal              = $input['latest_deal'] ?? null;
$latest_deal_price_string = $input['latest_deal_price_string'] ?? null;

if (!$student_id || !$latest_deal || !$latest_deal_price_string) {
    error_log('JOEY_STRIPE_ERROR: Missing student or deal data. student_id=' . $student_id);
    echo json_encode(['success' => false, 'error' => 'Missing student or deal data']);
    exit;
}

// ---------------------------------------------------------------------
// Extract hours + bonuses
// ---------------------------------------------------------------------
$aircraft_hours   = (float)$latest_deal['aircraft_hours'];
$instructor_hours = (float)$latest_deal['instructor_hours'];
$bonus_aircraft   = (float)($latest_deal['totals']['bonus_aircraft_hours'] ?? 0);
$bonus_instructor = (float)($latest_deal['totals']['bonus_instructor_hours'] ?? 0);

// Convert "$5,478.80" -> 547880 (cents)
$numeric           = preg_replace('/[^\d\.]/', '', $latest_deal_price_string);
$total_price_cents = (int)round(((float)$numeric) * 100);

// ---------------------------------------------------------------------
// DB connection via PDO
// ---------------------------------------------------------------------
try {
    $pdo = getDB(); // from db_connect.php
} catch (Throwable $e) {
    error_log('JOEY_STRIPE_ERROR: DB connection failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$deal_table = 'wp_joey_deals'; // your table name is exactly wp_joey_deals

// ---------------------------------------------------------------------
// Insert deal record (PDO)
// ---------------------------------------------------------------------
try {
    $sql = "
        INSERT INTO {$deal_table}
        (student_id, aircraft_hours, instructor_hours,
         bonus_aircraft_hours, bonus_instructor_hours,
         total_price_cents, latest_deal_json, status, created_at)
        VALUES
        (:student_id, :aircraft_hours, :instructor_hours,
         :bonus_aircraft_hours, :bonus_instructor_hours,
         :total_price_cents, :latest_deal_json, 'pending', NOW())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':student_id'             => $student_id,
        ':aircraft_hours'         => $aircraft_hours,
        ':instructor_hours'       => $instructor_hours,
        ':bonus_aircraft_hours'   => $bonus_aircraft,
        ':bonus_instructor_hours' => $bonus_instructor,
        ':total_price_cents'      => $total_price_cents,
        ':latest_deal_json'       => json_encode($latest_deal),
    ]);
    $deal_id = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('JOEY_STRIPE_ERROR: Could not create deal record: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Could not create deal record',
    ]);
    exit;
}

// ---------------------------------------------------------------------
// Create Stripe Checkout Session
// ---------------------------------------------------------------------
$domain = (STRIPE_MODE === 'live')
    ? 'https://amelia-i.com'   // live domain
    : 'https://amelia-i.com';   // dev / test domain – adjust if needed

try {
    $session = \Stripe\Checkout\Session::create([
    'mode'        => 'payment',
    'success_url' => $domain . '/wp-content/plugins/deal-agent/joey-deal-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => $domain . '/wp-content/plugins/deal-agent/joey-deal-success.php?status=cancelled',
        'line_items'  => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'usd',
                'unit_amount'  => $total_price_cents,
                'product_data' => [
                    'name' => "Flight hours package {$aircraft_hours}/{$instructor_hours}",
                ],
            ],
        ]],
        'metadata' => [
            'joey_deal_id' => $deal_id,
            'student_id'   => $student_id,
        ],
    ]);
} catch (Exception $e) {
    error_log('JOEY_STRIPE_ERROR: Exception creating Checkout Session: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// ---------------------------------------------------------------------
// Save Stripe session id (PDO)
// ---------------------------------------------------------------------
try {
    $sqlUpdate = "
        UPDATE {$deal_table}
        SET stripe_session_id = :session_id
        WHERE id = :deal_id
    ";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':session_id' => $session->id,
        ':deal_id'    => $deal_id,
    ]);
} catch (Throwable $e) {
    error_log('JOEY_STRIPE_ERROR: Could not update deal with session id: ' . $e->getMessage());
    // Non-fatal for the user; continue
}

echo json_encode([
    'success'      => true,
    'checkout_url' => $session->url,
    'deal_id'      => $deal_id,
]);
exit;
