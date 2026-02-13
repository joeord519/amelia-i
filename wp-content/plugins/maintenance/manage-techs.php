<?php
session_start();
require_once(__DIR__ . '/includes/db_connect.php');
$db = getDB();

// Only allow admins
if (!isset($_SESSION['admin_id'])) {
  echo "<p style='padding:20px;'>Access denied. Admin login required.</p>";
  exit;
}

// Handle new tech form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tech'])) {
  $name = $_POST['full_name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $username = $_POST['username'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $qualified_for = $_POST['qualified_for'];

  $stmt = $db->prepare("INSERT INTO wp_maintenance_techs (full_name, email, phone, username, password_hash, qualified_for) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->execute([$name, $email, $phone, $username, $password, $qualified_for]);

  echo "<script>alert('✅ Tech added successfully!'); window.location.href='manage-techs.php';</script>";
  exit;
}

// Fetch all current techs
$techs = $db->query("SELECT * FROM wp_maintenance_techs ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Maintenance Techs</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 30px; background-color: #f9f9f9; }
    h2 { text-align: center; }
    form { background: #fff; padding: 20px; border-radius: 8px; max-width: 500px; margin: 20px auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    input, select { width: 100%; padding: 10px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 40px; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
    .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-danger { background: #dc3545; color: white; }
  </style>
</head>
<body>

<h2>Manage Maintenance Technicians</h2>

<form method="POST">
  <h3>Add New Technician</h3>
  <input type="text" name="full_name" placeholder="Full Name" required>
  <input type="email" name="email" placeholder="Email">
  <input type="text" name="phone" placeholder="Phone (Optional)">
  <input type="text" name="username" placeholder="Username" required>
  <input type="password" name="password" placeholder="Password" required>
  <select name="qualified_for" required>
    <option value="All">All</option>
    <option value="50hr">50 Hour Only</option>
    <option value="100hr">100 Hour Only</option>
    <option value="Annual">Annual Only</option>
    <option value="Avionics">Avionics</option>
    <option value="Engine">Engine</option>
    <option value="Prop">Prop</option>
    <option value="Other">Other</option>
  </select>
  <button class="btn" type="submit" name="add_tech">Add Technician</button>
</form>

<h3>Current Techs</h3>
<table>
  <thead>
    <tr>
      <th>Name</th><th>Email</th><th>Phone</th><th>Username</th><th>Qualified For</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($techs as $tech): ?>
      <tr>
        <td><?= htmlspecialchars($tech['full_name']) ?></td>
        <td><?= htmlspecialchars($tech['email']) ?></td>
        <td><?= htmlspecialchars($tech['phone']) ?></td>
        <td><?= htmlspecialchars($tech['username']) ?></td>
        <td><?= htmlspecialchars($tech['qualified_for']) ?></td>
        <td><?= $tech['active'] ? '✅ Active' : '❌ Inactive' ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
