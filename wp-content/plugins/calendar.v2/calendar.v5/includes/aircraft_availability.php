<?php

if (!function_exists('aircraft_status_allows_dispatch')) {
    function aircraft_status_allows_dispatch($status)
    {
        return strtolower((string)$status) !== 'out of service';
    }
}

if (!function_exists('check_aircraft_available')) {
    /**
     * Shared availability check for booking + checkout flows.
     */
    function check_aircraft_available(PDO $db, $tailNumber, $startAt, $endAt, &$reason = null)
    {
        $tailNumber = strtoupper(trim((string)$tailNumber));
        if ($tailNumber === '') {
            $reason = 'Tail number is required.';
            return false;
        }

        $statusStmt = $db->prepare('SELECT status FROM wp_aircraft WHERE tail_number = ? LIMIT 1');
        $statusStmt->execute([$tailNumber]);
        $aircraft = $statusStmt->fetch(PDO::FETCH_ASSOC);

        if (!$aircraft) {
            $reason = 'Aircraft not found.';
            return false;
        }

        if (!aircraft_status_allows_dispatch($aircraft['status'] ?? '')) {
            $reason = 'Aircraft is currently Out of Service.';
            return false;
        }

        $start = date('Y-m-d H:i:s', strtotime((string)$startAt));
        $end = date('Y-m-d H:i:s', strtotime((string)$endAt));

        $dtStmt = $db->prepare(
            "SELECT id, start_at, end_at, reason
             FROM wp_aircraft_downtime
             WHERE tail_number = :tail
               AND start_at < :end_at
               AND COALESCE(end_at, '9999-12-31 23:59:59') > :start_at
             ORDER BY start_at ASC
             LIMIT 1"
        );

        $dtStmt->execute([
            ':tail' => $tailNumber,
            ':start_at' => $start,
            ':end_at' => $end,
        ]);

        $downtime = $dtStmt->fetch(PDO::FETCH_ASSOC);

        if ($downtime) {
            $reason = sprintf(
                'Aircraft is unavailable due to downtime (%s: %s to %s).',
                $downtime['reason'] ?: 'No reason provided',
                $downtime['start_at'],
                $downtime['end_at'] ?: 'open'
            );
            return false;
        }

        return true;
    }
}

if (!function_exists('notify_aircraft_markdown_students')) {
    function notify_aircraft_markdown_students(PDO $db, $tailNumber, $startAt, $endAt, $reason)
    {
        $tailNumber = strtoupper(trim((string)$tailNumber));
        $start = date('Y-m-d H:i:s', strtotime((string)$startAt));
        $end = $endAt ? date('Y-m-d H:i:s', strtotime((string)$endAt)) : null;

        if ($end) {
            $sql = "SELECT f.id, f.start_time, f.end_time,
                           s.email, s.first_name, s.last_name
                    FROM wp_flight_schedule f
                    LEFT JOIN wp_students s ON f.student_id = s.student_id
                    WHERE f.tail_number = :tail
                      AND f.start_time < :end_at
                      AND f.end_time > :start_at
                    ORDER BY f.start_time ASC";
            $params = [
                ':tail' => $tailNumber,
                ':start_at' => $start,
                ':end_at' => $end,
            ];
        } else {
            $dayStart = date('Y-m-d 00:00:00', strtotime($start));
            $dayEnd = date('Y-m-d 23:59:59', strtotime($start));
            $sql = "SELECT f.id, f.start_time, f.end_time,
                           s.email, s.first_name, s.last_name
                    FROM wp_flight_schedule f
                    LEFT JOIN wp_students s ON f.student_id = s.student_id
                    WHERE f.tail_number = :tail
                      AND f.start_time <= :day_end
                      AND f.end_time >= :day_start
                    ORDER BY f.start_time ASC";
            $params = [
                ':tail' => $tailNumber,
                ':day_start' => $dayStart,
                ':day_end' => $dayEnd,
            ];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $emailed = 0;
        foreach ($rows as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $studentName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Student';
            $subject = "Aircraft Update: {$tailNumber} unavailable";
            $message = "Hello {$studentName},\n\n"
                . "Your scheduled flight on {$row['start_time']} is impacted because aircraft {$tailNumber} was marked unavailable.\n"
                . "Reason: {$reason}\n"
                . "Downtime start: {$start}\n"
                . "Downtime end: " . ($end ?: 'Not yet specified') . "\n\n"
                . "A staff member will follow up with rescheduling details.";

            @mail($row['email'], $subject, $message);
            $emailed++;
        }

        return [
            'affected_flights' => $rows,
            'affected_count' => count($rows),
            'emails_sent' => $emailed,
        ];
    }
}
