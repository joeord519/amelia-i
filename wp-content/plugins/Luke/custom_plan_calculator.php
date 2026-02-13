<?php
// custom_plan_calculator.php

header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';
$db = getDB();

// ----------------------
// Global pricing constants
// ----------------------

// Base hourly rates (you can tweak these later or even move to a pricing table)
const RATE_AIRCRAFT  = 199.00;  // aircraft only
const RATE_INSTRUCTOR = 95.00;  // instructor only
const RATE_DUAL      = RATE_AIRCRAFT + RATE_INSTRUCTOR; // 294
const RATE_FMX_SIM_ONLY = 80.00;
const RATE_FMX_SIM_PLUS_CFI = 150.00;

// 1) Read JSON input
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// 2) Sanitize / defaults
$goal           = $data['target_goal'] ?? 'ppl';         // e.g. 'ppl','ifr','commercial_single'
$currentLicense = $data['current_license'] ?? 'none';    // e.g. 'none','sport','ppl','ifr'
$currentTotal   = (float)($data['current_total_time'] ?? 0);
$lessonsPerWeek = (float)($data['desired_lessons_per_week'] ?? 2);
$targetMonths   = (int)($data['target_completion_months'] ?? 0);

// TODO: you can add more structured hour breakdowns later (XC, night, multi, etc.)

// 3) Decide target total hours for goal
$targetTotalHours = getTargetHoursForGoal($goal, $currentLicense);

// 4) Compute remaining hours
$additionalNeeded = max(0, $targetTotalHours - $currentTotal);

// 5) Decide phases based on goal
$phases = buildPhases($goal, $currentLicense, $additionalNeeded, $db, $lessonsPerWeek, $targetMonths);

// 6) Build summary and payment view
$response = [
    'student_profile' => [
        'current_license'          => $currentLicense,
        'current_total_time'       => $currentTotal,
        'target_goal'              => $goal,
        'desired_lessons_per_week' => $lessonsPerWeek,
        'target_completion_months' => $targetMonths,
    ],
    'summary'            => buildSummary($targetTotalHours, $additionalNeeded, $phases),
    'phases'             => $phases,
    'global_payment_view'=> buildPaymentView($phases)
];

echo json_encode($response);
exit;


// ====================== Helper functions ======================

function getTargetHoursForGoal(string $goal, string $currentLicense): float {
    // These are "realistic Piston targets", not FAA minimums
    switch ($goal) {
        case 'ppl':
            return 65.0;                     // realistic average
        case 'sport':
            return 60.0;
        case 'ifr':
            // total time "in play" by the time IFR is solid
            return 120.0;
        case 'commercial_single':
            return 250.0;
        case 'commercial_multi':
            return 250.0;
        case 'atp_prep':
            return 1500.0;
        default:
            return 60.0;
    }
}

/**
 * Build phases based on the goal and current license.
 * This is where you gradually add more cases (ifr-only, commercial-only, career ladder, etc.)
 */
function buildPhases(
    string $goal,
    string $currentLicense,
    float $additionalNeeded,
    PDO $db,
    float $lessonsPerWeek,
    int $targetMonths
): array {
    $phases = [];

    // Example: Commercial (single) path for a PPL holder
    if ($goal === 'commercial_single' && $currentLicense === 'ppl') {
        // Phase 1: IFR finish-up if they don't already have IFR
        if ($currentLicense !== 'ifr') {
            $phases[] = buildPhaseFromProgramSlug($db, 'ifr_self_paced', [
                'phase_number' => 1,
                'label'        => 'Phase 1: IFR Training & Finish-Up',
                'estimated_hours' => [
                    'aircraft'    => 20,
                    'sim'         => 20,   // AATD portion
                    'instructor'  => 40,   // dual IFR
                ],
                'lessons_per_week' => $lessonsPerWeek
            ]);
        }

        // Phase 2: Time-building toward 250 (you can optionally blend in Multi/Time-Building Week)
        $remainingAfterIFR = max(0, $additionalNeeded - 60); // rough IFR chunk assumption

        $phases[] = buildPhaseFromProgramSlug($db, 'time_building_week', [
            'phase_number' => 2,
            'label'        => 'Phase 2: Time Building Toward 250 Hours',
            'estimated_hours' => [
                'aircraft'    => $remainingAfterIFR,
                'sim'         => 0,
                'instructor'  => 10,    // generic supervision/flight review time
            ],
            'lessons_per_week' => $lessonsPerWeek
        ]);

        // Phase 3: Commercial maneuvers & checkride prep
        $phases[] = buildPhaseFromProgramSlug($db, 'commercial_self_paced', [
            'phase_number' => 3,
            'label'        => 'Phase 3: Commercial Maneuvers & Checkride Prep',
            'estimated_hours' => [
                'aircraft'    => 15,
                'sim'         => 0,
                'instructor'  => 20,
            ],
            'lessons_per_week' => $lessonsPerWeek
        ]);
    }

    // TODO: add other goal flows:
    // - IFR only (ppl -> ifr)
    // - PPL from zero
    // - Sport -> PPL bridge
    // - Career track ladders (PPL -> IFR -> Commercial -> CFI)

    return $phases;
}

/**
 * Build a phase record using luke_programs + overrides.
 */
