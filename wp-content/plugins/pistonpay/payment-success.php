<?php
// /wp-content/plugins/pistonpay/payment-success.php
session_start();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/db_connect.php');

$session_id  = $_GET['session_id'] ?? '';
$embed_done  = isset($_GET['done']);        // set by docusign_embedded.php on return
$embed_error = isset($_GET['embed_error']); // set by docusign_embedded.php on fallback

// Figure out where "Back to Dashboard" should go (your existing logic)
$dashboardLink = '/';
if (!empty($_SESSION['admin_logged_in'])) {
  $dashboardLink = '/wp-content/plugins/checkout/admin-panel.php';
} elseif (!empty($_SESSION['cfi_id'])) {
  $dashboardLink = '/wp-content/plugins/cfipanel/';
}

// If we have a Stripe Checkout session_id and we haven't attempted embedded signing yet,
// queue a quick redirect to DocuSign embedded. (Email is already sent from the webhook.)
$shouldEmbed = $session_id && !$embed_done && !$embed_error;
$embedUrl = $shouldEmbed
  ? '/wp-content/plugins/pistonpay/docusign_embedded.php?session_id=' . urlencode($session_id)
  : '';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Payment Success</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php if ($shouldEmbed): ?>
    <!-- Safety net: meta refresh in case JS is blocked -->
    <meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($embedUrl, ENT_QUOTES) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { background:#f7f7fb; }
    .card { border:0; box-shadow: 0 6px 24px rgba(0,0,0,0.06); }
    .spinner { width: 1.5rem; height: 1.5rem; border: .2rem solid #dee2e6; border-top-color: #198754; border-radius: 50%; animation: spin 1s linear infinite; display:inline-block; vertical-align: middle; }
    @keyframes spin { to { transform: rotate(360deg); } }
    code { user-select: all; }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card p-4 p-md-5 text-center">
          <h1 class="text-success mb-2">✅ Payment Successful!</h1>
          <p class="text-muted mb-4">Thank you—your payment has been processed.</p>

          <?php if ($session_id): ?>
            <p class="small text-muted mb-1">Stripe Session</p>
            <p><code><?= htmlspecialchars($session_id, ENT_QUOTES) ?></code></p>
          <?php endif; ?>

          <?php if ($shouldEmbed): ?>
            <div class="alert alert-info d-flex align-items-center justify-content-center gap-2" role="alert">
              <span class="spinner" aria-hidden="true"></span>
              <span>Redirecting you to contract signing…</span>
            </div>
          <?php elseif ($embed_done): ?>
            <div class="alert alert-success" role="alert">
              Thanks! If you didn’t finish the DocuSign yet, we also emailed you the contract link.
            </div>
          <?php elseif ($embed_error): ?>
            <div class="alert alert-warning" role="alert">
              We emailed your contract via DocuSign. If the signing tab didn’t open, please check your inbox.
            </div>
          <?php else: ?>
            <div class="alert alert-secondary" role="alert">
              A DocuSign email with your contract has been sent to you.
            </div>
          <?php endif; ?>

          <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
            <a href="<?= htmlspecialchars($dashboardLink, ENT_QUOTES) ?>" class="btn btn-primary">⬅️ Back to Dashboard</a>
            <?php if ($session_id): ?>
              <a href="/wp-content/plugins/pistonpay/docusign_embedded.php?session_id=<?= urlencode($session_id) ?>"
                 class="btn btn-outline-success">
                Open DocuSign
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($shouldEmbed): ?>
  <script>
    // JS redirect a touch sooner than the meta refresh
    setTimeout(function () {
      window.location.href = <?= json_encode($embedUrl) ?>;
    }, 800);
  </script>
  <?php endif; ?>
</body>
</html>


