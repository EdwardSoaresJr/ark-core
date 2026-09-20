<?php

declare(strict_types=1);

return [
    'key_tag_printer' => 'Brother QL-800',

    'key_tag_qz_page' => [
        'width_mm' => 62.0,
        'height_mm' => 38.1,
    ],

    'key_tag_qz_orientation' => 'portrait',

    'key_tag' => [
        'default_dpi' => (int) env('PRINTING_KEY_TAG_DEFAULT_DPI', 300),
    ],

    'key_tag_media_type' => 'mono',

    'default_printer' => 'HP LaserJet',

    'ql_force_raster' => filter_var(env('PRINTING_QL_FORCE_RASTER', 'false'), FILTER_VALIDATE_BOOLEAN),

    'ql_label_reference_mm' => [
        'width' => 62.0,
        'height' => 38.1,
    ],

    'ql_key_tag_lock_reference_raster' => filter_var(env('PRINTING_QL_KEY_TAG_LOCK_REFERENCE_RASTER', 'true'), FILTER_VALIDATE_BOOLEAN),

    'ql_key_tag_lock_reference_px' => [
        203 => ['w' => 496, 'h' => 304],
        300 => ['w' => 732, 'h' => 450],
    ],

    'qz' => [
        'certificate_path' => env('QZ_CERTIFICATE_PATH', ''),
        'private_key_path' => env('QZ_PRIVATE_KEY_PATH', ''),
        'private_key_passphrase' => env('QZ_PRIVATE_KEY_PASSPHRASE', ''),
        'signature_algorithm' => env('QZ_SIGNATURE_ALGORITHM', 'sha512'),
        'sign_cache_ttl' => (int) env('QZ_SIGN_CACHE_TTL', 30),
    ],
];
