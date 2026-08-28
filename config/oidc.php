<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OIDC issuer (Phase 1b spike — staging only until go/no-go)
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('OIDC_ENABLED', false),

    'issuer' => rtrim((string) env('OIDC_ISSUER', env('APP_URL', 'http://localhost')), '/'),

    'shop_id' => (string) env('OIDC_SHOP_ID', '1'),

    'authorization_code_ttl_seconds' => (int) env('OIDC_CODE_TTL', 600),

    'id_token_ttl_seconds' => (int) env('OIDC_ID_TOKEN_TTL', 3600),

    'access_token_ttl_seconds' => (int) env('OIDC_ACCESS_TOKEN_TTL', 3600),

    'private_key_storage_path' => env('OIDC_KEY_PATH', 'oidc/keys'),

];
