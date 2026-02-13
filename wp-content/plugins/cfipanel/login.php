<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once(__DIR__ . '/db_connect.php');
  session_start();

  $email = trim($_POST['email'] ?? '');
  $db = getDB();

  $stmt = $db->prepare("SELECT cfi_id, first_name FROM wp_cfis WHERE email = :email");
  $stmt->execute([':email' => $email]);
  $cfi = $stmt->fetch();

  if ($cfi) {
    $_SESSION['cfi_id'] = $cfi['cfi_id'];
    $_SESSION['cfi_name'] = $cfi['first_name'];
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'CFI not found']);
  }
  exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>CFI Login</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<script>
Swal.fire({
  title: 'CFI Panel Login',
  input: 'email',
  inputLabel: 'Enter your email to log in',
  inputPlaceholder: 'you@example.com',
  confirmButtonText: 'Log In',
  allowOutsideClick: false,
  inputValidator: (value) => {
    if (!value) {
      return 'Email is required';
    }
  },
  preConfirm: (email) => {
    return $.post('login.php', { email: email })
      .then(response => {
        try {
          const res = JSON.parse(response);
          if (res.status === 'success') {
            window.location.reload();
          } else {
            Swal.showValidationMessage(res.message || 'Login failed');
          }
        } catch (err) {
          console.error("Error parsing response:", response);
          Swal.showValidationMessage("Unexpected server response");
        }
      });
  }
});
</script>
</body>
</html>


