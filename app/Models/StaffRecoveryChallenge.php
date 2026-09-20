<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StaffRecoveryChallenge extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashCode(string $plainCode): string
    {
        return hash('sha256', trim($plainCode));
    }

    public function isUsable(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->used_at !== null) {
            return false;
        }

        if ($this->attempts >= 5) {
            return false;
        }

        return $this->expires_at instanceof Carbon && $this->expires_at->greaterThan($now);
    }
}
