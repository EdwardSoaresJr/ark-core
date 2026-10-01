<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OidcAuthorizationCode extends Model
{
    protected $fillable = [
        'code',
        'user_id',
        'oidc_client_id',
        'redirect_uri',
        'code_challenge',
        'code_challenge_method',
        'scopes',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(OidcClient::class, 'oidc_client_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function matchesPkce(string $codeVerifier): bool
    {
        if ($this->code_challenge_method !== 'S256') {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return hash_equals($this->code_challenge, $computed);
    }
}
