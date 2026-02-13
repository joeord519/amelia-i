<?php
require_once 'db_connect.php';

// Fetch home airports
try {
    $conn = getDB();
    $locationStmt = $conn->prepare("SELECT airport_code, name FROM wp_locations ORDER BY name ASC");
    $locationStmt->execute();
    $locations = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

    $cfiStmt = $conn->prepare("SELECT cfi_id, CONCAT(first_name, ' ', last_name) AS cfi_name FROM wp_cfis ORDER BY last_name ASC");
    $cfiStmt->execute();
    $cfis = $cfiStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $homeAirport = $_POST['home_airport'] ?? '';
    $aircraftHours = $_POST['aircraft_hours_remaining'] ?? 0;
    $instructorHours = $_POST['instructor_hours_remaining'] ?? 0;
    $assignedCfi = $_POST['assigned_cfi_id'] ?? '';

    try {
        $insertStmt = $conn->prepare("INSERT INTO wp_students 
            (first_name, last_name, email, phone, home_airport, aircraft_hours_remaining, instructor_hours_remaining, assigned_cfi_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([
            $firstName, $lastName, $email, $phone, $homeAirport,
            $aircraftHours, $instructorHours, $assignedCfi
        ]);

        header('Location: admin-student-management.php?toast=student_added');
        exit;
    } catch (PDOException $e) {
        die("Insert error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Student | Piston Aviation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="admin-student-management.php" class="btn btn-outline-secondary">← Back to Student Management</a>
    </div>

    <h2 class="mb-4 text-center">➕ Add New Student</h2>

    <form method="POST">
        <div class="form-group mb-3">
            <label>First Name</label>
            <input name="first_name" class="form-control" required>
        </div>

        <div class="form-group mb-3">
            <label>Last Name</label>
            <input name="last_name" class="form-control" required>
        </div>

        <div class="form-group mb-3">
            <label>Email</label>
            <input name="email" class="form-control">
        </div>

        <div class="form-group mb-3">
            <label>Phone</label>
            <input name="phone" class="form-control">
        </div>

        <div class="form-group mb-3">
            <label>Home Airport</label>
            <select name="home_airport" class="form-control">
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo $loc['airport_code']; ?>">
                        <?php echo htmlspecialchars($loc['airport_code'] . ' - ' . $loc['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label>Aircraft Hours Remaining</label>
            <input type="number" step="0.1" name="aircraft_hours_remaining" class="form-control" value="0">
        </div>

        <div class="form-group mb-3">
            <label>Instructor Hours Remaining</label>
            <input type="number" step="0.1" name="instructor_hours_remaining" class="form-control" value="0">
        </div>

        <div class="form-group mb-4">
            <label>Assigned CFI</label>
            <select name="assigned_cfi_id" class="form-control">
                <option value="">-- None --</option>
                <?php foreach ($cfis as $cfi): ?>
                    <option value="<?php echo $cfi['cfi_id']; ?>">
                        <?php echo htmlspecialchars($cfi['cfi_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn btn-success" type="submit">➕ Add Student</button>
        <a href="admin-student-management.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script>
// Auto-format phone input (US format)
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let x = this.value.replace(/\D/g, '').substring(0,10);
            let formatted = x;

            if (x.length > 6) {
                formatted = `(${x.substring(0,3)}) ${x.substring(3,6)}-${x.substring(6,10)}`;
            } else if (x.length > 3) {
                formatted = `(${x.substring(0,3)}) ${x.substring(3)}`;
            } else if (x.length > 0) {
                formatted = `(${x}`;
            }

            this.value = formatted;
        });
    }
});
</script>

</body>
</html>
