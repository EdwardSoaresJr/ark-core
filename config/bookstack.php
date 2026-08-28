<?php

return [
    'base_url' => rtrim(env('BOOKSTACK_URL', 'https://learn.demo-auto.test'), '/'),

    'cutover' => (bool) env('BOOKSTACK_CUTOVER', false),

    'shelf_slug' => env('BOOKSTACK_SHELF_SLUG', 'shop-in-a-box'),

    'api_token_id' => env('BOOKSTACK_API_TOKEN_ID'),

    'api_token_secret' => env('BOOKSTACK_API_TOKEN_SECRET'),

    'api_token_name' => env('BOOKSTACK_API_TOKEN_NAME', 'ARK Import Service'),

    'default_author_ark_user_id' => (int) env('BOOKSTACK_DEFAULT_AUTHOR_ARK_USER_ID', 1),

    'shelf_name' => env('BOOKSTACK_SHELF_NAME', 'Shop In A Box'),

    'shelf_description' => 'ARKademy base curriculum — distributable across shops.',

    'role_books' => [
        'owner' => [
            'name' => 'Owner Excellence',
            'description' => 'Daily rhythm, financial targets, and owner reporting.',
        ],
        'advisor' => [
            'name' => 'Advisor Operations',
            'description' => 'Service advisor training, workflow, and floor SOPs.',
        ],
        'technician' => [
            'name' => 'Technician Operations',
            'description' => 'Technician production, inspection, and RO discipline.',
        ],
        'admin' => [
            'name' => 'Admin Setup',
            'description' => 'Shop configuration, integrations, and staff setup.',
        ],
    ],
];
