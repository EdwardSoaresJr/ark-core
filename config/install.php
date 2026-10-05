<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Managed database
    |--------------------------------------------------------------------------
    |
    | When true, ARK created this database environment (Docker Compose).
    | First-run setup verifies the runtime connection and does not ask the
    | operator to type Docker-internal credentials.
    |
    */

    'managed_database' => filter_var(env('ARK_MANAGED_DATABASE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Install entry
    |--------------------------------------------------------------------------
    |
    | Set by the deployment, not by the browser. self_hosted is the default
    | and starts at system checks. managed starts at shop identity.
    | This is not ARK_MANAGED_DATABASE. A supplied database does not choose
    | the onboarding mode.
    |
    */

    'mode' => env('ARK_INSTALL_MODE') === 'managed' ? 'managed' : 'self_hosted',

];
