<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * A number that asked not to be texted (STOP, or the provider reporting it blocked us). Scoped to the number, so it
 * holds with or without a customer.
 */
class PhoneSmsOptOut extends Model
{
    protected $fillable = [
        'normalized_phone',
        'source',
        'provider_message_id',
        'error_code',
        'opted_out_at',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'opted_out_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public static function isActive(string $normalizedPhone): bool
    {
        $normalized = PhoneNumber::normalize($normalizedPhone);

        if ($normalized === null) {
            return false;
        }

        return self::query()
            ->where('normalized_phone', $normalized)
            ->whereNull('cleared_at')
            ->exists();
    }

    public static function record(
        string $phone,
        string $source,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
    ): void {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return;
        }

        self::query()->updateOrCreate(
            ['normalized_phone' => $normalized],
            [
                'source' => $source,
                'provider_message_id' => $providerMessageId,
                'error_code' => $errorCode,
                'opted_out_at' => now(),
                'cleared_at' => null,
            ],
        );
    }

    public static function clear(string $phone): void
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return;
        }

        self::query()
            ->where('normalized_phone', $normalized)
            ->whereNull('cleared_at')
            ->update(['cleared_at' => now()]);
    }
}
