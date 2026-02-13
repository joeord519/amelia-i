<?php
// joey_deal_engine.php
// Core “brain” for Joey’s deal building logic, simplified for compatibility.

if (!defined('ABSPATH')) {
    // If not in WP, just define ABSPATH so this file doesn't explode when called directly.
    define('ABSPATH', __DIR__ . '/');
}

/**
 * Helper: Get discount config for aircraft hours.
 *
 * Returns an array:
 * [
 *   'max'    => float (e.g. 0.082 for 8.2%),
 *   'offers' => [float, float, float] // the 3 pre-max offer steps
 * ]
 */
function joey_get_aircraft_discount_config($aircraft_hours)
{
    $aircraft_hours = (float) $aircraft_hours;

    if ($aircraft_hours >= 30) {
        // 30+: Max 8.2%; offers at 3%, 5%, 6.5%
        return array(
            'max'    => 0.082,
            'offers' => array(0.03, 0.05, 0.065),
        );
    } elseif ($aircraft_hours >= 20) {
        // 20–29: Max 6.5%; offers at 3%, 4%, 5%
        return array(
            'max'    => 0.065,
            'offers' => array(0.03, 0.04, 0.05),
        );
    } else {
        // <20: Max 5%; offers at 2.5%, 3%, 4%
        return array(
            'max'    => 0.05,
            'offers' => array(0.025, 0.03, 0.04),
        );
    }
}

/**
 * Helper: Get discount config for instructor hours.
 *
 * Returns:
 * [
 *   'max'    => float,
 *   'offers' => [float, float, float]
 * ]
 */
function joey_get_instructor_discount_config($instructor_hours)
{
    $instructor_hours = (float) $instructor_hours;

    if ($instructor_hours >= 30) {
        // 30+: Max 10%; offers at 4%, 7%, 8.2%
        return array(
            'max'    => 0.10,
            'offers' => array(0.04, 0.07, 0.082),
        );
    } elseif ($instructor_hours >= 20) {
        // 20–29: Max 7.5%; offers at 4%, 5%, 6%
        return array(
            'max'    => 0.075,
            'offers' => array(0.04, 0.05, 0.06),
        );
    } else {
        // <20: Max 5%; offers at 2%, 3%, 4%
        return array(
            'max'    => 0.05,
            'offers' => array(0.02, 0.03, 0.04),
        );
    }
}

/**
 * Helper: Given a config and negotiation round, pick a discount.
 *
 * Round mapping:
 *   round 1 -> offers[0]
 *   round 2 -> offers[1]
 *   round 3 -> offers[2]
 *   round 4+ -> max
 */
function joey_pick_discount_for_round($config, $round)
{
    $offers = isset($config['offers']) ? $config['offers'] : array(0, 0, 0);
    $max    = isset($config['max']) ? $config['max'] : 0;

    $round = (int) $round;

    if ($round <= 1) {
        return $offers[0];
    } elseif ($round == 2) {
        return $offers[1];
    } elseif ($round == 3) {
        return $offers[2];
    } else {
        // round 4+ => max discount ceiling
        return $max;
    }
}

/**
 * Helper: Pick ONE aircraft sweetener based on purchased aircraft hours.
 *
 * PRIORITY (highest value first):
 *   1) Free Aircraft Hour          (A >= 25)
 *   2) Free Instructor Hour        (A >= 15)
 *   3) Free 1/2 Aircraft Hour      (A >= 15)
 *   4) Free 1/2 Instructor Hour    (A >= 10)
 *
 * Returns:
 *   null OR [
 *     'key'         => string,
 *     'label'       => string,
 *     'bonus_hours' => ['aircraft' => float, 'instructor' => float]
 *   ]
 */
function joey_pick_aircraft_sweetener($aircraft_hours)
{
    $A = (float) $aircraft_hours;

    if ($A >= 25) {
        return array(
            'key'   => 'free_aircraft_hour',
            'label' => 'Free Aircraft Hour',
            'bonus_hours' => array(
                'aircraft'   => 1.0,
                'instructor' => 0.0,
            ),
        );
    }

    if ($A >= 15) {
        // Prefer free instructor hour over 1/2 aircraft hour
        return array(
            'key'   => 'free_instructor_hour_from_aircraft',
            'label' => 'Free Instructor Hour',
            'bonus_hours' => array(
                'aircraft'   => 0.0,
                'instructor' => 1.0,
            ),
        );
    }

    if ($A >= 10) {
        return array(
            'key'   => 'free_half_instructor_hour_from_aircraft',
            'label' => 'Free 1/2 Instructor Hour',
            'bonus_hours' => array(
                'aircraft'   => 0.0,
                'instructor' => 0.5,
            ),
        );
    }

    return null;
}

/**
 * Helper: Pick ONE instructor sweetener based on purchased instructor hours.
 *
 * PRIORITY:
 *   1) 2 Free Instructor Hours     (I >= 25)
 *   2) Free Instructor Hour        (I >= 15)
 *   3) Free 1/2 Instructor Hour    (I >= 10)
 */
function joey_pick_instructor_sweetener($instructor_hours)
{
    $I = (float) $instructor_hours;

    if ($I >= 25) {
        return array(
            'key'   => 'two_free_instructor_hours',
            'label' => '2 Free Instructor Hours',
            'bonus_hours' => array(
                'aircraft'   => 0.0,
                'instructor' => 2.0,
            ),
        );
    }

    if ($I >= 15) {
        return array(
            'key'   => 'one_free_instructor_hour',
            'label' => 'Free Instructor Hour',
            'bonus_hours' => array(
                'aircraft'   => 0.0,
                'instructor' => 1.0,
            ),
        );
    }

    if ($I >= 10) {
        return array(
            'key'   => 'half_free_instructor_hour',
            'label' => 'Free 1/2 Instructor Hour',
            'bonus_hours' => array(
                'aircraft'   => 0.0,
                'instructor' => 0.5,
            ),
        );
    }

    return null;
}

