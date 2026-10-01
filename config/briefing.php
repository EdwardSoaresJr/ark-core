<?php

return [
    'estimate_follow_up_min_views' => (int) env('BRIEFING_ESTIMATE_FOLLOW_UP_MIN_VIEWS', 3),
    'parts_waiting_days' => (int) env('BRIEFING_PARTS_WAITING_DAYS', 2),
    'large_estimate_cents' => (int) env('BRIEFING_LARGE_ESTIMATE_CENTS', 200_000),
    'estimate_aging_days' => (int) env('BRIEFING_ESTIMATE_AGING_DAYS', 3),
    'revenue_spike_threshold_percent' => (int) env('BRIEFING_REVENUE_SPIKE_THRESHOLD', 25),
    'revenue_drop_threshold_percent' => (int) env('BRIEFING_REVENUE_DROP_THRESHOLD', 25),
    'max_attention_items' => (int) env('BRIEFING_MAX_ATTENTION_ITEMS', 12),
];
