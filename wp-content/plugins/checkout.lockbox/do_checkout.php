$studentId   = $_POST['student_id'] ?? null;
$aircraftId  = $_POST['aircraft_id'] ?? null;
$startHobbs  = $_POST['start_hobbs'] ?? null;
$startTach   = $_POST['start_tach'] ?? null;
$description = $_POST['flight_type'] ?? 'Not Specified';

if (!$studentId || !$aircraftId || !$startHobbs || !$startTach) {
  exit("❌ Missing required fields.");
}

try {
  $db = getDB();

  $insert = $db->prepare("
    INSERT INTO wp_flight_checkouts (student_id, aircraft_id, start_hobbs, start_tach, checkout_time, description)
    VALUES (:sid, :aid, :hobbs, :tach, NOW(), :desc)
  ");
  $insert->execute([
    ':sid'   => $studentId,
    ':aid'   => $aircraftId,
    ':hobbs' => $startHobbs,
    ':tach'  => $startTach,
    ':desc'  => $description
  ]);

  echo "<h2>✅ You're checked out!</h2>
        <p>Have a great flight. Don't forget to check back in when you're done.</p>";

} catch (Exception $e) {
  echo "❌ Error during checkout: " . $e->getMessage();
}

