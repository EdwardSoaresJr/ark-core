<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function enableHostedPlatformPayments(?string $shopPublicId = null): string
{
    $installationUuid = (string) Str::uuid();
    InstallationIdentity::write($installationUuid);

    config()->set('services.ark_platform.payments_capture', true);
    config()->set('services.square.application_id', null);
    config()->set('services.square.access_token', null);
    config()->set('services.square.location_id', null);
    config()->set('services.square.webhook_signature_key', null);

    $shopPublicId ??= (string) Str::uuid();

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-payments-credential',
        'platform_base_url' => 'https://cloud.test',
        'platform_shop_public_id' => $shopPublicId,
        'square_enabled' => false,
        'square_application_id' => null,
        'square_location_id' => null,
        'square_terminal_device_id' => null,
        'square_terminal_enabled' => false,
        'square_keyed_enabled' => false,
        'square_portal_pay_enabled' => false,
        'square_email_pay_enabled' => false,
    ]);

    return $installationUuid;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformPaymentReadinessPayload(array $overrides = []): array
{
    return array_merge([
        'ok' => true,
        'status' => 'connected',
        'provider' => 'square',
        'supports_terminal' => true,
        'supports_keyed' => true,
        'supports_portal' => true,
        'available_devices' => [
            [
                'device_ref' => 'front-counter',
                'label' => 'Front Counter',
                'ready' => true,
            ],
        ],
        'public_config' => [
            'application_id' => 'sandbox-sq0idb-public',
            'location_id' => 'LOC_PUBLIC',
            'environment' => 'sandbox',
            'web_payments_sdk_url' => 'https://sandbox.web.squarecdn.com/v1/square.js',
        ],
        'message' => null,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformCapturePendingPayload(string $idempotencyKey, string $attemptPublicId, int $amountCents = 15000, array $overrides = []): array
{
    return array_merge([
        'ok' => true,
        'status' => 'pending',
        'capture_id' => (string) Str::uuid(),
        'capture_attempt_public_id' => $attemptPublicId,
        'idempotency_key' => $idempotencyKey,
        'amount_cents' => $amountCents,
        'currency' => 'USD',
        'context_kind' => 'payment',
        'capture_method' => 'terminal',
        'provider' => 'square',
        'provider_payment_id' => null,
        'provider_refs' => ['terminal_checkout_id' => 'chk_hosted_1'],
        'reason_code' => null,
        'message' => null,
        'poll_after_ms' => 1500,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformCaptureKeyedSucceededPayload(string $idempotencyKey, string $attemptPublicId, int $amountCents = 15000, array $overrides = []): array
{
    return array_merge([
        'ok' => true,
        'status' => 'succeeded',
        'capture_id' => (string) Str::uuid(),
        'capture_attempt_public_id' => $attemptPublicId,
        'idempotency_key' => $idempotencyKey,
        'amount_cents' => $amountCents,
        'currency' => 'USD',
        'context_kind' => 'payment',
        'capture_method' => 'keyed',
        'provider' => 'square',
        'provider_payment_id' => 'pay_hosted_cnp_1',
        'provider_refs' => [],
        'reason_code' => null,
        'message' => null,
        'poll_after_ms' => null,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postSignedFabricEvent(string $operation, array $payload): TestResponse
{
    $installation = InstallationIdentity::uuid();
    $credential = (string) PlatformConnection::current()->credential();
    $body = json_encode([
        'operation' => $operation,
        'installation_id' => $installation,
        'occurred_at' => now()->toIso8601String(),
        'payload' => $payload,
    ], JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $body),
    ]), $credential);

    return test()->call(
        'POST',
        '/webhooks/cloud/fabric/events',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARK_INSTALLATION_ID' => $installation,
            'HTTP_X_ARK_TIMESTAMP' => $timestamp,
            'HTTP_X_ARK_NONCE' => $nonce,
            'HTTP_X_ARK_SIGNATURE' => $signature,
        ],
        $body,
    );
}
