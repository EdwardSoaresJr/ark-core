<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phone Verification Authority
    |--------------------------------------------------------------------------
    |
    | ARK owns codes and verified sessions. SMS providers only deliver messages.
    | Callers must never embed why the phone is being verified into this layer.
    |
    */

    'code_ttl_minutes' => (int) env('PHONE_VERIFICATION_CODE_TTL', 5),

    'session_ttl_minutes' => (int) env('PHONE_VERIFICATION_SESSION_TTL', 30),

    'max_attempts' => (int) env('PHONE_VERIFICATION_MAX_ATTEMPTS', 5),

    'send_cooldown_seconds' => (int) env('PHONE_VERIFICATION_SEND_COOLDOWN', 30),

    'max_sends_per_phone_per_hour' => (int) env('PHONE_VERIFICATION_MAX_SENDS_PHONE', 5),

    'max_requests_per_ip_per_hour' => (int) env('PHONE_VERIFICATION_MAX_REQUESTS_IP', 10),

];
