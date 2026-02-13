<?php
// /wp-content/plugins/accelerated/generate_stripe_link.php

require_once('config.php'); // Loads Stripe and DB connection
require_once('../../../../wp-load.php'); // Optional: Only needed if you're using WP time functions

header('Content-Type: application/json');

$lead_id = intval($_POST['lead_id'] ?? 0);

if (!$lead_id) {
  echo json_encode(['status' => 'error', 'message' => 'Missing lead ID']);
  exit;
}

try {
  $pdo = getDB();

  // Fetch lead info
  $stmt = $pdo->prepare("SELECT * FROM wp_leads WHERE lead_id = ?");
  $stmt->execute([$lead_id]);
  $lead = $stmt->fetch();

  if (!$lead) {
    echo json_encode(['status' => 'error', 'message' => 'Lead not found']);
    exit;
  }

  $full_name = $lead['full_name'];
  $email = $lead['email'];
  $housing = $lead['housing_choice'] ?? 'Shared';

  // Calculate amount
  $base_price = 500000; // $5,000 in cents
  $solo_upcharge = ($housing === 'Solo') ? 150000 : 0; // $1,500
  $total_amount = $base_price + $solo_upcharge;

  // Create Stripe Checkout Session
  $checkout_session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
      'price_data' => [
        'currency' => 'usd',
        'product_data' => [
          'name' => 'Accelerated Private Pilot Program Deposit',
          'description' => ($housing === 'Solo') ? 'Includes solo housing upgrade' : 'Shared housing',
        ],
        'unit_amount' => $total_amount,
      ],
      'quantity' => 1,
    ]],
    'mode' => 'payment',
    'customer_email' => $email,
    'metadata' => [
      'lead_id' => $lead_id,
      'housing' => $housing
    ],
    'success_url' => 'https://flypiston.com/thank-you/?lead_id=' . $lead_id,
    'cancel_url' => 'https://flypiston.com/registration-canceled/',
  ]);

  // Save Stripe Checkout session ID
  $stmt = $pdo->prepare("UPDATE wp_leads SET stripe_checkout_id = ? WHERE lead_id = ?");
  $stmt->execute([$checkout_session->id, $lead_id]);

  echo json_encode([
    'status' => 'success',
    'checkout_url' => $checkout_session->url
  ]);

} catch (Exception $e) {
  echo json_encode([
    'status' => 'error',
    'message' => 'Stripe error: ' . $e->getMessage()
  ]);
}
