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

    'ark_mail' => [
        'base_url' => env('ARK_MAIL_SERVICE_URL'),
        'allow_activation' => (bool) env('ARK_MAIL_ALLOW_ACTIVATION', false),
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
        'username' => env('PARTSTECH_USERNAME'),
        'api_key' => env('PARTSTECH_API_KEY'),
        'password' => env('PARTSTECH_PASSWORD'),
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

    'square' => [
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        'voice_twiml_app_sid' => env('TWILIO_VOICE_TWIML_APP_SID'),
        'fcm_credential_sid' => env('TWILIO_FCM_CREDENTIAL_SID'),
        'apns_voip_credential_sid' => env('TWILIO_APNS_VOIP_CREDENTIAL_SID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Messenger (platform App — not shop Page)
    |--------------------------------------------------------------------------
    |
    | One ARK Meta App per deploy. Shops connect Pages only (Page ID + token).
    |
    */
    'meta_messenger' => [
        'app_id' => env('META_MESSENGER_APP_ID'),
        'app_secret' => env('META_MESSENGER_APP_SECRET'),
        'verify_token' => env('META_MESSENGER_VERIFY_TOKEN'),
        'graph_version' => env('META_MESSENGER_GRAPH_VERSION', 'v23.0'),
    ],

];
