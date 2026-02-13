<?php
session_start();
require_once(__DIR__ . '/includes/db_connect.php');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $phone = $_POST['phone'] ?? '';

  $stmt = $db->prepare("SELECT * FROM wp_maintenance_techs WHERE phone = :phone AND active = 1");
  $stmt->execute([':phone' => $phone]);
  $tech = $stmt->fetch();

  if ($tech) {
    $_SESSION['tech_id'] = $tech['tech_id'];
    $_SESSION['tech_name'] = $tech['full_name'];
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Phone not found or tech inactive']);
  }
  exit;
}
?>


<!DOCTYPE html>
<html>
<head>
  <title>Maintenance Tech Login</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
</head>
<body>
<script>
Swal.fire({
  title: 'Maintenance Tech Login',
  html: `<input id="phone" class="swal2-input" placeholder="(XXX) XXX-XXXX">`,
  didOpen: () => {
    $('#phone').mask('(000) 000-0000');
  },
  confirmButtonText: 'Log In',
  preConfirm: () => {
    const phone = document.getElementById('phone').value;

    if (!phone || phone.length !== 14) {
      Swal.showValidationMessage('Please enter a valid 10-digit phone number.');
      return false;
    }

    return fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ phone })
    })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
        return true;
      } else {
        Swal.showValidationMessage(response.message || 'Login failed');
      }
    });
  }
}).then(result => {
  if (result.isConfirmed) {
    window.location.href = 'aircraft_management.php';
  }
});
</script>


</body>
</html>

