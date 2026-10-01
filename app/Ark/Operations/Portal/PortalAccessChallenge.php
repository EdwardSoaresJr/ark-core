<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'customer_id',
    'channel',
    'destination',
    'code_hash',
    'attempts',
    'expires_at',
    'used_at',
])]
class PortalAccessChallenge extends Model
{
    public static function hashCode(string $plainCode): string
    {
        return hash('sha256', trim($plainCode));
    }

    protected function casts(): array
    {
        return [
            'channel' => PortalAccessChannel::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
