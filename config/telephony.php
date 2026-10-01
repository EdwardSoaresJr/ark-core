<?php

return [

    'sip_provisioning' => [
        'default_password' => env('VOICE_SIP_DEFAULT_PASSWORD'),
        'passwords' => [
            '101' => env('PJSIP_101_PASSWORD'),
            '102' => env('PJSIP_102_PASSWORD'),
            'desk1' => env('VOICE_SIP_DESK1_PASSWORD'),
            'desk2' => env('VOICE_SIP_DESK2_PASSWORD'),
        ],
    ],

];
