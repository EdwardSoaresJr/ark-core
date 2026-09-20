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

];
