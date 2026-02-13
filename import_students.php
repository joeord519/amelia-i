<?php
// ✅ Database Credentials
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';
$username = 'uizsmtjki2wdx';
$password = '7w26g#@$>iD5';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle); // skip header

        while (($data = fgetcsv($handle)) !== FALSE) {
            // Grab raw values
            $full_name = $data[1];
            $phone = preg_replace('/[^0-9]/', '', $data[2]);
            $email = $data[3];
            $aircraft_hours = floatval($data[4]);
            $instructor_hours = floatval($data[5]);
            $solo_status = ucfirst(strtolower(trim($data[8])));
            $signup_date = !empty($data[20]) ? date('Y-m-d', strtotime($data[20])) : null;
            $status = $data[0] === "Active" ? "Current" : "Inactive";

            // Split name
            $name_parts = explode(" ", trim($full_name), 2);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';

            // Check if student exists by phone
            $stmt = $pdo->prepare("SELECT student_id FROM wp_students WHERE REPLACE(phone, ' ', '') LIKE ?");
            $stmt->execute(["%$phone"]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                // UPDATE
                $update = $pdo->prepare("UPDATE wp_students SET 
                    first_name = ?, last_name = ?, email = ?, aircraft_hours_remaining = ?, 
                    instructor_hours_remaining = ?, solo_status = ?, sign_up_date = ?, student_status = ?
                    WHERE student_id = ?");
                $update->execute([
                    $first_name, $last_name, $email, $aircraft_hours, $instructor_hours,
                    $solo_status, $signup_date, $status, $student['student_id']
                ]);
            } else {
                // INSERT
                $insert = $pdo->prepare("INSERT INTO wp_students 
                    (first_name, last_name, phone, email, aircraft_hours_remaining, instructor_hours_remaining, 
                    solo_status, sign_up_date, student_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $first_name, $last_name, $phone, $email, $aircraft_hours, $instructor_hours,
                    $solo_status, $signup_date, $status
                ]);
            }
        }

        fclose($handle);
        echo "<p style='color: green;'>Student import complete!</p>";
    } else {
        echo "<p style='color: red;'>Failed to open file.</p>";
    }
}
?>

<!-- Upload form -->
<h2>📥 Upload Student CSV</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit">Import Students</button>
</form>
