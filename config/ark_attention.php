<?php

return [
    'unassigned_conversation_minutes' => 15,
    'inbound_response_minutes' => 60,
    'estimate_followup_hours' => 24,
    'estimate_cold_hours' => 48,
    'ready_pickup_followup_hours' => 24,
    'lead_stale_hours' => 4,
    'parts_stale_days' => 2,

    // Route names (patterns as for routeIs) the comms attention gate lets through, in addition to
    // Core's own communications surfaces. An installation that serves another place to clear customer
    // comms lists it here, so advisors are not sent away from the page they are clearing them on.
    'gate_exempt_routes' => [],
];
