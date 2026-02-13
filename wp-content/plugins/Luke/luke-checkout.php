<?php
// luke-checkout.php
// TEMP: stubbed checkout so Luke flow works without real Stripe.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'invalid_method']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$programSlug = $data['program_slug'] ?? null;

if (!$programSlug) {
    echo json_encode(['ok' => false, 'error' => 'missing_program_slug']);
    exit;
}

// You can change this to any URL you want (Gravity form, payment page, etc.)
$baseUrl = 'https://example.com/placeholder-checkout';

echo json_encode([
    'ok'           => true,
    'checkout_url' => $baseUrl . '?program=' . urlencode($programSlug),
    'stub'         => true
]);
exit;

