<?php

namespace App\Ark\Runtime\Identity\Oidc;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OidcSigningKey extends Model
{
    protected $fillable = [
        'kid',
        'algorithm',
        'revoked_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @param  Builder<OidcSigningKey>  $query
     * @return Builder<OidcSigningKey>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * @param  Builder<OidcSigningKey>  $query
     * @return Builder<OidcSigningKey>
     */
    public function scopeActiveForSigning(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }
}
