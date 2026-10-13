<?php

test('public core texting does not read twilio secrets from the environment', function () {
    expect(class_exists('App\\Ark\\Operations\\Messaging\\TwilioMessagingSender'))->toBeFalse()
        ->and(file_exists(base_path('app/Ark/Operations/Messaging/TwilioMessagingSender.php')))->toBeFalse()
        ->and(class_exists('App\\Ark\\Operations\\Messaging\\TwilioOutboundSmsTransport'))->toBeTrue()
        ->and(class_exists('App\\Ark\\Operations\\Messaging\\TwilioMessagingWebhookController'))->toBeTrue();

    $envExample = file_get_contents(base_path('.env.example'));
    expect($envExample)->not->toMatch('/^TWILIO_ACCOUNT_SID=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_AUTH_TOKEN=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_PHONE_NUMBER=/m');
});

test('outbound sms transport is the native twilio adapter', function () {
    expect(app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class))
        ->toBeInstanceOf(\App\Ark\Operations\Messaging\TwilioOutboundSmsTransport::class)
        ->and(app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class))
        ->not->toBeInstanceOf(\App\Ark\Texting\PlatformOutboundSmsTransport::class);
});
