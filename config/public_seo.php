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
        'title' => 'Auto Repair Demo City | Verified Diagnostics',
        'title_suffix' => 'Demo City Auto Repair',
        'description' => 'Demo City auto repair with testing and live data before parts. 24-month shop warranty on qualifying work. Book an appointment online.',
    ],

    'book' => [
        'title' => 'Book an Appointment',
        'description' => 'Request an appointment at Demo Auto Repair in Demo City. We verify the problem, provide a clear estimate, and repair only what you approve.',
    ],

    'thanks' => [
        'title' => 'Thanks — we received your message',
        'description' => 'Your message reached the shop. We’ll review it and follow up during business hours.',
        'robots' => 'noindex, follow',
    ],

    'financing' => [
        'title' => 'Financing for Auto Repair',
        'description' => 'Repair financing through Wisetack and Synchrony Car Care at Demo Auto Repair in Demo City.',
    ],

    'warranty' => [
        'title' => 'Repair Warranty',
        'description' => '24 month / 24,000 mile shop warranty on qualifying parts and labor at Demo Auto Repair in Demo City.',
    ],

    'contact' => [
        'title' => 'Contact',
        'description' => 'Call, text, or request service at Demo Auto Repair in Demo City. Phone number, shop address, business hours, directions, and online intake.',
    ],

    'repairpal' => [
        'title' => 'RepairPal',
        'description' => 'RepairPal Certified shop in Demo City — certification, independent reviews, and nationwide warranty explained.',
    ],

    'repairpal_certified' => [
        'title' => 'RepairPal Certified',
        'description' => 'What RepairPal Certified means at Demo Auto Repair in Demo City — independent review, how we diagnose, and how to check our listing.',
    ],

    'repairpal_reviews' => [
        'title' => 'RepairPal Reviews',
        'description' => 'RepairPal reviews at Demo Auto Repair — independent feedback alongside Google reviews, with a link to our live profile.',
    ],

    'repairpal_warranty' => [
        'title' => 'RepairPal Nationwide Warranty',
        'description' => 'RepairPal Certified warranty is 12 months / 12,000 miles on qualifying repairs at Demo Auto Repair in Demo City.',
    ],

    'privacy' => [
        'title' => 'Privacy Policy',
        'description' => 'How Demo Auto Repair collects and uses information from the website and My Account.',
    ],

    'terms' => [
        'title' => 'Terms of Use',
        'description' => 'Terms for using the Demo Auto Repair website and My Account.',
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
