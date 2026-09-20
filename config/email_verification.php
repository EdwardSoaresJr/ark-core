<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Verification (Book / lead identity)
    |--------------------------------------------------------------------------
    |
    | Proves email possession only. Does not create customers or log anyone in.
    | Portal sign-in remains a separate known-customer challenge path.
    |
    */

    'code_ttl_minutes' => (int) env('EMAIL_VERIFICATION_CODE_TTL', 5),

    'session_ttl_minutes' => (int) env('EMAIL_VERIFICATION_SESSION_TTL', 30),

    'max_attempts' => (int) env('EMAIL_VERIFICATION_MAX_ATTEMPTS', 5),

    'send_cooldown_seconds' => (int) env('EMAIL_VERIFICATION_SEND_COOLDOWN', 30),

    'max_sends_per_email_per_hour' => (int) env('EMAIL_VERIFICATION_MAX_SENDS_EMAIL', 5),

    'max_requests_per_ip_per_hour' => (int) env('EMAIL_VERIFICATION_MAX_REQUESTS_IP', 10),

];
