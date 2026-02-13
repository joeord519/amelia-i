<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Login</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f8;
    }
  </style>
</head>
<body>

<script>
const url = new URL(window.location.href);
const loggedOut = url.searchParams.get('loggedout');
const timeout = url.searchParams.get('timeout');

if (loggedOut) {
  Swal.fire("✅ Logged Out", "You have been successfully logged out.", "info");
}
if (timeout) {
  Swal.fire("⏰ Session Expired", "You were logged out after 25 minutes of inactivity.", "warning");
}

Swal.fire({
  title: "Admin Login",
  html: `
    <input id="adminPhone" class="swal2-input" placeholder="(XXX) XXX-XXXX">
    <input id="adminPass" type="password" class="swal2-input" placeholder="Password">
  `,
  confirmButtonText: "Login",
  allowOutsideClick: false,
  didOpen: () => {
    const input = document.getElementById("adminPhone");
    input.addEventListener("input", function () {
      let x = this.value.replace(/\D/g, '');
      this.value = x.length > 6
        ? `(${x.slice(0,3)}) ${x.slice(3,6)}-${x.slice(6,10)}`
        : x.length > 3
        ? `(${x.slice(0,3)}) ${x.slice(3)}`
        : x.length > 0
        ? `(${x}`
        : '';
    });
  },
  preConfirm: () => {
    const phone = document.getElementById("adminPhone").value.trim();
    const pass = document.getElementById("adminPass").value;
    if (!phone || !pass) {
      Swal.showValidationMessage("Both fields are required.");
      return false;
    }
    return { phone, pass };
  }
}).then(result => {
  if (result.isConfirmed) {
    fetch("admin-login-handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(result.value)
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        Swal.fire("✅ Welcome!", "Login successful.", "success").then(() => {
          window.location.href = "admin-panel.php";
        });
      } else {
        Swal.fire("⛔ Access Denied", data.message || "Invalid credentials.", "error")
          .then(() => location.reload());
      }
    });
  }
});
</script>

</body>
</html>
