<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function enableHostedPlatformParts(?string $shopPublicId = null): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    config()->set('services.ark_platform.parts_catalog', true);
    config()->set('services.partstech.username', 'leftover-shop');
    config()->set('services.partstech.password', 'leftover-password');
    config()->set('services.partstech.api_key', 'leftover-key');
    config()->set('services.partstech.base_url', 'https://partstech.test');

    $shopPublicId ??= (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-parts-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => $shopPublicId,
    ]);

    return $installationUuid;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformPartsReadinessPayload(array $overrides = []): array
{
    return array_merge([
        'ok' => true,
        'ready' => true,
        'transport' => 'partstech',
        'reason_code' => null,
        'message' => 'Parts catalog is ready.',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformPartsPreparePayload(string $cartReference, array $overrides = []): array
{
    return array_merge([
        'ok' => true,
        'prepared' => true,
        'catalog_url' => 'https://app.partstech.com?poNumber='.$cartReference,
        'cart_reference' => $cartReference,
        'po_number' => $cartReference,
        'repair_order_number' => ltrim($cartReference, 'R'),
        'partstech_login' => 'ark-shop',
        'partstech_login_source' => 'shop',
        'warnings' => [],
        'message' => null,
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $lines
 * @return array<string, mixed>
 */
function platformPartsQuotePayload(string $cartReference, array $lines = []): array
{
    return [
        'ok' => true,
        'cart_reference' => $cartReference,
        'lines' => $lines,
        'transport' => 'partstech',
    ];
}

/**
 * @param  array<string, mixed>  $readiness
 * @param  array<string, mixed>|callable|null  $prepare
 * @param  array<string, mixed>|callable|null  $quote
 */
function fakeHostedPartsPlatform(array $readiness = [], mixed $prepare = null, mixed $quote = null, bool $preventStray = true): void
{
    if ($preventStray) {
        Http::preventStrayRequests();
    }

    Http::fake(function (Request $request) use ($readiness, $prepare, $quote) {
        $url = $request->url();

        expect($url)->not->toContain('partstech.test');

        if (str_contains($url, '/api/v1/services/parts/readiness')) {
            $payload = platformPartsReadinessPayload($readiness);
            $status = (int) ($readiness['http_status'] ?? (($payload['ok'] ?? true) ? 200 : 403));

            return Http::response($payload, $status);
        }

        if ($request->method() === 'POST' && str_contains($url, '/api/v1/services/parts/sessions/prepare')) {
            if (is_callable($prepare)) {
                return $prepare($request);
            }

            $json = $request->data();
            $cartReference = (string) ($json['cart_reference'] ?? 'R0');
            $payload = is_array($prepare)
                ? platformPartsPreparePayload($cartReference, $prepare)
                : platformPartsPreparePayload($cartReference);
            $status = (int) ($payload['http_status'] ?? 200);
            unset($payload['http_status']);

            return Http::response($payload, $status);
        }

        if (str_contains($url, '/quote')) {
            if (is_callable($quote)) {
                return $quote($request);
            }

            $json = $request->data();
            $cartReference = (string) ($json['cart_reference'] ?? basename(dirname($url)));
            $payload = is_array($quote)
                ? $quote
                : platformPartsQuotePayload($cartReference);
            $status = (int) ($payload['http_status'] ?? 200);
            unset($payload['http_status']);

            return Http::response($payload, $status);
        }

        return Http::response(['ok' => false, 'reason_code' => 'unexpected'], 500);
    });
}
