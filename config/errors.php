<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error Reporting
    |--------------------------------------------------------------------------
    |
    | When a reportable exception occurs, ARK logs structured context and can
    | email the address below. Expected client errors (404, 403, etc.) are
    | not reported.
    |
    */

    'report' => [
        'enabled' => env('ERROR_REPORT_ENABLED', true),
        'email' => env('ERROR_REPORT_EMAIL'),
        'throttle_seconds' => (int) env('ERROR_REPORT_THROTTLE', 300),
        'queue' => env('ERROR_REPORT_QUEUE', true),
        'file' => [
            'enabled' => env('ERROR_REPORT_FILE_ENABLED', true),
            'path' => storage_path('logs/reported-errors'),
            'retention_days' => (int) env('ERROR_REPORT_FILE_RETENTION_DAYS', 30),
        ],
    ],

];
