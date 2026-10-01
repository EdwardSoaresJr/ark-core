<?php

namespace App\Ark\Auth;

final class OfflineRecoveryVerifier
{
    /**
     * @return array{v: int, purpose: string, installation_uuid: string, challenge: string, exp: int, jti: string}
     */
    public function verify(string $artifact): array
    {
        $public = $this->publicKey();
        $split = OfflineRecoveryAuthorization::splitArtifact($artifact);

        if (! sodium_crypto_sign_verify_detached($split['signature'], $split['payload'], $public)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authorization' => 'This authorization is not valid.',
            ]);
        }

        try {
            /** @var array{v?: mixed, purpose?: mixed, installation_uuid?: mixed, challenge?: mixed, exp?: mixed, jti?: mixed} $claims */
            $claims = json_decode($split['payload'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authorization' => 'This authorization is not valid.',
            ]);
        }

        if (($claims['v'] ?? null) !== OfflineRecoveryAuthorization::VERSION
            || ($claims['purpose'] ?? null) !== OfflineRecoveryAuthorization::PURPOSE
            || ! is_string($claims['installation_uuid'] ?? null)
            || ! is_string($claims['challenge'] ?? null)
            || ! is_string($claims['jti'] ?? null)
            || ! is_int($claims['exp'] ?? null)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authorization' => 'This authorization is not valid.',
            ]);
        }

        if ($claims['exp'] < time()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authorization' => 'This authorization has expired.',
            ]);
        }

        return [
            'v' => $claims['v'],
            'purpose' => $claims['purpose'],
            'installation_uuid' => $claims['installation_uuid'],
            'challenge' => $claims['challenge'],
            'exp' => $claims['exp'],
            'jti' => $claims['jti'],
        ];
    }

    private function publicKey(): string
    {
        $encoded = (string) config('services.ark_cloud.offline_recovery_public_key');
        $public = OfflineRecoveryAuthorization::base64UrlDecode($encoded);

        if (strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'authorization' => 'Offline recovery is not configured on this Box.',
            ]);
        }

        return $public;
    }
}
