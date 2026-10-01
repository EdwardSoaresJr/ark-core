<?php

test('public core has no turnkey Twilio SMS sender or webhook controller', function () {
    expect(class_exists('App\\Ark\\Operations\\Messaging\\TwilioMessagingSender'))->toBeFalse()
        ->and(class_exists('App\\Ark\\Operations\\Messaging\\MessagingWebhookController'))->toBeFalse()
        ->and(file_exists(base_path('app/Ark/Operations/Messaging/TwilioMessagingSender.php')))->toBeFalse();

    $envExample = file_get_contents(base_path('.env.example'));
    expect($envExample)->not->toMatch('/^TWILIO_ACCOUNT_SID=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_AUTH_TOKEN=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_PHONE_NUMBER=/m');
});

test('outbound sms transport is the platform texting adapter', function () {
    expect(app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class))
        ->toBeInstanceOf(\App\Ark\Texting\PlatformOutboundSmsTransport::class);
});
