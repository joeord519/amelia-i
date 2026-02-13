<?php
$currentPage = $_SERVER['SCRIPT_NAME'];

$isCRM = strpos($currentPage, 'dashboard.php') !== false
      || strpos($currentPage, 'flow-builder.php') !== false
      || strpos($currentPage, 'step-builder.php') !== false;

$isAI = strpos($currentPage, 'admin-training.php') !== false
     || strpos($currentPage, 'test-conversation.php') !== false
     || strpos($currentPage, 'unanswered-review.php') !== false;
?>

<div class="col-md-3">
  <div class="list-group mb-4 shadow-sm">

    <a href="/wp-content/plugins/checkout/admin-panel.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'admin-panel.php') !== false ? 'active' : '' ?>">🏠 Admin Dashboard</a>

    <!-- Lead CRM Dashboard -->
    <div class="d-flex justify-content-between align-items-center list-group-item <?= $isCRM ? 'bg-primary text-white' : '' ?>">
      <a href="/wp-content/plugins/piston-crm/dashboard.php" class="<?= $isCRM ? 'text-white' : 'text-dark' ?>" style="text-decoration: none; flex: 1;">
        🚀 Lead CRM Dashboard
      </a>
      <button class="btn btn-sm <?= $isCRM ? 'text-white' : 'text-secondary' ?>" data-bs-toggle="collapse" data-bs-target="#crmSubMenu" aria-expanded="<?= $isCRM ? 'true' : 'false' ?>" style="border: none; background: none;">
        <i class="bi <?= $isCRM ? 'bi-chevron-down' : 'bi-chevron-right' ?>"></i>
      </button>
    </div>

    <div class="collapse <?= $isCRM ? 'show' : '' ?>" id="crmSubMenu">
      <a href="/wp-content/plugins/piston-crm/flow-builder.php" 
         class="list-group-item list-group-item-action ps-5 <?= strpos($currentPage, 'flow-builder.php') !== false ? 'active' : '' ?>">
         🔁 Flow Builder
      </a>
      <a href="/wp-content/plugins/piston-crm/step-builder.php" 
         class="list-group-item list-group-item-action ps-5 <?= strpos($currentPage, 'step-builder.php') !== false ? 'active' : '' ?>">
         🧩 Step Builder
      </a>
    </div>

    <a href="/wp-content/plugins/checkout/company-settings.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'company-settings.php') !== false ? 'active' : '' ?>">🛠 Company Settings</a>
    <a href="/wp-content/plugins/checkout/admin-student-management.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'admin-student-management.php') !== false ? 'active' : '' ?>">🎓 Students</a>
    <a href="/wp-content/plugins/checkout/manage-cfis.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'manage-cfis.php') !== false ? 'active' : '' ?>">🧑‍🏫 CFIs</a>
    <a href="/wp-content/plugins/checkout/manage-wings.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'manage-wings.php') !== false ? 'active' : '' ?>">🪶 Manage Wings</a>
    <a href="/wp-content/plugins/calendar.v2/manage-aircraft.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'manage-aircraft.php') !== false ? 'active' : '' ?>">✈️ Aircraft</a>
    <a href="/wp-content/plugins/checkout/payroll-center.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'payroll-center.php') !== false ? 'active' : '' ?>">💰 CFI Payroll Console</a>
    <a href="/wp-content/plugins/checkout/flight-schedule.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'flight-schedule.php') !== false ? 'active' : '' ?>">🗓️ Schedule</a>

    <!-- AI Training -->
    <div class="d-flex justify-content-between align-items-center list-group-item <?= $isAI ? 'bg-primary text-white' : '' ?>">
      <a href="/wp-content/plugins/checkout/admin-training.php" class="<?= $isAI ? 'text-white' : 'text-dark' ?>" style="text-decoration: none; flex: 1;">
        🤖 AI Training
      </a>
      <button class="btn btn-sm <?= $isAI ? 'text-white' : 'text-secondary' ?>" data-bs-toggle="collapse" data-bs-target="#aiSubMenu" aria-expanded="<?= $isAI ? 'true' : 'false' ?>" style="border: none; background: none;">
        <i class="bi <?= $isAI ? 'bi-chevron-down' : 'bi-chevron-right' ?>"></i>
      </button>
    </div>

    <div class="collapse <?= $isAI ? 'show' : '' ?>" id="aiSubMenu">
      <a href="/wp-content/plugins/checkout/test-conversation.php" 
         class="list-group-item list-group-item-action ps-5 <?= strpos($currentPage, 'test-conversation.php') !== false ? 'active' : '' ?>">
         🧪 Test Conversation
      </a>
      <a href="/wp-content/plugins/checkout/unanswered-review.php" 
         class="list-group-item list-group-item-action ps-5 <?= strpos($currentPage, 'unanswered-review.php') !== false ? 'active' : '' ?>">
         ❓ Unanswered Review
      </a>
    </div>

    <a href="/wp-content/plugins/checkout/billing.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'billing.php') !== false ? 'active' : '' ?>">💳 Billing</a>
    <a href="/wp-content/plugins/checkout/admin-audit-logs.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'admin-audit-logs.php') !== false ? 'active' : '' ?>">📋 Logs</a>
    <a href="/wp-content/plugins/checkout/email-log.php" class="list-group-item list-group-item-action <?= strpos($currentPage, 'email-log.php') !== false ? 'active' : '' ?>">📬 Email Log</a>
    <a href="https://amelia-i.com/wp-content/plugins/pistonpay/create_coupon.php" class="list-group-item list-group-item-action">🎟️ Create Coupon Code</a>
    <a href="/wp-content/plugins/checkout/logout.php" class="list-group-item list-group-item-action text-danger">🚪 Logout</a>
  </div>
</div>



