<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\TwilioOutboundSmsTransport;
use Ark\Saas\Provider\ProviderMode;
use Ark\Saas\SaasServiceProvider;
use Ark\Saas\ShopRef;
use Ark\Saas\Texting\TextAccounts;
use Ark\Saas\Texting\TextCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $saas = dirname(base_path()).'/ark-saas/saas';
    if (! is_file($saas.'/src/SaasServiceProvider.php')) {
        $this->markTestSkipped('ARK SaaS is not beside this checkout.');
    }

    spl_autoload_register(static function (string $class) use ($saas): void {
        $prefix = 'Ark\\Saas\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }
        $path = $saas.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    $this->app->register(SaasServiceProvider::class);
    $this->artisan('migrate', [
        '--path' => $saas.'/database/migrations',
        '--realpath' => true,
        '--force' => true,
    ]);
    InstallationIdentity::write((string) Str::uuid());
});

test('saas byo credentials are what core sends, and another shop is not used', function () {
    $accounts = app(TextAccounts::class);
    $accounts->saveByo(new ShopRef('other-shop'), new TextCredential(
        'https://api.twilio.com',
        'ACother',
        'token-other',
        '+17195550999',
    ));
    $accounts->saveByo(\Ark\Saas\Hosted\LocalShop::ref(), new TextCredential(
        'https://api.twilio.com',
        'ACthis',
        'token-this',
        '+17195550111',
    ));

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMsaas1', 'status' => 'queued'], 201),
    ]);

    $transport = app(OutboundSmsTransport::class);
    expect($transport)->toBeInstanceOf(TwilioOutboundSmsTransport::class);

    $result = $transport->send('+17195550199', 'From this shop', [], [
        ['type' => 'idempotency_key', 'id' => 'saas-send-1'],
    ]);

    expect($result->messageId)->toBe('SMsaas1');
    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/Accounts/ACthis/Messages.json')
            && ! str_contains($request->url(), 'ACother')
            && ! str_contains($request->url(), 'cloud.arksms.com');
    });
});

test('saas disabled mode blocks the core transport before any request', function () {
    $accounts = app(TextAccounts::class);
    $accounts->saveByo(\Ark\Saas\Hosted\LocalShop::ref(), new TextCredential(
        'https://api.twilio.com',
        'ACthis',
        'token-this',
        '+17195550111',
    ));
    $accounts->selectMode(\Ark\Saas\Hosted\LocalShop::ref(), ProviderMode::Disabled);

    Http::fake();

    expect(fn () => app(OutboundSmsTransport::class)->send('+17195550199', 'Nope'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    Http::assertNothingSent();
});
