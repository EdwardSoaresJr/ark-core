<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Platform\Http\VerifyPlatformFabricSignature;
use App\Ark\Platform\PlatformConnection;
use Illuminate\Support\Str;

/**
 * @return array{0: string, 1: array<string, string>}
 */
function fabricSignedRequest(array $body, ?string $installationId = null, ?string $nonce = null, ?string $credential = null): array
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce ??= Str::random(24);
    $installationId ??= InstallationIdentity::uuid();
    $credential ??= (string) PlatformConnection::current()->credential();

    $signature = hash_hmac('sha256', implode("\n", [
        $timestamp,
        $nonce,
        'POST',
        VerifyPlatformFabricSignature::PATH,
        hash('sha256', $raw),
    ]), $credential);

    return [$raw, [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_ARK_INSTALLATION_ID' => $installationId,
        'HTTP_X_ARK_TIMESTAMP' => $timestamp,
        'HTTP_X_ARK_NONCE' => $nonce,
        'HTTP_X_ARK_SIGNATURE' => $signature,
    ]];
}
