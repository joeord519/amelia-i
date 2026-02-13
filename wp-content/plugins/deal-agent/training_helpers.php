<?php
// training_helpers.php
// Helpers for estimating training hours and suggesting hour blocks for Joey.

/**
 * Get a setting from wp_company_settings with a default fallback.
 */
function da_get_setting(PDO $pdo, string $key, $default = null)
{
    $sql = "SELECT setting_value FROM wp_company_settings WHERE setting_key = :key LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['setting_value'])) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Estimate training hours for a student based on program and total hours.
 * NOTE: This assumes wp_students may have a 'total_hours' column.
 * If not, logged_hours will be null and Joey should ask the student directly.
 *
 * Returns an array like:
 * [
 *   'program'        => 'PPL',
 *   'logged_hours'   => 32.5,
 *   'target_low'     => 60,
 *   'target_high'    => 75,
 *   'remaining_low'  => 27.5,
 *   'remaining_high' => 42.5,
 *   'remaining_mid'  => 35,
 *   'stage'          => 'early' | 'mid' | 'late' | 'unknown'
 * ]
 */
function da_estimate_training_hours(PDO $pdo, array $student): array
{
    $program = strtoupper(trim($student['program'] ?? ''));
    $logged  = isset($student['total_hours']) ? (float)$student['total_hours'] : null;

    // Defaults
    $target_low  = null;
    $target_high = null;
    $stage       = 'unknown';
    $remaining_low = null;
    $remaining_high = null;
    $remaining_mid = null;

    // Load program-specific targets
    switch ($program) {
        case 'PPL':
            $target_low  = (float) da_get_setting($pdo, 'ppl_target_low_hours', 60);
            $target_high = (float) da_get_setting($pdo, 'ppl_target_high_hours', 75);
            break;

        case 'IFR':
            $target_low  = (float) da_get_setting($pdo, 'ifr_target_low_hours', 45);
            $target_high = (float) da_get_setting($pdo, 'ifr_target_high_hours', 60);
            break;

        case 'COMM':
            // For commercial, core target is total time: comm_min_total_hours
            $target_low  = (float) da_get_setting($pdo, 'comm_min_total_hours', 250);
            $target_high = $target_low; // treat min as both low/high for total time
            break;

        case 'CFI':
            // CFI is mostly instructor time; total time target optional
            $target_low  = null;
            $target_high = null;
            break;

        default:
            // Unknown / no program
            break;
    }

    if ($logged !== null && $target_low !== null && $target_high !== null) {
        $target_mid = ($target_low + $target_high) / 2.0;
        if ($target_mid > 0) {
            $progress = $logged / $target_mid;

            // Determine stage based on progress
            if ($progress < 0.35) {
                $stage = 'early';
            } elseif ($progress < 0.8) {
                $stage = 'mid';
            } else {
                $stage = 'late';
            }

            // Remaining without multipliers
            $raw_remaining_mid = max(0.0, $target_mid - $logged);

            // Stage multipliers
            $early_mult = (float) da_get_setting($pdo, 'training_stage_early_multiplier', 1.15);
            $mid_mult   = (float) da_get_setting($pdo, 'training_stage_mid_multiplier',   1.00);
            $late_mult  = (float) da_get_setting($pdo, 'training_stage_late_multiplier',  0.90);

            switch ($stage) {
                case 'early':
                    $remaining_mid = $raw_remaining_mid * $early_mult;
                    break;
                case 'mid':
                    $remaining_mid = $raw_remaining_mid * $mid_mult;
                    break;
                case 'late':
                    $remaining_mid = $raw_remaining_mid * $late_mult;
                    break;
                default:
                    $remaining_mid = $raw_remaining_mid;
            }

            // Simple low/high remaining based on target band
            $remaining_low  = max(0.0, $target_low  - $logged);
            $remaining_high = max(0.0, $target_high - $logged);
        }
    } else {
        // logged_hours is unknown or targets missing
        $stage = 'unknown';
    }

    return [
        'program'        => $program,
        'logged_hours'   => $logged,
        'target_low'     => $target_low,
        'target_high'    => $target_high,
        'remaining_low'  => $remaining_low,
        'remaining_high' => $remaining_high,
        'remaining_mid'  => $remaining_mid,
        'stage'          => $stage,
    ];
}

/**
 * Suggest a block of aircraft + instructor hours for the student,
 * using your sales logic:
 *
 * - PPL EARLY: big equal blocks (20/20, 30/30, 40/40) to unlock better discounts.
 * - PPL MID: medium balanced blocks (20–30 / 20–30).
 * - PPL LATE: fewer aircraft hours + more instructor hours (checkride prep).
 *
 * For other programs, we can add rules later; for now we keep it simple or null.
 *
 * Returns:
 * [
 *   'aircraft_hours'        => 30,
 *   'instructor_hours'      => 30,
 *   'stage'                 => 'early',
 *   'max_discount_pct'      => 8.2,
 *   'sweeteners'            => [
 *       'max_free_aircraft_hours'   => 1.5,
 *       'max_free_instructor_hours' => 1.5
 *   ]
 * ]
 */
function da_suggest_block_for_student(PDO $pdo, array $student, array $estimate): array
{
    $program = $estimate['program'] ?? strtoupper(trim($student['program'] ?? ''));
    $stage   = $estimate['stage'] ?? 'unknown';
    $remaining_mid = $estimate['remaining_mid'] ?? null;

    $aircraft_hours   = null;
    $instructor_hours = null;

    // Only implement detailed logic for PPL for now
    if ($program === 'PPL') {
        if ($remaining_mid === null) {
            // If we don't know remaining, use a safe default suggest
            $aircraft_hours   = 20;
            $instructor_hours = 20;
            $stage = 'unknown';
        } else {
            // Round remaining_mid to nearest 5
            $rounded = max(5, round($remaining_mid / 5) * 5);

            switch ($stage) {
                case 'early':
                    // EARLY: encourage larger equal blocks (20–40)
                    // so 20/20, 30/30, or 40/40 to unlock better discounts.
                    if ($rounded < 20) {
                        $aircraft_hours = 20;
                    } elseif ($rounded > 40) {
                        $aircraft_hours = 40;
                    } else {
                        $aircraft_hours = $rounded;
                    }
                    $instructor_hours = $aircraft_hours; // equal for early
                    break;

                case 'mid':
                    // MID: 20–30 balanced block
                    if ($rounded < 20) {
                        $aircraft_hours = 20;
                    } elseif ($rounded > 30) {
                        $aircraft_hours = 30;
                    } else {
                        $aircraft_hours = $rounded;
                    }
                    $instructor_hours = $aircraft_hours;
                    break;

                case 'late':
                    // LATE: fewer aircraft hours, more instructor (checkride prep)
                    // Cover most of remaining, but emphasize CFI.
                    if ($rounded < 10) {
                        $aircraft_hours = 10;
                    } else {
                        $aircraft_hours = $rounded;
                    }
                    // Instructor heavier, e.g. 20% more
                    $instructor_hours = (int) round($aircraft_hours * 1.2);
                    break;

                default:
                    // Unknown stage, safe generic block
                    $aircraft_hours   = 20;
                    $instructor_hours = 20;
            }
        }
    } else {
        // For IFR/COMM/CFI we can add program-specific logic later.
        // For now, return null suggestion.
        return [
            'program'            => $program,
            'stage'              => $stage,
            'aircraft_hours'     => null,
            'instructor_hours'   => null,
            'max_discount_pct'   => null,
            'sweeteners'         => [
                'max_free_aircraft_hours'   => 0.0,
                'max_free_instructor_hours' => 0.0,
            ],
        ];
    }

    // Determine max discount allowed based on aircraft_hours
    $max_discount_pct = 0.0;
    if ($aircraft_hours >= 30) {
        $max_discount_pct = 8.2;
    } elseif ($aircraft_hours >= 20) {
        $max_discount_pct = 6.5;
    } else {
        $max_discount_pct = 5.0;
    }

    // Calculate sweetener ceilings based on your rules:
    // Deal Sweeteners:
    // - 1 Free Aircraft hour for purchases over 25 aircraft hours
    // - 1 Free Instructor Hour for purchases of 15 aircraft hours or 15+ Instructor Hours
    // - Free Half Aircraft Hour for purchases of 15+ aircraft hours
    // - Free Half Instructor Hours for Purchases of 10 aircraft hours or 10+ Instructor Hours
    $max_free_aircraft_hours   = 0.0;
    $max_free_instructor_hours = 0.0;

    // Aircraft sweeteners
    if ($aircraft_hours > 25) {
        $max_free_aircraft_hours += 1.0;
    }
    if ($aircraft_hours >= 15) {
        $max_free_aircraft_hours += 0.5;
    }

    // Instructor sweeteners
    if ($aircraft_hours >= 15 || $instructor_hours >= 15) {
        $max_free_instructor_hours += 1.0;
    }
    if ($aircraft_hours >= 10 || $instructor_hours >= 10) {
        $max_free_instructor_hours += 0.5;
    }

    return [
        'program'            => $program,
        'stage'              => $stage,
        'aircraft_hours'     => $aircraft_hours,
        'instructor_hours'   => $instructor_hours,
        'max_discount_pct'   => $max_discount_pct,
        'sweeteners'         => [
            'max_free_aircraft_hours'   => $max_free_aircraft_hours,
            'max_free_instructor_hours' => $max_free_instructor_hours,
        ],
    ];
}
