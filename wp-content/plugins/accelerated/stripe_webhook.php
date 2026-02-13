<?php
// /wp-content/plugins/accelerated/stripe_webhook.php

require_once('config.php');
header('Content-Type: application/json');

// Get the raw payload and signature
$payload = @file_get_contents("php://input");
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$endpoint_secret = 'whsec_MK7ZdEsjAmAjqZ4Zy9ti3DKP5MK0nh54'; // Replace with your actual webhook secret

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit; // Invalid payload
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit; // Invalid signature
}

// Only handle successful payments
if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $checkout_id = $session->id;
    $lead_id = $session->metadata->lead_id ?? null;

    if ($lead_id && $checkout_id) {
        try {
            $pdo = getDB();

            $stmt = $pdo->prepare("UPDATE wp_leads SET deposit_paid = 1 WHERE lead_id = ? AND stripe_checkout_id = ?");
            $stmt->execute([$lead_id, $checkout_id]);

            // Optional: Send confirmation email or SMS here

            http_response_code(200);
            echo json_encode(['status' => 'success']);
            exit;

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            http_response_code(500);
            exit;
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ignored']);
