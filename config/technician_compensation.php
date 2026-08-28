<?php

/**
 * Technician compensation guidance — not payroll authority.
 *
 * floor_wage_suggestion seeds new Flag technicians and surfaces review
 * when a stored floor differs. It never silently rewrites stored agreements.
 */
return [

    'floor_wage_suggestion' => [
        'amount_cents' => (int) env('TECH_FLOOR_WAGE_SUGGESTION_CENTS', 1516),
        'jurisdiction' => 'colorado_statewide',
        'effective_year' => (int) env('TECH_FLOOR_WAGE_SUGGESTION_YEAR', 2026),
        'label' => 'Colorado statewide minimum wage suggestion',
    ],

    /**
     * Phase 1A adoption — immutable recognition did not exist before this shop date.
     * Periods entirely before this are "unknown," not zero production.
     */
    'recognition_authority_starts_at' => env('TECH_FLAG_RECOGNITION_STARTS_AT', '2026-07-27'),

    'overtime_review' => [
        'weekly_hours_threshold' => (float) env('TECH_OT_WEEKLY_HOURS_THRESHOLD', 40),
        'daily_hours_threshold' => (float) env('TECH_OT_DAILY_HOURS_THRESHOLD', 12),
    ],

];
