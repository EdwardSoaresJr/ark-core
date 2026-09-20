<?php

namespace App\Ark\Runtime\Identity\Oidc;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class OidcKeyRepository
{
    public function activeSigningKey(): OidcSigningKey
    {
        $key = OidcSigningKey::query()->activeForSigning()->latest('id')->first();

        if ($key === null) {
            throw new OidcIssuerNotConfiguredException('No active OIDC signing key. Run ark:oidc:keys:create.');
        }

        return $key;
    }

    /**
     * @return list<OidcSigningKey>
     */
    public function publishedKeys(): array
    {
        return OidcSigningKey::query()->published()->orderBy('id')->get()->all();
    }

    public function createKeyPair(): OidcSigningKey
    {
        $pair = OidcRsaCryptography::generateKeyPair();
        $kid = 'ark-'.Str::lower(Str::random(12));

        $this->storePrivateKey($kid, $pair['private']);
        $this->storePublicKey($kid, $pair['public']);

        OidcSigningKey::query()->where('is_active', true)->update(['is_active' => false]);

        return OidcSigningKey::query()->create([
            'kid' => $kid,
            'algorithm' => 'RS256',
            'is_active' => true,
        ]);
    }

    public function rotateKeyPair(): OidcSigningKey
    {
        return $this->createKeyPair();
    }

    public function revokeKey(string $kid): OidcSigningKey
    {
        $key = OidcSigningKey::query()->where('kid', $kid)->firstOrFail();

        $key->update([
            'revoked_at' => now(),
            'is_active' => false,
        ]);

        return $key->fresh();
    }

    public function privateKeyPem(OidcSigningKey $key): string
    {
        $path = $this->privateKeyPath($key->kid);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new OidcIssuerNotConfiguredException("Missing private key for kid [{$key->kid}].");
        }

        return (string) $disk->get($path);
    }

    public function publicKeyPem(OidcSigningKey $key): string
    {
        $path = $this->publicKeyPath($key->kid);
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new OidcIssuerNotConfiguredException("Missing public key for kid [{$key->kid}].");
        }

        return (string) $disk->get($path);
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function jwksDocument(): array
    {
        $keys = [];

        foreach ($this->publishedKeys() as $signingKey) {
            try {
                $keys[] = OidcRsaCryptography::publicKeyToJwk(
                    $this->publicKeyPem($signingKey),
                    $signingKey->kid,
                    $signingKey->algorithm,
                );
            } catch (OidcIssuerNotConfiguredException) {
                continue;
            }
        }

        if ($keys === []) {
            throw new OidcIssuerNotConfiguredException('No publishable OIDC signing keys found.');
        }

        return ['keys' => $keys];
    }

    private function storePrivateKey(string $kid, string $pem): void
    {
        Storage::disk('local')->put($this->privateKeyPath($kid), $pem);
        $this->ensureKeyStoragePermissions();
    }

    private function storePublicKey(string $kid, string $pem): void
    {
        Storage::disk('local')->put($this->publicKeyPath($kid), $pem);
        $this->ensureKeyStoragePermissions();
    }

    private function ensureKeyStoragePermissions(): void
    {
        $root = Storage::disk('local')->path(trim(config('oidc.private_key_storage_path'), '/'));

        if (! is_dir($root)) {
            return;
        }

        @chmod($root, 0750);

        foreach (glob($root.'/*.public.pem') ?: [] as $path) {
            @chmod($path, 0644);
        }

        foreach (glob($root.'/*.private.pem') ?: [] as $path) {
            @chmod($path, 0640);

            if (function_exists('posix_getgrnam')) {
                $group = posix_getgrnam('www-data');

                if ($group !== false) {
                    @chgrp($path, $group['gid']);
                }
            }
        }
    }

    private function privateKeyPath(string $kid): string
    {
        return trim(config('oidc.private_key_storage_path'), '/')."/{$kid}.private.pem";
    }

    private function publicKeyPath(string $kid): string
    {
        return trim(config('oidc.private_key_storage_path'), '/')."/{$kid}.public.pem";
    }
}
