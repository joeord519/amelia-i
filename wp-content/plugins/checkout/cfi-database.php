<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->query("SELECT * FROM wp_cfis WHERE status='Active' ORDER BY last_name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $fields = [
        'first_name', 'last_name', 'email', 'phone',
        'home_airport', 'rating', 'is_two_year_cfi',
        'cfi_cert_id', 'cfi_cert_expiration', 'med_expiration',
        'max_student_level', 'cfi_certifications', 'can_train_cfi',
        'google_calendar_id', 'status',
        'base_pay_rate', 'pay_cap_override', 'on_draw', 'draw_amount',
        'wing_id' // ✅ Add wing_id to fields
    ];

    $wingId = $data['wing_id'] ?? null;

    if ($wingId) {
        // ✅ Check if wing is active and not full
        $stmt = $conn->prepare("
            SELECT w.id, w.max_cfis, COUNT(c.cfi_id) AS current_count
            FROM wp_cfi_wings w
            LEFT JOIN wp_cfis c ON w.id = c.wing_id
            WHERE w.status = 'Active' AND w.id = ?
            GROUP BY w.id, w.max_cfis
        ");
        $stmt->execute([$wingId]);
        $wing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wing) {
            echo json_encode(['status' => 'error', 'message' => 'Wing is not active or does not exist.']);
            exit;
        }

        // ✅ Prevent over-assigning on ADD or on EDIT if changing wings
        if ($data['action'] === 'add' || ($data['action'] === 'edit' && isset($data['wing_id']))) {
            if ((int)$wing['current_count'] >= (int)$wing['max_cfis']) {
                echo json_encode(['status' => 'error', 'message' => 'This Wing is full (max 5 CFIs). Choose another.']);
                exit;
            }
        }
    }

    if ($data['action'] === 'add') {
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $stmt = $conn->prepare("INSERT INTO wp_cfis (" . implode(',', $fields) . ") VALUES ($placeholders)");
        $stmt->execute(array_map(fn($f) => $data[$f] ?? null, $fields));
    } else {
        $updates = implode('=?, ', $fields) . '=?';
        $stmt = $conn->prepare("UPDATE wp_cfis SET $updates WHERE cfi_id = ?");
        $stmt->execute(array_merge(array_map(fn($f) => $data[$f] ?? null, $fields), [$data['id']]));
    }

    echo json_encode(['status' => 'success']);
}



