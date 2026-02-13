<?php
require_once(__DIR__ . '/config.php');

header('Content-Type: application/json');

// ======= Branch 1: TIME BUILDING (reservation_ids present) =======
if (!empty($_POST['reservation_ids'])) {
  try {
    $pdo = getDB();

    $lead_id         = (int)($_POST['lead_id'] ?? 0);
    $reservation_ids = array_values(array_filter(array_map('intval', (array)($_POST['reservation_ids']))));

    // Optional: capture student name/email for GF prefill + Stripe receipt
    $student_name  = trim($_POST['student_name']  ?? '');
    $student_email = trim($_POST['student_email'] ?? '');

    if ($lead_id <= 0 || empty($reservation_ids)) {
      echo json_encode(['success' => false, 'message' => 'Missing lead_id or reservation_ids']);
      exit;
    }

    // How many weeks did they select?
    $weeks = count($reservation_ids);

    // TEST price: $0.50 per week (change when ready)
    $unit_amount_cents = 495000;

    // Real display price (for the Gravity form): $4,950 per week
    $display_price_per_week = 4950;
    $display_total_usd      = $weeks * $display_price_per_week;

    // ---- Build a human-readable “weeks scheduled” string from reservation_ids
    // Example format: "Sep 14–Sep 20, Sep 21–Sep 27"
    $weeksText = '';
    if ($weeks > 0) {
      $in  = implode(',', array_fill(0, count($reservation_ids), '?'));
      $sql = "
        SELECT DISTINCT w.week_start, w.week_end
        FROM wp_tb_weeks w
        JOIN wp_tb_reservations r ON r.week_id = w.id
        WHERE r.id IN ($in)
        ORDER BY w.week_start ASC
      ";
      $q = $pdo->prepare($sql);
      $q->execute($reservation_ids);
      $rows = $q->fetchAll(PDO::FETCH_ASSOC);

      $fmt = function(string $d): string {
        $ts = strtotime($d);
        return $ts ? date('M j', $ts) : $d;
      };
      $ranges = array_map(function ($r) use ($fmt) {
        return $fmt($r['week_start']) . '–' . $fmt($r['week_end']);
      }, $rows ?: []);

      $weeksText = implode(', ', $ranges);
    }

    // ---- Build Stripe line item: single product, quantity = number of weeks
    $line_items = [[
      'price_data' => [
        'currency'     => 'usd',
        'product_data' => [
          'name'        => 'Time Building (Weekly Reservation)',
          'description' => $weeks . ' week(s) at $4,950 each',
        ],
        'unit_amount'  => $unit_amount_cents, // cents
      ],
      'quantity' => max(1, $weeks),
    ]];

    // ---- Gravity Forms success URL with prefilled params
    // stnm = Student Name, em = Email, tas = Total Amount Spent (USD), wesc = Weeks Scheduled
    $gfParams = http_build_query([
      'stnm' => $student_name,
      'em'   => $student_email,
      'tas'  => $display_total_usd,
      'wesc' => $weeksText,
      // Keep the Stripe session id for reference on SkyListPro if you want
      'sid'  => '{CHECKOUT_SESSION_ID}',
    ], '', '&', PHP_QUERY_RFC3986);

    $success_url = 'https://skylistpro.com/piston-aviation-time-building/?' . $gfParams;
    $cancel_url  = 'https://amelia-i.com/payment-cancelled';

    // ---- Create checkout session
    $session = \Stripe\Checkout\Session::create([
      'payment_method_types' => ['card'],
      'mode'                 => 'payment',
      'line_items'           => $line_items,
      // Use customer_email if you collected it so Stripe emails a receipt
      'customer_email'       => $student_email ?: null,
      // Keep useful data in metadata as a backup / audit log
      'metadata' => [
        'type'             => 'time_building',
        'lead_id'          => (string)$lead_id,
        'reservation_ids'  => implode(',', $reservation_ids),
        'weeks_text'       => $weeksText,
        'amount_usd'       => $display_total_usd,
        'student_name'     => $student_name,
        'student_email'    => $student_email,
      ],
      'success_url' => $success_url,
      'cancel_url'  => $cancel_url,
    ]);

    echo json_encode(['success' => true, 'url' => $session->url]);
    exit;

  } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
  }
}

// ======= Branch 2: EXISTING STUDENT HOURS FLOW (unchanged behavior) =======

// Collect and sanitize input
$student_id       = (int) ($_POST['student_id'] ?? 0);
$student_email    = trim($_POST['email'] ?? '');
$aircraft_hours   = (float)($_POST['aircraft_hours'] ?? 0);
$instructor_hours = (float)($_POST['instructor_hours'] ?? 0);
$coupon_code      = $_POST['coupon_code'] ?? null;

if ($student_id <= 0 || empty($student_email) || ($aircraft_hours + $instructor_hours) <= 0) {
  echo json_encode(['success' => false, 'message' => 'Missing or invalid input.']);
  exit;
}

