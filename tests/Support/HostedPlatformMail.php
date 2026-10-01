<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Voice\ManagedVoiceGate;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function enableHostedPlatformMail(?string $shopPublicId = null): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    config()->set('services.ark_platform.mail_send', true);

    $shopPublicId ??= (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-mail-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => $shopPublicId,
        'ark_mail_from_email' => 'shop@example.test',
        'ark_mail_status' => 'connected',
    ]);

    Http::fake([
        'cloud.test/api/v1/status' => Http::response([
            'ok' => true,
            'services' => [
                ['key' => 'voice', 'label' => 'ARK Voice', 'status' => 'not_enabled', 'status_label' => 'Not enabled', 'detail' => null, 'runtime_owner' => 'core'],
            ],
        ], 200),
    ]);

    ManagedVoiceGate::resetMemo();

    return $installationUuid;
}

function platformMailBody(Request $request): array
{
    $body = $request->data();
    if ($body === []) {
        $decoded = json_decode($request->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    return $body;
}

function assertPlatformTransactionalMail(string $operation, string $to, ?string $needle = null): void
{
    Http::assertSent(function (Request $request) use ($operation, $to, $needle): bool {
        if (! str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return false;
        }

        $body = platformMailBody($request);
        if (($body['operation'] ?? null) !== $operation || ($body['to'] ?? null) !== $to) {
            return false;
        }

        if ($needle === null) {
            return true;
        }

        $haystack = json_encode($body['variables'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str_contains((string) $haystack, $needle)
            && ! array_key_exists('html_body', $body)
            && ! array_key_exists('subject', $body);
    });
}

function usePlatformPortalMail(): void
{
    enableHostedPlatformMail();
    fakeHostedPlatformMail();
}

function recordedPortalSignInCode(): string
{
    $code = null;
    Http::assertSent(function (Request $request) use (&$code): bool {
        $body = platformMailBody($request);
        $found = $body['variables']['code'] ?? null;
        if (($body['operation'] ?? null) !== 'portal.sign_in' || ! is_string($found) || $found === '') {
            return false;
        }
        $code = $found;

        return true;
    });

    return $code;
}

function fakePermissivePlatformMail(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-test-1',
            ], 200);
        }

        return Http::response([], 200);
    });
}

function fakeHostedPlatformMail(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-test-1',
            ], 200);
        }

        return Http::response(['unexpected' => true, 'url' => $url], 599);
    });
}
