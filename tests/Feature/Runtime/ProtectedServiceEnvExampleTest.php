<?php

it('does not advertise protected turnkey provider credentials in env example', function () {
    $example = file_get_contents(base_path('.env.example'));

    expect($example)->not->toBeFalse();

    $forbiddenKeys = [
        'TWILIO_ACCOUNT_SID',
        'TWILIO_AUTH_TOKEN',
        'TWILIO_PHONE_NUMBER',
        'TWILIO_API_KEY',
        'OPENAI_API_KEY',
        'POSTMARK_TOKEN',
        'POSTMARK_MESSAGE_STREAM',
        'META_MESSENGER_PAGE_ACCESS_TOKEN',
        'ANTHROPIC_API_KEY',
        'PARTSTECH_USERNAME',
        'PARTSTECH_API_KEY',
        'PARTSTECH_PASSWORD',
        'PARTSTECH_BASE_URL',
        'PARTSTECH_CATALOG_PATH',
    ];

    foreach ($forbiddenKeys as $key) {
        expect($example)->not->toContain($key.'=');
    }

    expect($example)->toContain('ARK Email is configured in Settings')
        ->and($example)->toContain('Do not paste provider API tokens into Core');
});
