<?php
ob_start();
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/afm_bridge.php');
header('Content-Type: application/json');

// Log inbound payload for debugging
file_put_contents(__DIR__ . '/debug_checkin.json', json_encode($_POST, JSON_PRETTY_PRINT));

// 🔧 RECOMMENDATION ENGINE FUNCTION — NOW SUPPORTS SOLO MODE
function getNextRecommendedFlights($studentId, $tailNumber, $cfiId, $db, $isSolo = false)
{
    $stmt = $db->prepare("
        SELECT DAYOFWEEK(start_time) as day_of_week, HOUR(start_time) as hour_block
        FROM wp_flight_logs 
        WHERE student_id = :studentId AND status = 'Completed'
        ORDER BY start_time DESC
        LIMIT 10
    ");
    $stmt->execute([':studentId' => $studentId]);
    $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timeCounts = [];
    foreach ($patterns as $row) {
        $key = $row['day_of_week'] . '-' . $row['hour_block'];
        $timeCounts[$key] = ($timeCounts[$key] ?? 0) + 1;
    }

    arsort($timeCounts);
    $topPatterns = array_slice(array_keys($timeCounts), 0, 2);

    file_put_contents(__DIR__ . '/debug_patterns.json', json_encode([
        'patterns'    => $timeCounts,
        'topPatterns' => $topPatterns
    ], JSON_PRETTY_PRINT));

    $recommended = [];

    foreach ($topPatterns as $pattern) {
        [$dayOfWeek, $hour] = explode('-', $pattern);

        for ($i = 1; $i <= 10; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            if (date('N', strtotime($date)) != $dayOfWeek) {
                continue;
            }

            $start = "$date $hour:00:00";
            $end   = date('Y-m-d H:i:s', strtotime("+2 hours", strtotime($start)));

            $conflictQuery = "
                SELECT COUNT(*) FROM wp_flight_schedule 
                WHERE (
                  (start_time < :end AND end_time > :start)
                  AND (tail_number = :tailNumber" . (!$isSolo ? " OR cfi_id = :cfiId" : "") . ")
                )
            ";

            $conflictCheck = $db->prepare($conflictQuery);
            $params = [
                ':start'      => $start,
                ':end'        => $end,
                ':tailNumber' => $tailNumber
            ];
            if (!$isSolo) {
                $params[':cfiId'] = $cfiId;
            }

            $conflictCheck->execute($params);
            if ($conflictCheck->fetchColumn() > 0) {
                continue;
            }

            $cfiName = $isSolo ? 'N/A' : '';
            if (!$isSolo && $cfiId) {
                $cfiLookup = $db->prepare("SELECT first_name, last_name FROM wp_cfis WHERE cfi_id = ?");
                $cfiLookup->execute([$cfiId]);
                $cfiRow = $cfiLookup->fetch();
                if ($cfiRow) {
                    $cfiName = $cfiRow['first_name'] . ' ' . $cfiRow['last_name'];
                }
            }

            $recommended[] = [
                'start_time' => $start,
                'end_time'   => $end,
                'aircraft'   => $tailNumber,
                'cfi_id'     => $isSolo ? null : $cfiId,
                'cfi_name'   => $cfiName
            ];

            if (count($recommended) >= 3) {
                break 2;
            }
        }
    }

    // 🔁 Fallback: next 21 days, open time blocks
    if (count($recommended) < 3) {
        for ($i = 0; $i <= 21; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            foreach ([7, 9, 11, 13, 15, 17] as $hour) {
                $start = "$date $hour:00:00";
                $end   = date('Y-m-d H:i:s', strtotime("+2 hours", strtotime($start)));

                $conflictQuery = "
                    SELECT COUNT(*) FROM wp_flight_schedule 
                    WHERE (
                      (start_time < :end AND end_time > :start)
                      AND (tail_number = :tailNumber" . (!$isSolo ? " OR cfi_id = :cfiId" : "") . ")
                    )
                ";

                $conflictCheck = $db->prepare($conflictQuery);
                $params = [
                    ':start'      => $start,
                    ':end'        => $end,
                    ':tailNumber' => $tailNumber
                ];
                if (!$isSolo) {
                    $params[':cfiId'] = $cfiId;
                }

                $conflictCheck->execute($params);
                if ($conflictCheck->fetchColumn() > 0) {
                    continue;
                }

                $cfiName = $isSolo ? 'N/A' : '';
                if (!$isSolo && $cfiId) {
                    $cfiLookup = $db->prepare("SELECT first_name, last_name FROM wp_cfis WHERE cfi_id = ?");
                    $cfiLookup->execute([$cfiId]);
                    $cfiRow = $cfiLookup->fetch();
                    if ($cfiRow) {
                        $cfiName = $cfiRow['first_name'] . ' ' . $cfiRow['last_name'];
                    }
                }

                $recommended[] = [
                    'start_time' => $start,
                    'end_time'   => $end,
                    'aircraft'   => $tailNumber,
                    'cfi_id'     => $isSolo ? null : $cfiId,
                    'cfi_name'   => $cfiName
                ];

                if (count($recommended) >= 3) {
                    break 2;
                }
            }
        }
    }

    file_put_contents(__DIR__ . '/debug_last_slots.json', json_encode($recommended, JSON_PRETTY_PRINT));
    return $recommended;
}

try {
    $logId    = $_POST['checkout_id'] ?? null;
    $endHobbs = floatval($_POST['end_hobbs'] ?? 0);
    $endTach  = floatval($_POST['end_tach'] ?? 0);
    $override = isset($_POST['override']) && $_POST['override'] === '1';

    if (!$logId || $endHobbs <= 0 || $endTach <= 0) {
        ob_end_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing or invalid Hobbs/Tach entries.'
        ]);
        exit;
    }

    $db = getDB();

    // 🔹 Pull existing flight log row
    $stmt = $db->prepare("
        SELECT 
          start_hobbs, 
          start_tach,
          plane_factor, 
          student_id, 
          cfi_id, 
          tail_number, 
          appointment_type,
          ground_time
        FROM wp_flight_logs 
        WHERE id = :id 
        LIMIT 1
    ");
    $stmt->execute([':id' => $logId]);
    $row = $stmt->fetch();
    if (!$row) {
        ob_end_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Flight log not found.'
        ]);
        exit;
    }

    $startHobbs      = floatval($row['start_hobbs']);
    $startTach       = floatval($row['start_tach']);
    $planeFactor     = floatval($row['plane_factor'] ?? 1.0);
    $studentId       = $row['student_id'];
    $cfiId           = $row['cfi_id'];
    $tailNumber      = $row['tail_number'] ?? 'Unknown';
    $appointmentType = trim($row['appointment_type'] ?? '');
    $groundTime      = floatval($row['ground_time'] ?? 0.0);

    // 🔒 SAFETY CHECKS FOR HOBBS/TACH
    $hobbsDelta = $endHobbs - $startHobbs;
    $tachDelta  = $endTach - $startTach;
    $deltaDiff  = abs($hobbsDelta - $tachDelta);

    // 1) No going backwards
    if ($endHobbs < $startHobbs || $endTach < $startTach) {
        ob_end_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => "These numbers go backwards.\n"
                       . "Start Hobbs: {$startHobbs}, End Hobbs: {$endHobbs}\n"
                       . "Start Tach: {$startTach}, End Tach: {$endTach}"
        ]);
        exit;
    }

    // 2) Reasonable upper bound (hard stop)
    $MAX_HOURS = 10; // any single flight over 10 hours is probably a data error

    if ($hobbsDelta <= 0 || $hobbsDelta > $MAX_HOURS || $tachDelta <= 0 || $tachDelta > $MAX_HOURS) {
        ob_end_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => "These values don’t look right for a single flight.\n"
                       . "Hobbs time: " . number_format($hobbsDelta, 2) . " hrs\n"
                       . "Tach time: " . number_format($tachDelta,  2) . " hrs"
        ]);
        exit;
    }

    // 3) Hobbs vs Tach sanity (0.8 hr tolerance, with override path)
    $TOLERANCE = 0.8;

    if ($deltaDiff > $TOLERANCE && !$override) {
        ob_end_clean();
        echo json_encode([
            'status'      => 'confirm_required',
            'message'     => "Hobbs and Tach differ by " . number_format($deltaDiff, 2) . " hours.",
            'hobbs_delta' => round($hobbsDelta, 2),
            'tach_delta'  => round($tachDelta, 2)
        ]);
        exit;
    }

    // ✅ If we reach here, values are acceptable (or override is confirmed)
    $totalFlightTime = $hobbsDelta;                    // base flight time
    $adjustedTime    = $totalFlightTime * $planeFactor;

    // ✅ Calculate total hours flown today, including this flight
    $totalTodayStmt = $db->prepare("
        SELECT SUM(total_flight_time) 
        FROM wp_flight_logs 
        WHERE student_id = :sid AND flight_date = CURDATE() AND status = 'Completed'
    ");
    $totalTodayStmt->execute([':sid' => $studentId]);
    $totalToday = floatval($totalTodayStmt->fetchColumn() ?: 0);
    $totalToday += $totalFlightTime; // include this flight

    // 🔑 Generate a CFI token for link
    $flightToken = bin2hex(random_bytes(32));
    $setToken = $db->prepare("UPDATE wp_flight_logs SET cfi_token = :token WHERE id = :id");
    $setToken->execute([':token' => $flightToken, ':id' => $logId]);

    // 🔄 Update the flight log with hobbs/tach, total time, status
    $update = $db->prepare("
        UPDATE wp_flight_logs
        SET end_hobbs = :endHobbs,
            end_tach = :endTach,
            checkin_time = NOW(),
            total_flight_time = :total,
            status = 'Completed'
        WHERE id = :id
    ");
    $update->execute([
        ':endHobbs' => $endHobbs,
        ':endTach'  => $endTach,
        ':total'    => $totalFlightTime,
        ':id'       => $logId
    ]);

    // 📝 If override was used with big delta, flag it for admin review
    if ($deltaDiff > $TOLERANCE && $override) {
        $note = "AUTO FLAG: Hobbs/Tach difference of " . number_format($deltaDiff, 2) . " hrs at check-in. Please review.";
        $flag = $db->prepare("
            UPDATE wp_flight_logs
            SET instructor_discrepancy_reason = :reason
            WHERE id = :id
        ");
        $flag->execute([
            ':reason' => $note,
            ':id'     => $logId
        ]);
    }

    // 🧠 Recommendation engine
    $recommendedFlights = getNextRecommendedFlights($studentId, $tailNumber, $cfiId, $db, false);

    // ✈️ Update aircraft latest tach/hobbs
    if ($tailNumber) {
        $updateAircraft = $db->prepare("
            UPDATE wp_aircraft
            SET latest_hobbs_time = :hobbs, latest_tach_time = :tach
            WHERE tail_number = :tail
        ");
        $updateAircraft->execute([
            ':hobbs' => $endHobbs,
            ':tach'  => $endTach,
            ':tail'  => $tailNumber
        ]);
    }

    // 🌐 Send tach/total_hours to Base44 AFM (non-blocking)
    if ($tailNumber) {
        $tachSyncResult = afm_send_total_hours($tailNumber, $endTach, $logId);
        if (!$tachSyncResult['success']) {
        // Log failure for debugging; do NOT affect student flow
        error_log('[Base44 Tach] Sync failed for ' . $tailNumber . ': ' . print_r($tachSyncResult, true));
    }
}


    // 💰 Update student balances (aircraft + instructor + ground)
    if ($studentId) {
        // Aircraft hours always use adjusted time
        $deductAircraft = $db->prepare("
            UPDATE wp_students
            SET aircraft_hours_remaining = aircraft_hours_remaining - :aircraft
            WHERE student_id = :sid
        ");
        $deductAircraft->execute([
            ':aircraft' => $adjustedTime,
            ':sid'      => $studentId
        ]);

        // Instructor logic: include ground_time for dual/checkride style events
        $normalizedType = strtolower(trim($appointmentType));
        $instructorUsed = 0.0;

        if (in_array($normalizedType, ['dual training', 'dual cross country', 'checkride'])) {
            // Instructor time = flight + any ground_time logged on this entry
            $instructorUsed = $totalFlightTime + $groundTime;
        }

        if ($instructorUsed > 0) {
            $deductCFI = $db->prepare("
                UPDATE wp_students
                SET instructor_hours_remaining = instructor_hours_remaining - :instructor
                WHERE student_id = :sid
            ");
            $deductCFI->execute([
                ':instructor' => $instructorUsed,
                ':sid'        => $studentId
            ]);
        }

        // Fetch updated student hours for logging
        $getStudent = $db->prepare("
            SELECT aircraft_hours_remaining, instructor_hours_remaining 
            FROM wp_students 
            WHERE student_id = ?
        ");
        $getStudent->execute([$studentId]);
        $studentRow = $getStudent->fetch();

        $newAircraft   = $studentRow ? floatval($studentRow['aircraft_hours_remaining'])   : null;
        $newInstructor = $studentRow ? floatval($studentRow['instructor_hours_remaining']) : null;

        // Update log with final balances
        $logUpdate = $db->prepare("
            UPDATE wp_flight_logs
            SET new_aircraft_hours_remaining   = :new_aircraft,
                new_instructor_hours_remaining = :new_instructor
            WHERE id = :id
        ");
        $logUpdate->execute([
            ':new_aircraft'   => number_format($newAircraft,   2),
            ':new_instructor' => number_format($newInstructor, 2),
            ':id'             => $logId
        ]);
    }

    // 🔔 Slack notification (unchanged)
    $slackToken = 'xoxp-5363861031136-5325621895031-8980992686519-2d322d81e11bb4dda9846e44d8defe64';

    $cfiQuery = $db->prepare("
        SELECT c.slack_username, c.email 
        FROM wp_flight_logs f
        JOIN wp_cfis c ON f.cfi_id = c.cfi_id
        WHERE f.id = :id
        LIMIT 1
    ");
    $cfiQuery->execute([':id' => $logId]);
    $cfi = $cfiQuery->fetch();

    if ($cfi && !empty($cfi['email'])) {
        $lookup = curl_init("https://slack.com/api/users.lookupByEmail?email=" . urlencode($cfi['email']));
        curl_setopt($lookup, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($lookup, CURLOPT_HTTPHEADER, ["Authorization: Bearer $slackToken"]);
        $lookupResp = curl_exec($lookup);
        curl_close($lookup);

        file_put_contents(__DIR__ . '/log/slack_debug.json', $lookupResp);
        $data = json_decode($lookupResp, true);

        if (!empty($data['ok']) && !empty($data['user']['id'])) {
            $slackUserId = $data['user']['id'];

            $flightInfo = $db->prepare("
                SELECT f.tail_number, s.first_name, s.last_name
                FROM wp_flight_logs f
                JOIN wp_students s ON f.student_id = s.student_id
                WHERE f.id = :id
                LIMIT 1
            ");
            $flightInfo->execute([':id' => $logId]);
            $info = $flightInfo->fetch();

            $student = $info['first_name'] . ' ' . $info['last_name'];
            $tail    = $info['tail_number'];
            $link    = "https://amelia-i.com/wp-content/plugins/checkout.basic/cfi-flight-log.php?id=$logId&token=$flightToken";

            $text = ":airplane: *Flight Check-In Alert*
*Student:* $student
*Tail:* $tail
<{$link}|✍️ Complete CFI Flight Log>";

            $msg = [
                'channel' => $slackUserId,
                'text'    => $text
            ];

            $send = curl_init("https://slack.com/api/chat.postMessage");
            curl_setopt($send, CURLOPT_POST, true);
            curl_setopt($send, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($send, CURLOPT_POSTFIELDS, json_encode($msg));
            curl_setopt($send, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $slackToken",
                "Content-Type: application/json"
            ]);
            $sendResp = curl_exec($send);
            file_put_contents(__DIR__ . '/log/slack_msg_response.json', $sendResp);
            curl_close($send);
        }
    }

    ob_end_clean();
    echo json_encode([
        'status'             => 'success',
        'message'            => 'Flight check-in complete.',
        'recommended_flights'=> $recommendedFlights,
        'flight_token'       => $flightToken,
        'total_flight_time'  => round($totalFlightTime, 2)
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
