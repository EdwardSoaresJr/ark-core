<?php

namespace App\Ark\Operations\Leads;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

final class LeadFormRenderStamp
{
    public const Missing = 'missing';

    public const Stale = 'stale';

    public const Ready = 'ready';

    public static function issue(?Carbon $renderedAt = null): string
    {
        $renderedAt ??= now();

        return Crypt::encryptString((string) $renderedAt->getTimestamp());
    }

    /**
     * @return array{status: string, rendered_at: ?Carbon}
     */
    public static function read(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return ['status' => self::Missing, 'rendered_at' => null];
        }

        try {
            $plain = Crypt::decryptString(trim($value));
        } catch (DecryptException) {
            return ['status' => self::Missing, 'rendered_at' => null];
        }

        if (! ctype_digit($plain)) {
            return ['status' => self::Missing, 'rendered_at' => null];
        }

        $renderedAt = Carbon::createFromTimestamp((int) $plain);

        if ($renderedAt->isFuture() || $renderedAt->lt(now()->subDay())) {
            return ['status' => self::Stale, 'rendered_at' => null];
        }

        return ['status' => self::Ready, 'rendered_at' => $renderedAt];
    }
}
