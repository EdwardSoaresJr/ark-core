<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'ark_platform' => [
        // Prefer ARK_PLATFORM_*; fall back to deprecated ARK_CLOUD_*. Empty when unset so tests can set either key.
        'base_url' => env('ARK_PLATFORM_BASE_URL', env('ARK_CLOUD_BASE_URL', env('ARK_MAIL_SERVICE_URL'))),
        'offline_recovery_public_key' => env('ARK_PLATFORM_OFFLINE_RECOVERY_PUBLIC_KEY', env('ARK_CLOUD_OFFLINE_RECOVERY_PUBLIC_KEY', 'AJJroSsitWeaIjsqIaK30jDB8Y7ifkbwfNoEBuRstu8')),
        'mail_send' => env('ARK_MAIL_PLATFORM_SEND', true),
        'payments_capture' => env('ARK_PAYMENTS_PLATFORM_CAPTURE', true),
        'parts_catalog' => env('ARK_PARTS_PLATFORM_CATALOG', true),
    ],

    /* @deprecated Use services.ark_platform - retained while ARK Cloud terminology is retired. */
    'ark_cloud' => [
        'base_url' => env('ARK_PLATFORM_BASE_URL', env('ARK_CLOUD_BASE_URL', env('ARK_MAIL_SERVICE_URL', 'https://cloud.arksms.com'))),
        'offline_recovery_public_key' => env('ARK_PLATFORM_OFFLINE_RECOVERY_PUBLIC_KEY', env('ARK_CLOUD_OFFLINE_RECOVERY_PUBLIC_KEY', 'AJJroSsitWeaIjsqIaK30jDB8Y7ifkbwfNoEBuRstu8')),
    ],

    // Kept as aliases for older env files / local configs.
    'ark_mail' => [
        'base_url' => env('ARK_MAIL_SERVICE_URL', env('ARK_CLOUD_BASE_URL')),
    ],

    // Stock Core: not_configured. Set platform only when calling ARK Platform parts service.
    'parts_catalog' => [
        'driver' => env('ARK_PARTS_CATALOG_DRIVER', 'not_configured'),
    ],

    // Laravel framework may still reference a postmark mailer transport;
    // official ARK does not configure or document shop-held Postmark tokens.
    'postmark' => [
        'token' => null,
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'partstech' => [
        'base_url' => env('PARTSTECH_BASE_URL', 'https://app.partstech.com'),
        'catalog_path' => env('PARTSTECH_CATALOG_PATH', ''),
    ],

    'labor_guides' => [
        'alldata' => [
            'base_url' => env('LABOR_GUIDE_ALLDATA_URL', 'https://my.alldata.com/repair'),
            'login_path' => env('LABOR_GUIDE_ALLDATA_LOGIN_PATH', ''),
        ],
        'prodemand' => [
            'base_url' => env('LABOR_GUIDE_PRODEMAND_URL', 'https://www.prodemand.com'),
            'login_path' => env('LABOR_GUIDE_PRODEMAND_LOGIN_PATH', ''),
        ],
    ],

    'pdf' => [
        'node_binary' => env('PDF_NODE_BINARY'),
        'npm_binary' => env('PDF_NPM_BINARY'),
        'chrome_path' => env('PDF_CHROME_PATH'),
        'include_path' => env('PDF_INCLUDE_PATH'),
        'no_sandbox' => env('PDF_NO_SANDBOX', false),
    ],


    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
