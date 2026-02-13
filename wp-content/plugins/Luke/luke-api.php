<?php
// luke-api.php
header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1) Read JSON input
// -----------------------------------------------------------------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$message    = trim($data['message']    ?? '');
$session_id = trim($data['session_id'] ?? '');

if ($message === '') {
    echo json_encode([
        'ok'    => false,
        'error' => 'empty_message'
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 2) Build Context Array
// -----------------------------------------------------------------------------
$context = [
    'student_status'          => 'public',
    'active_deals'            => [],
    'program_count'           => 0,
    'kb'                      => [],
    'aircraft'                => [],
    'locations'               => [],
    'student'                 => null,
    'lead'                    => null,
    'flight_schedule_preview' => [],
    'db_error'                => null,
];

// -----------------------------------------------------------------------------
// 3) Database Connection
// -----------------------------------------------------------------------------
$db = null;

try {
    $paths = [
        __DIR__ . '/../db_connect.php',
        __DIR__ . '/db_connect.php',
        dirname(__DIR__) . '/db_connect.php',
    ];

    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            if (function_exists('getDB')) {
                $db = getDB();
                break;
            }
        }
    }

    if (!($db instanceof PDO)) {
        $context['db_error'] = 'db_connect.php not found or getDB() not available.';
    } else {
        $today = date('Y-m-d');
        $audience = 'public';

        // ---------------- Deals ----------------
        try {
            $sql = "
                SELECT *
                FROM wp_deals
                WHERE status = 'active'
                  AND (start_date IS NULL OR start_date <= :today)
                  AND (end_date IS NULL   OR end_date >= :today)
                  AND (audience_scope = 'any'
                    OR audience_scope = 'public'
                    OR audience_scope = :aud)
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':today' => $today,
                ':aud'   => $audience
            ]);
            $context['active_deals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $context['active_deals'] = [];
            $context['db_error_deals'] = $e->getMessage();
        }

        // ---------------- Program Count ----------------
        try {
            $sql = "SELECT COUNT(*) AS c FROM luke_programs WHERE LOWER(TRIM(status))='active'";
            $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $context['program_count'] = (int)($row['c'] ?? 0);
        } catch (Throwable $e) {
            $context['program_count'] = 0;
            $context['db_error_program_count'] = $e->getMessage();
        }

        // ---------------- KB / Aircraft / Locations ----------------
        $context['kb']        = luke_safe_fetch_all($db, 'wp_luke_kb', 200);
        $context['aircraft']  = luke_safe_fetch_all($db, 'wp_aircraft', 200);
        $context['locations'] = luke_safe_fetch_all($db, 'wp_locations', 50);
    }

} catch (Throwable $e) {
    $context['db_error'] = $e->getMessage();
}

// -----------------------------------------------------------------------------
// 4) CALL LUKE BRAIN
// -----------------------------------------------------------------------------
require_once __DIR__ . '/luke_brain.php';

$brain = luke_generate_reply(
    ($db instanceof PDO ? $db : null),
    [
        'session_id' => $session_id,
        'message'    => $message
    ],
    $context
);

// Normalize reply fields
$replyText = $brain['reply_text'] ?? "I received your message, but my brain response was empty.";
$meta      = [
    'programs'    => $brain['programs']    ?? [],
    'deals'       => $brain['deals']       ?? [],
    'notes'       => $brain['notes']       ?? '',
    'actions'     => $brain['actions']     ?? [],
    'hours_offer' => $brain['hours_offer'] ?? null,
];

// -----------------------------------------------------------------------------
// 5) DECISION ENGINE v1
// -----------------------------------------------------------------------------
function luke_is_ready_to_buy(string $userMsg, string $botReply): bool {
    $m = strtolower($userMsg);
    return (
        str_contains($m, 'enroll') ||
        str_contains($m, 'sign up') ||
        str_contains($m, 'book') ||
        str_contains($m, 'get started') ||
        str_contains($m, 'pay') ||
        str_contains($m, 'purchase')
    );
}

// Use the REAL reply variable
if (luke_is_ready_to_buy($message, $replyText)) {

    $meta['intent'] = 'buy';

    $meta['actions'][] = [
        'type'         => 'stripe_checkout',
        'label'        => 'Start Enrollment – PPL 50x50',
        'program_slug' => 'ppl_50x50'
    ];
}

// -----------------------------------------------------------------------------
// 6) FINAL JSON OUTPUT (ONLY ONCE!)
// -----------------------------------------------------------------------------
echo json_encode([
    'ok'    => true,
    'reply' => $replyText,
    'meta'  => $meta
]);
exit;


// -----------------------------------------------------------------------------
// UTIL
// -----------------------------------------------------------------------------
function luke_safe_fetch_all($db, $table, $limit = 200) {
    if (!($db instanceof PDO)) return [];
    try {
        $stmt = $db->query("SELECT * FROM {$table} LIMIT " . (int)$limit);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

