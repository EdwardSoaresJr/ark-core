<?php

namespace App\Ark\Runtime\Identity\Oidc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OidcClient extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'client_secret',
        'redirect_uris',
        'required_product',
        'is_confidential',
    ];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'is_confidential' => 'boolean',
            'client_secret' => 'hashed',
        ];
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(OidcAuthorizationCode::class);
    }

    public function allowsRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirect_uris ?? [], true);
    }

    public function verifySecret(?string $secret): bool
    {
        if (! $this->is_confidential) {
            return true;
        }

        return filled($secret) && password_verify($secret, $this->client_secret);
    }
}
