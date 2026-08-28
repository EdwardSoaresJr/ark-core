<?php

return [
    'enabled' => env('GROWTH_ENABLED', true),

    'redirects_enabled' => env('GROWTH_REDIRECTS_ENABLED', true),

    'public_surface' => [
        'server_page_views' => env('GROWTH_PUBLIC_SURFACE_SERVER_PAGE_VIEWS', true),
    ],

    'event_collection' => [
        'enabled' => env('GROWTH_EVENT_COLLECTION', true),
        'throttle_per_minute' => 120,
    ],

    'health' => [
        'seo_weight' => 0.25,
        'content_weight' => 0.25,
        'search_weight' => 0.25,
        'conversion_weight' => 0.25,
    ],

    'sitemap' => [
        'sections' => [
            'pages',
            'services',
            'common_problems',
            'manufacturers',
            'vehicles',
            'blog',
            'images',
        ],
        'default_changefreq' => 'weekly',
        'index_chunk_size' => 50000,
    ],

    'integrations' => [
        'google_search_console' => [
            'enabled' => env('GROWTH_SEARCH_CONSOLE_ENABLED', false),
            'property' => env('GROWTH_SEARCH_CONSOLE_PROPERTY'),
            'credentials_json' => env('GROWTH_SEARCH_CONSOLE_CREDENTIALS_JSON'),
            'fixture_enabled' => env('GROWTH_SEARCH_CONSOLE_FIXTURE', true),
        ],
        'google_analytics_4' => [
            'enabled' => false,
        ],
        'google_ads' => [
            'tag_id' => env('GOOGLE_ADS_TAG_ID'),
            'ga4_measurement_id' => env('GOOGLE_ADS_GA4_MEASUREMENT_ID'),
        ],
        'google_business_profile' => [
            'fixture_enabled' => env('GROWTH_GBP_FIXTURE', true),
        ],
        'bing_webmaster' => [
            'enabled' => false,
        ],
    ],

    'revenue_explorer' => [
        'default_revenue_threshold_cents' => 1_000_000,
        'default_limit' => 50,
    ],

    'opportunities' => [
        'queue_limit' => 5,
        'lookback_days' => 28,
        'create_min_impressions' => 100,
        'improve_min_impressions' => 50,
        'improve_ctr_ceiling' => 0.02,
    ],

    'maintenance' => [
        'nightly_at' => env('GROWTH_MAINTENANCE_NIGHTLY_AT', '02:00'),
        'auto_ensure' => env('GROWTH_MAINTENANCE_AUTO_ENSURE', true),
        'stale_after_hours' => (int) env('GROWTH_MAINTENANCE_STALE_HOURS', 26),
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO automation — search demand → pages → search engine notify
    |--------------------------------------------------------------------------
    |
    | Nightly pipeline: ingest Search Console → recalculate opportunities →
    | auto-publish qualified Create rows → notify IndexNow + Google.
    |
    */
    'seo_automation' => [
        'auto_publish_enabled' => env('GROWTH_AUTO_PUBLISH_PAGES', true),
        'auto_publish_max_per_night' => (int) env('GROWTH_AUTO_PUBLISH_MAX_PER_NIGHT', 2),
        'auto_publish_min_impressions' => (int) env('GROWTH_AUTO_PUBLISH_MIN_IMPRESSIONS', 500),
        'auto_publish_max_position' => (float) env('GROWTH_AUTO_PUBLISH_MAX_POSITION', 15),
        'notify_search_engines' => env('GROWTH_NOTIFY_SEARCH_ENGINES', true),
        'google_indexing_enabled' => env('GROWTH_GOOGLE_INDEXING_ENABLED', true),
        'google_indexing_daily_limit' => (int) env('GROWTH_GOOGLE_INDEXING_DAILY_LIMIT', 20),
    ],

    'business_profile' => [
        'fixture' => [
            'metrics' => [
                ['metric' => 'CALL_CLICKS', 'value' => 4],
                ['metric' => 'WEBSITE_CLICKS', 'value' => 18],
                ['metric' => 'BUSINESS_DIRECTION_REQUESTS', 'value' => 11],
                ['metric' => 'BUSINESS_CONVERSATIONS', 'value' => 2],
                ['metric' => 'BUSINESS_IMPRESSIONS_MOBILE_MAPS', 'value' => 612],
                ['metric' => 'BUSINESS_IMPRESSIONS_DESKTOP_MAPS', 'value' => 284],
                ['metric' => 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH', 'value' => 430],
                ['metric' => 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'value' => 196],
            ],
        ],
    ],

    'search_console' => [
        'fixture' => [
            'queries' => [
                ['query' => 'wheel bearing noise', 'impressions' => 1842, 'clicks' => 12, 'position' => 8.4],
                ['query' => 'p0171', 'impressions' => 920, 'clicks' => 4, 'position' => 11.2],
                ['query' => 'brake repair colorado springs', 'impressions' => 640, 'clicks' => 9, 'position' => 5.2],
                ['query' => 'subaru overheating', 'impressions' => 410, 'clicks' => 3, 'position' => 9.1],
                ['query' => 'honda timing belt', 'impressions' => 380, 'clicks' => 2, 'position' => 14.0],
            ],
            'landing_pages' => [
                ['path' => '/common-problems/check-engine-light', 'impressions' => 1200, 'clicks' => 14, 'position' => 6.1],
                ['path' => '/common-problems/brake-noise', 'impressions' => 890, 'clicks' => 8, 'position' => 5.2],
            ],
        ],
    ],
];
