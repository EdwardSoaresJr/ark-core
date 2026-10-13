<?php

use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

test('public core does not ship a twilio account or messaging webhook', function () {
    expect(is_file(base_path('app/Ark/Texting/PlatformOutboundSmsTransport.php')))->toBeFalse()
        ->and(is_file(base_path('app/Ark/Texting/ArkTextingClient.php')))->toBeFalse()
        ->and(is_dir(base_path('app/Ark/Texting')))->toBeFalse()
        ->and(Route::has('webhooks.twilio.messaging.inbound'))->toBeFalse()
        ->and(Route::has('webhooks.twilio.messaging.status'))->toBeFalse();

    $envExample = file_get_contents(base_path('.env.example'));
    expect($envExample)->not->toMatch('/^TWILIO_ACCOUNT_SID=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_AUTH_TOKEN=/m')
        ->and($envExample)->not->toMatch('/^TWILIO_PHONE_NUMBER=/m');
});

test('stock core leaves outbound sms unconfigured and does not call a provider', function () {
    Http::fake();

    $transport = app(OutboundSmsTransport::class);

    expect($transport)->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and($transport->isConfigured())->toBeFalse();

    expect(fn () => $transport->send('+17195550100', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    Http::assertNothingSent();
});
