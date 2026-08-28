<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PortalShortLink extends Model
{
    protected $fillable = [
        'code',
        'destination_url',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isActive(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->expires_at === null || $this->expires_at->isAfter($at);
    }
}
