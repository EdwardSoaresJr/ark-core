<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OfflineRecoveryChallenge extends Model
{
    protected $fillable = [
        'public_id',
        'installation_uuid',
        'expires_at',
        'consumed_at',
        'consumed_jti',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isUsable(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->consumed_at !== null) {
            return false;
        }

        return $this->expires_at instanceof Carbon && $this->expires_at->greaterThan($now);
    }
}
