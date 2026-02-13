<?php
require_once __DIR__ . '/db_connect.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_csv'])) {
    $file = $_FILES['student_csv']['tmp_name'];
    $handle = fopen($file, 'r');

    if ($handle === false) {
        die("❌ Failed to open uploaded file.");
    }

    $header = fgetcsv($handle); // Skip header row
    $updated = 0;
    $skipped = [];

    while (($data = fgetcsv($handle)) !== false) {
        // Assuming columns are in fixed order: Name, Phone, Email, Aircraft, Instructor, Sim, Family, Active
        $fullName = $data[0];
        $email = trim($data[2]);
        $status = strtolower(trim($data[7])) === 'active' ? 'active' : 'inactive';

        $nameParts = explode(' ', $fullName, 2);
        $firstName = trim($nameParts[0]);
        $lastName = trim($nameParts[1] ?? '');

        // Try to find matching students by name
        $stmt = $conn->prepare("SELECT * FROM wp_students WHERE first_name = ? AND last_name = ?");
        $stmt->execute([$firstName, $lastName]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($matches) === 1) {
            $studentId = $matches[0]['student_id'];
        } elseif (count($matches) > 1 && $email) {
            // Try to resolve duplicates by email
            foreach ($matches as $match) {
                if (strtolower($match['email']) === strtolower($email)) {
                    $studentId = $match['student_id'];
                    break;
                }
            }
        } else {
            $skipped[] = $fullName;
            continue;
        }

        if (!empty($studentId)) {
            $update = $conn->prepare("UPDATE wp_students SET status = ? WHERE student_id = ?");
            $update->execute([$status, $studentId]);
            $updated++;
        } else {
            $skipped[] = $fullName;
        }
    }

    fclose($handle);

    echo "✅ Updated $updated students.<br>";
    if (!empty($skipped)) {
        echo "⚠️ Skipped the following:<br><ul>";
        foreach ($skipped as $name) {
            echo "<li>$name</li>";
        }
        echo "</ul>";
    }
} else {
    echo "❌ No file uploaded.";
}
