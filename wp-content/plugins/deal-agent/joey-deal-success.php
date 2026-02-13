<?php
// joey-deal-success.php – simple standalone success page for Joey Deal Agent
// No WordPress needed.

ini_set('display_errors', 0);
error_reporting(0);

$session_id = isset($_GET['session_id']) ? htmlspecialchars($_GET['session_id']) : '';
// You don't have to use $session_id, but it's available if you ever want it.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Successful – Joey Deal Agent</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f3f4f6;
      color: #111827;
    }
    .wrapper {
      max-width: 800px;
      margin: 40px auto;
      padding: 30px;
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .pill {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 999px;
      background: #00388310;
      color: #003883;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 16px;
    }
    h1 {
      font-size: 30px;
      margin: 8px 0 10px;
      text-align: center;
    }
    p {
      font-size: 15px;
      line-height: 1.6;
      color: #4b5563;
    }
    .center {
      text-align: center;
    }
    .btn-primary {
      display: inline-block;
      padding: 12px 28px;
      border-radius: 999px;
      background: #00bf63;
      color: #ffffff;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      box-shadow: 0 10px 20px rgba(16, 185, 129, 0.35);
      margin-top: 18px;
    }
    .btn-primary:hover {
      background: #00a754;
    }
    .small {
      font-size: 13px;
      color: #9ca3af;
      margin-top: 16px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="center">
      <div class="pill">
        Joey Deal Agent &mdash; Payment Confirmed
      </div>
    </div>

    <h1>You’re all set &mdash; your hours are being added</h1>

    <p class="center">
      We’ve received your payment for this flight hours package. Your aircraft and instructor time
      are being added to your student account right now.
    </p>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

    <p><strong>What happens next?</strong></p>
    <ul style="padding-left:22px;margin-top:6px;margin-bottom:18px;color:#374151;">
      <li>Your hour balances are being updated in the system.</li>
      <li>You’ll see your new totals on your account page.</li>
      <li>You’ll also receive a receipt at the email you used at checkout.</li>
    </ul>

    <p>
      If anything looks off with your hours, or you have questions about this deal,
      just reach out to the front desk and we’ll help you out.
    </p>

    <div class="center">
      <!-- TODO: update href to your real account page URL -->
      <a href="https://amelia-i.com/wp-content/plugins/calendar.v2/appointment-booking/index.html" class="btn-primary">
        Go back to my account
      </a>
    </div>

    <p class="small">
      If your balance doesn’t update immediately, give it a few seconds and refresh this page.
    </p>
  </div>
</body>
</html>
