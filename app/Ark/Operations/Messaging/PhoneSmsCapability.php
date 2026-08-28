<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Database\Eloquent\Model;

class PhoneSmsCapability extends Model
{
    protected $fillable = [
        'normalized_phone',
        'valid',
        'line_type',
        'carrier_name',
        'sms_capable',
        'reason',
        'checked_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'valid' => 'boolean',
            'sms_capable' => 'boolean',
            'checked_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public static function findByNormalizedPhone(?string $normalizedPhone): ?self
    {
        if ($normalizedPhone === null || $normalizedPhone === '') {
            return null;
        }

        return self::query()->where('normalized_phone', $normalizedPhone)->first();
    }

    public function blockReason(): ?string
    {
        if ($this->sms_capable) {
            return null;
        }

        $reason = trim((string) ($this->reason ?? ''));

        return $reason !== ''
            ? $reason
            : 'This number cannot receive SMS.';
    }
}
