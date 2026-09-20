<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repair Time Engine availability
    |--------------------------------------------------------------------------
    |
    | null  — auto-detect (rte_lab table exists and has rows)
    | true  — force enabled (still requires imported data at runtime)
    | false — hide Repair Time Engine surfaces without querying rte_lab
    |
    */
    'enabled' => env('RTE_LABOR_GUIDE_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Default labor hours basis
    |--------------------------------------------------------------------------
    |
    | Shop Avg is the working estimate default — weighted toward book high with
    | vehicle-age padding on Avg/Hi. Advisors can still apply Lo or Hi.
    |
    */
    'default_hours_basis' => env('RTE_LABOR_GUIDE_DEFAULT_BASIS', 'avg'),

    /*
    |--------------------------------------------------------------------------
    | Shop labor hours projection
    |--------------------------------------------------------------------------
    */
    'shop_hours_projection' => env('RTE_LABOR_GUIDE_SHOP_HOURS', true),

    'shop_hours' => [
        // 0 = book average, 1 = book high. 0.85 keeps default above midpoint.
        'avg_weight_toward_hi' => (float) env('RTE_LABOR_SHOP_AVG_WEIGHT', 0.85),
        // Headroom above book high before vehicle-age padding.
        'hi_ceiling_multiplier' => (float) env('RTE_LABOR_SHOP_HI_MULTIPLIER', 1.08),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vehicle age padding (model year → today)
    |--------------------------------------------------------------------------
    |
    | Applied to shop Avg and Hi only. Lo stays at book average so advisors can
    | still discount without fighting age padding on the floor.
    |
    */
    'vehicle_age_padding' => [
        ['min_age' => 0, 'max_age' => 7, 'multiplier' => 1.00],
        ['min_age' => 8, 'max_age' => 12, 'multiplier' => 1.08],
        ['min_age' => 13, 'max_age' => 17, 'multiplier' => 1.15],
        ['min_age' => 18, 'max_age' => null, 'multiplier' => 1.22],
    ],

];