function buildPhaseFromProgramSlug(PDO $db, string $slug, array $phaseOverrides = []): array {
    $stmt = $db->prepare("SELECT * FROM luke_programs WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $basePrice   = (float)($row['price_total_usd'] ?? 0);       // package price if > 0
    $shortDesc   = $row['short_description'] ?? '';
    $displayName = $row['display_name'] ?? 'Training Phase';

    $est = $phaseOverrides['estimated_hours'] ?? [
        'aircraft'   => (float)($row['aircraft_hours'] ?? 0),
        'sim'        => (float)($row['simulator_hours'] ?? 0),
        'instructor' => (float)($row['instructor_hours'] ?? 0),
    ];

    // Estimate cash cost using simple rules:
    // - If basePrice > 0 → use that as primary, but still compute a ~10% low/high range
    // - If basePrice == 0 → cost = hours * hourly rates
    $cashLow  = estimatePhaseCost($basePrice, $est, 0.9);
    $cashHigh = estimatePhaseCost($basePrice, $est, 1.1);

    return [
        'phase_number'  => $phaseOverrides['phase_number'] ?? null,
        'label'         => $phaseOverrides['label'] ?? $displayName,
        'program_slug'  => $slug,
        'description'   => $shortDesc,
        'estimated_hours' => $est,
        'cash_estimate' => [
            'low'  => $cashLow,
            'high' => $cashHigh,
        ],
        'financing_options' => getFinancingOptionsForProgram($db, $slug),
        'timeline'          => buildTimelineEstimate($est, $phaseOverrides['lessons_per_week'] ?? 2),
    ];
}

/**
 * Estimate phase cost given:
 * - basePrice from luke_programs (if > 0)
 * - estimated hours (aircraft/sim/instructor)
 * - a fudgeFactor (e.g. 0.9 low, 1.1 high)
 */
function estimatePhaseCost(float $basePrice, array $hours, float $fudgeFactor = 1.0): float {
    $aircraftHours   = (float)($hours['aircraft'] ?? 0);
    $simHours        = (float)($hours['sim'] ?? 0);
    $instructorHours = (float)($hours['instructor'] ?? 0);

    if ($basePrice > 0) {
        // Treat as primarily a package price; adjust with small fudge factor
        return $basePrice * $fudgeFactor;
    }

    // Otherwise, calculate from hourly assumptions
    $costAircraft   = $aircraftHours   * RATE_AIRCRAFT;
    $costSim        = $simHours        * RATE_FMX_SIM_PLUS_CFI; // or SIM_ONLY depending on usage
    $costInstructor = $instructorHours * RATE_INSTRUCTOR;

    return ($costAircraft + $costSim + $costInstructor) * $fudgeFactor;
}

/**
 * Pick financing options based on program slug and payment types.
 * For now, just return a list of suggestable financing products by slug.
 */
function getFinancingOptionsForProgram(PDO $db, string $slug): array {
    $options = [];

    // Example: if IFR or Commercial-related, suggest FTF and/or Stratus
    if (strpos($slug, 'ifr') !== false) {
        $options[] = 'ifr_ftf';
        $options[] = 'stratus_financing';
    }
    if (strpos($slug, 'commercial') !== false) {
        $options[] = 'commercial_ftf';
        $options[] = 'stratus_financing';
    }

    return $options;
}

/**
 * Very rough timeline estimate: based on aircraft hours and lessons per week.
 */
function buildTimelineEstimate(array $hours, float $lessonsPerWeek): array {
    $aircraftHours = (float)($hours['aircraft'] ?? 0);
    if ($lessonsPerWeek <= 0) {
        $lessonsPerWeek = 2.0;
    }

    // Assume ~1.5 flight hours per lesson on average
    $weeks = $aircraftHours / ($lessonsPerWeek * 1.5);
    $months = $weeks / 4.0;

    return [
        'estimated_weeks'  => round($weeks, 1),
        'estimated_months' => max(1, round($months, 1)),
    ];
}

/**
 * Build an overall summary from phases.
 */
function buildSummary(float $targetTotalHours, float $additionalNeeded, array $phases): array {
    $sumLow  = 0;
    $sumHigh = 0;
    foreach ($phases as $phase) {
        $sumLow  += $phase['cash_estimate']['low'] ?? 0;
        $sumHigh += $phase['cash_estimate']['high'] ?? 0;
    }

    return [
        'target_total_hours'   => $targetTotalHours,
        'additional_needed'    => $additionalNeeded,
        'estimated_cost_low'   => round($sumLow, 0),
        'estimated_cost_high'  => round($sumHigh, 0),
        'notes'                => 'These are planning estimates; actual cost will depend on your final hours, weather, and training pace.'
    ];
}

/**
 * Placeholder: build a payment view (cash vs FTF vs Stratus).
 * For now this can just mirror the summary cost, and you can expand later.
 */
function buildPaymentView(array $phases): array {
    $total = 0;
    foreach ($phases as $p) {
        $total += $p['cash_estimate']['high'] ?? 0;
    }
    return [
        'cash_estimate_high' => round($total, 0),
        'ftf_example'        => 'Example: FTF could spread this over 3–5 years with 2–4 flights/week.',
        'stratus_example'    => 'Stratus could finance a structured, accelerated version of this plan.'
    ];
}
