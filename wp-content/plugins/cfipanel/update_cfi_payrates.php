<?php
// update_cfi_payrates.php
// Run this as a daily cron to update base_pay_rate dynamically

require_once(__DIR__ . '/db_connect.php');
$db = getDB();

// Fetch all CFIs and their starting pay rate
$stmt = $db->query("SELECT cfi_id, starting_pay_rate FROM wp_cfis");
$cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($cfis as $cfi) {
    $cfi_id = $cfi['cfi_id'];
    $start_rate = floatval($cfi['starting_pay_rate'] ?? 45.00);

    // Fetch total dual (Student Flight) hours
    $stmt2 = $db->prepare("
        SELECT SUM(total_flight_time)
        FROM wp_flight_logs
        WHERE cfi_id = :id AND flight_category = 'Student Flight'
    ");
    $stmt2->execute([':id' => $cfi_id]);
    $total_hours = floatval($stmt2->fetchColumn() ?? 0);

    // Calculate base rate
    $new_rate = $start_rate;
    if ($total_hours >= 1000) {
        $new_rate = 50;
    } elseif ($total_hours >= 900) {
        $new_rate = 49;
    } elseif ($total_hours >= 800) {
        $new_rate = 48;
    } elseif ($total_hours >= 700) {
        $new_rate = 47;
    } elseif ($total_hours >= 600) {
        $new_rate = 46;
    }

    // Respect pay_cap_override: skip updating if it's set
    $check = $db->prepare("SELECT pay_cap_override FROM wp_cfis WHERE cfi_id = :id");
    $check->execute([':id' => $cfi_id]);
    $cap = $check->fetchColumn();

    if (!$cap) {
        $update = $db->prepare("UPDATE wp_cfis SET base_pay_rate = :rate WHERE cfi_id = :id");
        $update->execute([':rate' => $new_rate, ':id' => $cfi_id]);
    }
}
echo "✅ CFI pay rates updated successfully.\n";
?>
