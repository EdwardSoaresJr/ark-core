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

    'sip_outbound' => [
        // North American area codes outside the US and Canada that desk phones may dial, comma separated.
        // Empty by default: Caribbean, Atlantic and Pacific NANP destinations stay blocked.
        'allowed_area_codes' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEPHONY_SIP_OUTBOUND_ALLOWED_AREA_CODES', ''))))),

        // Twilio starts a <Dial> recording when the called party answers, while the notice is still playing.
        // Keep this off until a live call shows nothing is recorded before the person called hears the notice.
        'recording' => (bool) env('TELEPHONY_SIP_OUTBOUND_RECORDING', false),
    ],

];