/**
 * MAIN ENTRY: Build a deal for a bundle of aircraft + instructor hours.
 *
 * @param float $aircraft_hours
 * @param float $instructor_hours
 * @param int   $round
 * @param array $rates             ['aircraft' => 199.00, 'instructor' => 95.00]
 * @param bool  $enable_sweeteners
 *
 * @return array{
 *   aircraft_hours: float,
 *   instructor_hours: float,
 *   aircraft: array,
 *   instructor: array,
 *   totals: array
 * }
 */
function joey_build_deal($aircraft_hours, $instructor_hours, $round, $rates = array(), $enable_sweeteners = true)
{
    $aircraft_hours   = (float) $aircraft_hours;
    $instructor_hours = (float) $instructor_hours;
    $round            = (int) $round;

    // --- 1) DISCOUNT CONFIGS & PICKED DISCOUNTS ---
    $air_cfg = joey_get_aircraft_discount_config($aircraft_hours);
    $ins_cfg = joey_get_instructor_discount_config($instructor_hours);

    // GLOBAL RULE: NO DISCOUNTS UNLESS AT LEAST 5 AIRCRAFT AND 5 INSTRUCTOR HOURS
    // If either side is under 5 hours, we hard-cap both discount configs at 0.
    if ($aircraft_hours < 5 || $instructor_hours < 5) {
        $air_cfg['max']    = 0.0;
        $air_cfg['offers'] = array(0.0, 0.0, 0.0);

        $ins_cfg['max']    = 0.0;
        $ins_cfg['offers'] = array(0.0, 0.0, 0.0);
    }

    $air_discount_pct = joey_pick_discount_for_round($air_cfg, $round);
    $ins_discount_pct = joey_pick_discount_for_round($ins_cfg, $round);

    // Safety clamps
    if ($air_discount_pct > $air_cfg['max']) {
        $air_discount_pct = $air_cfg['max'];
    }
    if ($ins_discount_pct > $ins_cfg['max']) {
        $ins_discount_pct = $ins_cfg['max'];
    }

    // --- 2) RATES & BASE PRICES ---
    $aircraft_rate   = (float) $rates['aircraft'];
    $instructor_rate = (float) $rates['instructor'];

    if ($aircraft_rate <= 0 || $instructor_rate <= 0) {
        throw new Exception("Invalid rates passed to joey_build_deal");
    }

    $air_base_price = $aircraft_hours   * $aircraft_rate;
    $ins_base_price = $instructor_hours * $instructor_rate;
    $base_total     = $air_base_price + $ins_base_price;

    // --- 3) APPLY DISCOUNTS SEPARATELY ---
    $air_price_after = $air_base_price * (1.0 - $air_discount_pct);
    $ins_price_after = $ins_base_price * (1.0 - $ins_discount_pct);
    $final_total     = $air_price_after + $ins_price_after;

    // --- 4) SWEETENERS ---
    $air_sweetener = null;
    $ins_sweetener = null;
    $bonus_aircraft_hours   = 0.0;
    $bonus_instructor_hours = 0.0;

    // Sweetener logic only really kicks in at higher hour counts anyway,
    // but we leave it as-is since <5 hours won't meet those thresholds.
    if ($enable_sweeteners && $round >= 2) {
        $air_sweetener = joey_pick_aircraft_sweetener($aircraft_hours);
        $ins_sweetener = joey_pick_instructor_sweetener($instructor_hours);

        if ($air_sweetener && isset($air_sweetener['bonus_hours'])) {
            $bonus_aircraft_hours   += $air_sweetener['bonus_hours']['aircraft'];
            $bonus_instructor_hours += $air_sweetener['bonus_hours']['instructor'];
        }
        if ($ins_sweetener && isset($ins_sweetener['bonus_hours'])) {
            $bonus_aircraft_hours   += $ins_sweetener['bonus_hours']['aircraft'];
            $bonus_instructor_hours += $ins_sweetener['bonus_hours']['instructor'];
        }
    }

    // Round prices for output
    $air_base_price   = round($air_base_price, 2);
    $ins_base_price   = round($ins_base_price, 2);
    $base_total       = round($base_total, 2);
    $air_price_after  = round($air_price_after, 2);
    $ins_price_after  = round($ins_price_after, 2);
    $final_total      = round($final_total, 2);

    return array(
        'aircraft_hours'   => $aircraft_hours,
        'instructor_hours' => $instructor_hours,
        'aircraft' => array(
            'rate'                 => $aircraft_rate,
            'base_price'           => $air_base_price,
            'max_discount_pct'     => $air_cfg['max'],
            'chosen_discount_pct'  => $air_discount_pct,
            'price_after_discount' => $air_price_after,
            'sweetener'            => $air_sweetener,
        ),
        'instructor' => array(
            'rate'                 => $instructor_rate,
            'base_price'           => $ins_base_price,
            'max_discount_pct'     => $ins_cfg['max'],
            'chosen_discount_pct'  => $ins_discount_pct,
            'price_after_discount' => $ins_price_after,
            'sweetener'            => $ins_sweetener,
        ),
        'totals' => array(
            'base_total_price'       => $base_total,
            'final_total_price'      => $final_total,
            'bonus_aircraft_hours'   => $bonus_aircraft_hours,
            'bonus_instructor_hours' => $bonus_instructor_hours,
        ),
    );
}

