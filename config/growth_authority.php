<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authority — effort vs earned (not Google's algorithm)
    |--------------------------------------------------------------------------
    |
    | Effort = inputs the shop controls. Earned = outputs the market returns.
    | Review requests are effort, not earned authority.
    |
    */
    'ledger_weights' => [
        'google_review' => 1.0,
        'new_customer_acquired' => 1.0,
        'new_customer_from_growth_session' => 1.0,
        'returning_customer_visit' => 0.5,
        'gbp_photo_added' => 0.1,
        'gbp_post_published' => 0.05,
        'local_backlink' => 3.0,
    ],

    'targets' => [
        'reviews_per_month' => 12,
        'review_capture_rate' => 0.20,
        'review_request_rate' => 1.0,
        'new_customer_mix_min' => 0.25,
    ],

    'pressure' => [
        'missed_review_opportunity_days' => 30,
        'pending_review_followup_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Why should I care? — owner language for every projection row
    |--------------------------------------------------------------------------
    */
    'why_care' => [
        'missed_review_opportunities' => 'Customers who leave without being asked cannot strengthen your public reputation. Over time this reduces local authority and makes it harder for new customers to choose your shop.',
        'review_request_rate' => 'If ARK cannot see review asks at close, you cannot measure whether reputation capture is improving — and missed opportunities stay invisible.',
        'review_velocity' => 'Reviews are evidence of market trust. When count stalls, selection pressure rises even if operational quality stays high.',
        'new_customer_mix' => 'A shop that only serves repeat customers may be excellent operationally but invisible to new drivers choosing a mechanic.',
        'brand_searches' => 'Brand searches mean people already know your name — they are choosing whether to call you or someone else.',
        'call_clicks' => 'Maps call clicks are selection in the moment — did searchers pick up the phone and choose your shop?',
        'business_direction_requests' => 'Direction requests mean someone is physically choosing to drive to your bay — not just browsing.',
        'website_ctr' => 'Click-through is the first selection gate on Google Search — before they ever see your shop or read a review.',
    ],
];
