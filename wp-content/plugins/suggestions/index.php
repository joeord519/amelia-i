<?php
require_once(__DIR__ . '/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db = getDB();

  $stmt = $db->prepare("
    INSERT INTO wp_suggestions (category, suggestion, name, email, phone, submitted_at)
    VALUES (?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    $_POST['category'] ?? '',
    $_POST['suggestion'] ?? '',
    $_POST['name'] ?? '',
    $_POST['email'] ?? '',
    $_POST['phone'] ?? '',
  ]);

  echo "<p>✅ Suggestion submitted. Thank you!</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Suggestion Box</title>
  <style>
    body { font-family: sans-serif; padding: 40px; }
    form { max-width: 500px; margin: auto; display: flex; flex-direction: column; gap: 12px; }
    input, textarea, select { padding: 10px; border-radius: 6px; border: 1px solid #ccc; }
    button { background-color: #0073aa; color: white; padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; }
  </style>
</head>
<body>

<h2>🗳️ Suggestion Box</h2>
<form method="POST">
  <label>Category</label>
  <select name="category">
    <option value="Calendar">Calendar</option>
    <option value="Scheduler">Scheduler</option>
  </select>

  <label>Your Suggestion</label>
  <textarea name="suggestion" required></textarea>

  <label>Your Name (optional)</label>
  <input type="text" name="name">

  <label>Your Email (optional)</label>
  <input type="email" name="email">

  <label>Your Phone (optional)</label>
  <input type="text" name="phone">

  <button type="submit">Submit</button>
</form>

</body>
</html>
