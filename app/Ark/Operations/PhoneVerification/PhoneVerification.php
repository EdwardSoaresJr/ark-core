<?php

namespace App\Ark\Operations\PhoneVerification;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'phone_e164',
    'code_hash',
    'expires_at',
    'attempts_remaining',
    'send_count',
    'verified_at',
    'consumed_at',
    'created_ip',
    'user_agent',
])]
class PhoneVerification extends Model
{
    public static function hashCode(string $plainCode): string
    {
        return hash_hmac('sha256', trim($plainCode), (string) config('app.key'));
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts_remaining' => 'integer',
            'send_count' => 'integer',
        ];
    }

    public function isPending(): bool
    {
        return $this->verified_at === null
            && $this->consumed_at === null
            && $this->attempts_remaining > 0
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }
}
