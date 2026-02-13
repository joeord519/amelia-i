<?php
require_once('../db_connect.php'); // Make sure this sets up $wpdb properly
header('Content-Type: application/json');

// Auto-split full name into first and last
$full_name = trim($_POST['name']);
$first_name = '';
$last_name = '';
if (strpos($full_name, ' ') !== false) {
  $name_parts = explode(' ', $full_name, 2);
  $first_name = sanitize_text_field($name_parts[0]);
  $last_name = sanitize_text_field($name_parts[1]);
} else {
  $first_name = sanitize_text_field($full_name);
  $last_name = '';
}

// Sanitize and prepare data
$data = [
  'first_name'       => $first_name,
  'last_name'        => $last_name,
  'email'            => sanitize_email($_POST['email']),
  'phone'            => sanitize_text_field($_POST['phone']),
  'training_program' => 'Accelerated PPL',
  'down_payment'     => 5000.00,
  'payment_plan'     => '', // will be filled after payment selection
  'monthly_payment'  => 0.00,
  'prepaid_hours'    => 0.00,
  'throttled_hours'  => 0.00,
  'cosigner'         => 'No',
  'pdf_file'         => null,
  'lead_source'      => sanitize_text_field($_POST['utm_source']),
  'utm_medium'       => sanitize_text_field($_POST['utm_medium']),
  'utm_campaign'     => sanitize_text_field($_POST['utm_campaign']),
  'selected_month'   => sanitize_text_field($_POST['class_date']),
  'housing_choice'   => ucfirst(sanitize_text_field($_POST['housing'])),
  'deposit_paid'     => 0,
  'stripe_checkout_id' => null,
  'created_at'       => current_time('mysql'),
  'notes'            => 'Headset: ' . sanitize_text_field($_POST['headset']),
  'estimated_total'  => floatval($_POST['total_price'])
];

// Insert lead
$inserted = $wpdb->insert($wpdb->prefix . 'leads', $data);

echo json_encode(['status' => $inserted ? 'success' : 'error']);