// Fetch current rates from wp_company_settings
$rateMap = ['aircraft_hourly_rate' => 0, 'instructor_hourly_rate' => 0];
$stmt = getDB()->query("
  SELECT setting_key, setting_value
  FROM wp_company_settings
  WHERE setting_key IN ('aircraft_hourly_rate','instructor_hourly_rate')
");
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $val) {
  $rateMap[$key] = (float)$val;
}

// Debug logging (kept from your file)
error_log('📦 RATES PULLED:');
error_log('✈ Aircraft Rate: '  . $rateMap['aircraft_hourly_rate']);
error_log('👨‍🏫 Instructor Rate: ' . $rateMap['instructor_hourly_rate']);

$aircraft_rate     = $rateMap['aircraft_hourly_rate'];
$instructor_rate   = $rateMap['instructor_hourly_rate'];

$aircraft_subtotal   = $aircraft_hours   * $aircraft_rate;
$instructor_subtotal = $instructor_hours * $instructor_rate;
$original_total      = $aircraft_subtotal + $instructor_subtotal;
$total               = $original_total; // start with full price

// Coupon logic (DB-driven)
if (!empty($coupon_code)) {
  $stmt = getDB()->prepare("SELECT * FROM wp_coupons WHERE code = :code AND is_active = 1");
  $stmt->execute(['code' => $coupon_code]);
  $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($coupon) {
    error_log('🎟 Coupon Found: ' . $coupon['code']);
    error_log('📊 Discount Type Raw: ' . $coupon['discount_type']);
    error_log('📉 Discount Value: ' . $coupon['discount_value']);

    $isExpired = $coupon['expires_at'] && strtotime($coupon['expires_at']) < time();
    $isUsedUp  = $coupon['usage_limit'] && $coupon['times_used'] >= $coupon['usage_limit'];

    if (!$isExpired && !$isUsedUp) {
      $discount_value = (float)$coupon['discount_value'];
      $discount_type  = strtolower(trim($coupon['discount_type']));

      if ($discount_type === 'flat') {
        $discounted_total   = max(0, $original_total - $discount_value);
        $aircraft_ratio     = $original_total > 0 ? ($aircraft_subtotal / $original_total)   : 0;
        $instructor_ratio   = $original_total > 0 ? ($instructor_subtotal / $original_total) : 0;
        $aircraft_subtotal   = $discounted_total * $aircraft_ratio;
        $instructor_subtotal = $discounted_total * $instructor_ratio;
        $total               = $aircraft_subtotal + $instructor_subtotal;

        error_log("💲 Flat Discount Applied - New Aircraft Subtotal: $aircraft_subtotal");
        error_log("💲 Flat Discount Applied - New Instructor Subtotal: $instructor_subtotal");

      } elseif ($discount_type === 'percent') {
        error_log("💲 Aircraft Before %: $aircraft_subtotal");
        $aircraft_subtotal   -= ($aircraft_subtotal   * $discount_value / 100);
        error_log("💲 Aircraft After %: $aircraft_subtotal");
        error_log("💲 Instructor Before %: $instructor_subtotal");
        $instructor_subtotal -= ($instructor_subtotal * $discount_value / 100);
        error_log("💲 Instructor After %: $instructor_subtotal");
        $total = $aircraft_subtotal + $instructor_subtotal;
      }

      // Final safety checks
      if ($aircraft_subtotal   < 0) $aircraft_subtotal   = 0;
      if ($instructor_subtotal < 0) $instructor_subtotal = 0;
      if ($total               < 0) $total               = 0;

      error_log("✅ Final Total After Discount: $total");
    } else {
      error_log('⚠️ Coupon invalid due to expiration or usage limit');
    }
  } else {
    error_log('❌ Coupon not found or inactive: ' . $coupon_code);
  }
}

try {
  $line_items = [];

  if ($aircraft_hours > 0) {
    $line_items[] = [
      'price_data' => [
        'currency'     => 'usd',
        'product_data' => ['name' => "{$aircraft_hours} Aircraft Hour(s)"],
        'unit_amount'  => (int)round($aircraft_subtotal * 100), // cents
      ],
      'quantity' => 1,
    ];
  }

  if ($instructor_hours > 0) {
    $line_items[] = [
      'price_data' => [
        'currency'     => 'usd',
        'product_data' => ['name' => "{$instructor_hours} Instructor Hour(s)"],
        'unit_amount'  => (int)round($instructor_subtotal * 100), // cents
      ],
      'quantity' => 1,
    ];
  }

  $session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'customer_email'       => $student_email,
    'line_items'           => $line_items,
    'mode'                 => 'payment',
    'metadata' => [
      'student_id'        => $student_id,
      'aircraft_hours'    => $aircraft_hours,
      'instructor_hours'  => $instructor_hours,
      'coupon_code'       => $coupon_code,
      'student_email'     => $student_email,
    ],
    'success_url' => 'https://amelia-i.com/wp-content/plugins/pistonpay/payment-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => 'https://amelia-i.com/payment-cancelled',
  ]);

  echo json_encode(['success' => true, 'url' => $session->url]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

