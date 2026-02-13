<?php
// /wp-content/plugins/time-building/get_weeks.php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

try {
    // Always show a rolling window of upcoming weeks.
    // Default: 26 weeks (can override with ?limit=)
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 26;

    // Only show weeks that have not fully ended yet.
    // Using CURDATE() (server local date) avoids timezone weirdness vs NOW().
    $sql = "
    SELECT
        w.id                              AS week_id,
        w.week_start,
        w.week_end,
        COALESCE(w.total_capacity, 6)     AS total_capacity,
        GREATEST(
            COALESCE(w.total_capacity, 6)
            - COALESCE((
                SELECT COUNT(*)
                FROM wp_tb_reservations r
                WHERE r.week_id = w.id
                  AND r.status IN ('paid','hold')
                  AND (r.expires_at IS NULL OR r.expires_at > NOW())
            ), 0),
            0
        ) AS spots_left
    FROM wp_tb_weeks w
    WHERE w.week_end >= CURDATE()
    ORDER BY w.week_start ASC
    LIMIT :lim
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'weeks'  => $weeks,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}

