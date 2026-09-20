<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile push — platform transport (not per shop)
    |--------------------------------------------------------------------------
    |
    | One Firebase project serves the ARK Staff app binary for every tenant on
    | this runtime. Credentials and project id live in deployment env / mounted
    | secrets — not in shop Settings.
    |
    | Shop Settings → Communications → Mobile only toggles whether this shop
    | dispatches push packets (mobile_push.enabled on shop_settings).
    |
    */

    'push' => [
        'enabled' => filter_var(env('FCM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'credentials_path' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],
];
