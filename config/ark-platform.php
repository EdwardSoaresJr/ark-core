<?php

return [

    'coolify' => [
        'enabled' => (bool) env('COOLIFY_ENABLED', false),
        'base_url' => rtrim((string) env('COOLIFY_BASE_URL', 'https://platform.autorepairkeeper.com'), '/'),
        'token' => env('COOLIFY_API_TOKEN'),
        'timeout' => (int) env('COOLIFY_TIMEOUT', 15),
        'connect_timeout' => (int) env('COOLIFY_CONNECT_TIMEOUT', 5),
        /*
        | 1 = authenticate
        | 2 = + discover servers / resolve deployment_target
        | 3 = + discover projects/apps / resolve application
        | 4 = + trigger deploy (existing app only)
        | 5 = + observe completion (bounded poll)
        */
        'milestone' => (int) env('COOLIFY_ADAPTER_MILESTONE', 1),
        'application_uuid' => env('COOLIFY_DEPLOY_APPLICATION_UUID'),
        'poll_interval_seconds' => (int) env('COOLIFY_POLL_INTERVAL', 2),
        'poll_timeout_seconds' => (int) env('COOLIFY_POLL_TIMEOUT', 60),
    ],

];
