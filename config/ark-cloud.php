<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Cloud posture
    |--------------------------------------------------------------------------
    |
    | Controls what the company marketing site may offer. Self-host install is
    | unaffected. Public hosted signup and published pricing stay off by default.
    |
    */

    'public_signups' => (bool) env('ARK_CLOUD_PUBLIC_SIGNUPS', false),

    'public_pricing' => (bool) env('ARK_CLOUD_PUBLIC_PRICING', false),

    'interest_email' => env('ARK_CLOUD_INTEREST_EMAIL', 'hello@autorepairkeeper.com'),

];
