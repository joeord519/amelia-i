<?php
// This is the main entry point for the checkout system
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Flight Check-Out</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Bangers Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Bangers&display=swap" rel="stylesheet">

  <!-- Custom SweetAlert Theme -->
  <link rel="stylesheet" href="/wp-content/plugins/checkout/swal-theme.css">

  <!-- Checkout Flow (type=module) -->
 <script type="module" src="./checkout-flow.js"></script>

  <script>console.log("✅ index.php loaded");</script>
</head>
<body style="background:#f4f4f4; display:flex; justify-content:center; align-items:center; height:100vh;">
  <h1 style="font-family:Arial; color:#aaa;">✈️ PistonOps Check-Out</h1>
</body>
</html>
