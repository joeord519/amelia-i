<?php
require_once(__DIR__ . '/db_connect.php');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['cfi_id'])) {
    echo json_encode(['error' => 'CFI not logged in']);
    exit;
}

$cfi_id = (int) $_SESSION['cfi_id'];
$conn = getDB();

// Step 1: Pull students assigned to this CFI + flight data
$sql = "
    SELECT 
        s.student_id,
        s.first_name,
        s.last_name,
        s.phone,
        s.email,
        s.current_lesson,
        s.aircraft_hours_remaining,
        s.instructor_hours_remaining,
        MAX(f.flight_date) AS last_flight_date,
        DATEDIFF(CURDATE(), MAX(f.flight_date)) AS days_since_flight
    FROM wp_students s
    LEFT JOIN wp_flight_logs f ON f.student_id = s.student_id
    WHERE s.assigned_cfi_id = ?
    GROUP BY s.student_id
";

$stmt = $conn->prepare($sql);
$stmt->execute([$cfi_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Step 2: Score them based on urgency to follow up
foreach ($students as &$s) {
    $score = 0;
    $days = (int) $s['days_since_flight'];
    $air = (float) $s['aircraft_hours_remaining'];
    $inst = (float) $s['instructor_hours_remaining'];

    // 🔥 Time-based urgency
    if ($days >= 7 && $days <= 10) $score += 6;
    else if ($days > 10 && $days <= 21) $score += 4;
    else if ($days > 21) $score += 2;

    // ⛽ Aircraft time left
    if ($air < 2) $score += 5;
    else if ($air < 4) $score += 3;

    // 🧑‍🏫 Instructor time left
    if ($inst < 1.5) $score += 4;
    else if ($inst < 3) $score += 2;

    // ✈️ Just flew? Deprioritize
    if ($days <= 3) $score -= 3;

    // 🧠 Combo bonus
    if ($days >= 7 && ($air < 2 || $inst < 1.5)) {
        $score += 2;
    }

    $s['outreach_score'] = $score;
}

// Step 3: Sort by outreach score (descending)
usort($students, fn($a, $b) => $b['outreach_score'] <=> $a['outreach_score']);

// Output final list
echo json_encode($students);



