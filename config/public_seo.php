<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public surface SEO defaults (demo-auto.test)
    |--------------------------------------------------------------------------
    |
    | Page-specific overrides belong on controllers. These are shop-wide fallbacks.
    |
    */
    'home' => [
        'title' => 'Auto Repair Colorado Springs | Verified Diagnostics',
        'title_suffix' => 'Colorado Springs Auto Repair',
        'description' => 'Colorado Springs auto repair with testing and live data before parts. 24-month shop warranty on qualifying work. Book an appointment online.',
    ],

    'book' => [
        'title' => 'Book an Appointment',
        'description' => 'Request an appointment at LugsNPlugs in Colorado Springs. We verify the problem, provide a clear estimate, and repair only what you approve.',
    ],

    'thanks' => [
        'title' => 'Thanks — we received your message',
        'description' => 'Your message reached the shop. We’ll review it and follow up during business hours.',
        'robots' => 'noindex, follow',
    ],

    'financing' => [
        'title' => 'Financing for Auto Repair',
        'description' => 'Repair financing through Wisetack and Synchrony Car Care at LugsNPlugs in Colorado Springs.',
    ],

    'warranty' => [
        'title' => 'Repair Warranty',
        'description' => '24 month / 24,000 mile shop warranty on qualifying parts and labor at LugsNPlugs in Colorado Springs.',
    ],

    'contact' => [
        'title' => 'Contact',
        'description' => 'Call, text, or request service at LugsNPlugs in Colorado Springs. Phone number, shop address, business hours, directions, and online intake.',
    ],

    'repairpal' => [
        'title' => 'RepairPal',
        'description' => 'RepairPal Certified shop in Colorado Springs — certification, independent reviews, and nationwide warranty explained.',
    ],

    'repairpal_certified' => [
        'title' => 'RepairPal Certified',
        'description' => 'What RepairPal Certified means at LugsNPlugs in Colorado Springs — independent review, how we diagnose, and how to check our listing.',
    ],

    'repairpal_reviews' => [
        'title' => 'RepairPal Reviews',
        'description' => 'RepairPal reviews at LugsNPlugs — independent feedback alongside Google reviews, with a link to our live profile.',
    ],

    'repairpal_warranty' => [
        'title' => 'RepairPal Nationwide Warranty',
        'description' => 'RepairPal Certified warranty is 12 months / 12,000 miles on qualifying repairs at LugsNPlugs in Colorado Springs.',
    ],

    'privacy' => [
        'title' => 'Privacy Policy',
        'description' => 'How LugsNPlugs collects and uses information from the website and My Account.',
    ],

    'terms' => [
        'title' => 'Terms of Use',
        'description' => 'Terms for using the LugsNPlugs website and My Account.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap entries (path => daily change frequency hint)
    |--------------------------------------------------------------------------
    |
    | Common Problems and From The Bay URLs are registered automatically from authority.
    |
    */
    'sitemap_paths' => [
        '/' => 'weekly',
        '/book' => 'weekly',
        '/contact' => 'monthly',
        '/financing' => 'monthly',
        '/warranty' => 'monthly',
        '/repairpal' => 'monthly',
        '/repairpal-certified' => 'monthly',
        '/repairpal-reviews' => 'monthly',
        '/repairpal-warranty' => 'monthly',
    ],
];
