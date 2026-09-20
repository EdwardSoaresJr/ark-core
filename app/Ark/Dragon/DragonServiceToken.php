<?php

namespace App\Ark\Dragon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dedicated machine credential for Dragon — not a staff User / Sanctum PAT.
 *
 * Plaintext token is shown once at issue time; only the hash is stored.
 */
class DragonServiceToken extends Model
{
    protected $table = 'dragon_service_tokens';

    protected $fillable = [
        'name',
        'token_prefix',
        'token_hash',
        'shop_identity',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return array{token: DragonServiceToken, plain_text: string}
     */
    public static function issue(string $name, ?string $shopIdentity = null): array
    {
        $plain = 'drg_'.Str::random(48);
        $prefix = substr($plain, 0, 12);

        $token = static::query()->create([
            'name' => $name,
            'token_prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
            'shop_identity' => $shopIdentity ?: (string) config('shop.identity'),
        ]);

        return ['token' => $token, 'plain_text' => $plain];
    }

    public static function findActiveByPlainText(string $plainText): ?self
    {
        $hash = hash('sha256', $plainText);

        /** @var self|null $token */
        $token = static::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        return $token;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function belongsToCurrentShop(): bool
    {
        return hash_equals((string) config('shop.identity'), (string) $this->shop_identity);
    }

    public function revoke(): void
    {
        if ($this->revoked_at !== null) {
            return;
        }

        $this->forceFill(['revoked_at' => Carbon::now()])->save();
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => Carbon::now()])->save();
    }

    public function auditLabel(): string
    {
        return sprintf('dragon:%s:%s', $this->id, $this->token_prefix);
    }
}
