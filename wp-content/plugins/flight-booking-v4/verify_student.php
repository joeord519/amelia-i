<?php
require 'db_connect.php'; // Ensure correct database connection
session_start(); // ✅ Start the PHP session

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = preg_replace('/\D/', '', $_POST['phone']); // Remove all non-numeric characters
    $security_word = $_POST['security_word'];

    // Format phone to match database format (XXX) XXX-XXXX
    if (strlen($phone) === 10) {
        $formatted_phone = "({$phone[0]}{$phone[1]}{$phone[2]}) {$phone[3]}{$phone[4]}{$phone[5]}-{$phone[6]}{$phone[7]}{$phone[8]}{$phone[9]}";
    } else {
        $formatted_phone = $phone;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.first_name, s.last_name, s.aircraft_hours_remaining, s.instructor_hours_remaining, 
                   s.latest_contract_file, s.assigned_cfi_id, s.home_airport, 
                   c.first_name AS cfi_first_name, c.last_name AS cfi_last_name,
                   l.name AS home_airport_name  -- ✅ Fetch the airport name correctly
            FROM wp_students s
            LEFT JOIN wp_cfis c ON s.assigned_cfi_id = c.cfi_id
            LEFT JOIN wp_locations l ON s.home_airport = l.airport_code  -- ✅ Corrected Join
            WHERE (s.phone = ? OR s.phone = ?) AND s.security_word = ?
        ");

        $stmt->execute([$phone, $formatted_phone, $security_word]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name']; // ✅ Store student name

        if ($student) {
            echo json_encode([
                "success" => true,
                "student_id" => $student['student_id'],  // ✅ Add student_id
                "student_name" => $student['first_name'] . ' ' . $student['last_name'],
                "aircraft_hours" => $student['aircraft_hours_remaining'],
                "instructor_hours" => $student['instructor_hours_remaining'],
                "latest_contract_file" => !empty($student['latest_contract_file']) && strtolower($student['latest_contract_file']) !== 'no' ? $student['latest_contract_file'] : null,
                "assigned_cfi_id" => !empty($student['assigned_cfi_id']) ? $student['assigned_cfi_id'] : null,
                "assigned_cfi_name" => !empty($student['cfi_first_name']) ? $student['cfi_first_name'] . ' ' . $student['cfi_last_name'] : "None",
                "home_airport" => !empty($student['home_airport']) ? $student['home_airport'] : null,
                "home_airport_name" => !empty($student['home_airport_name']) ? $student['home_airport_name'] : "Unknown" // ✅ Add the airport name to response
            ]);
        } else {
            echo json_encode(["success" => false, "error" => "Invalid credentials."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "error" => "Database Error: " . $e->getMessage()]);
    }
}
?>



