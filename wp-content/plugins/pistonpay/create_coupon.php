<?php
// pistonpay/create_coupon.php

require_once(__DIR__ . '/db_connect.php'); // or config.php if that's your preferred DB handler

// Optional access restriction
// Uncomment this if this file is accessed through wp-admin
// if (!current_user_can('manage_options')) die('Access Denied');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = $_POST['discount_type'] ?? 'flat';
    $value = floatval($_POST['discount_value'] ?? 0);
    $expires = !empty($_POST['expires_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null;
    $limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;

    if (!$code || !$value || !in_array($type, ['flat', 'percent'])) {
        $error = "Invalid input.";
    } else {
        try {
            $stmt = getDB()->prepare("INSERT INTO wp_coupons (code, discount_type, discount_value, expires_at, usage_limit, is_active)
                                      VALUES (:code, :type, :value, :expires, :limit, 1)");
            $stmt->execute([
                'code' => $code,
                'type' => $type,
                'value' => $value,
                'expires' => $expires,
                'limit' => $limit
            ]);
            $success = "✅ Coupon <strong>$code</strong> created successfully!";
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Coupon Code</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 30px; background: #f4f4f4; }
    form { background: #fff; padding: 20px; border-radius: 6px; max-width: 400px; margin: auto; }
    input, select, button { width: 100%; padding: 8px; margin: 10px 0; }
    h2 { text-align: center; }
    .msg { text-align: center; font-weight: bold; }
    .success { color: green; }
    .error { color: red; }
  </style>
</head>
<body>

<h2>Create New Coupon Code</h2>

<?php if ($success): ?>
  <div class="msg success"><?= $success ?></div>
<?php elseif ($error): ?>
  <div class="msg error"><?= $error ?></div>
<?php endif; ?>

<form method="post">
  <label>Coupon Code:
    <input name="code" required placeholder="e.g. PPL25 or SAVE100">
  </label>

  <label>Discount Type:
    <select name="discount_type" required>
      <option value="flat">$ Flat Discount</option>
      <option value="percent">% Percentage Discount</option>
    </select>
  </label>

  <label>Discount Value:
    <input type="number" name="discount_value" step="0.01" required placeholder="e.g. 20">
  </label>

  <label>Expiration Date (optional):
    <input type="datetime-local" name="expires_at">
  </label>

  <label>Usage Limit (optional):
    <input type="number" name="usage_limit" min="1">
  </label>

  <button type="submit">Create Coupon</button>
</form>

</body>
</html>
