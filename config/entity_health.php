<?php

return [
    /*
    | Entity Health v1 — observe public identity, do not manage it.
    | Canonical category label for operator comparison when verifying audiences.
    */
    'primary_category' => 'Auto Repair Shop',

    'verification_stale_days' => 90,

    /** @var list<string> Legacy branding phrases to detect in ARK-controlled surfaces. */
    'legacy_identity_phrases' => [
        'Mobile Automotive',
    ],

    /** @var array<string, string> Audience surface key => operator label */
    'audience_surfaces' => [
        'google_business_profile' => 'Google Business Profile',
        'apple_business_connect' => 'Apple Business Connect',
        'facebook' => 'Facebook',
        'bing_places' => 'Bing Places',
        'repairpal' => 'RepairPal',
        'bbb' => 'BBB',
    ],
];
