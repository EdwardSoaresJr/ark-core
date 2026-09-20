<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use Carbon\CarbonImmutable;

/**
 * Next shop-open morning at 08:00 local — skips closed weekdays (e.g. Sat night → Monday).
 */
final class TomorrowMorningSchedule
{
    public const LOCAL_HOUR = 8;

    public const LOCAL_MINUTE = 0;

    public const LOOKAHEAD_DAYS = 21;

    public const DEFAULT_UPCOMING_COUNT = 5;

    public static function nextInstant(?CarbonImmutable $now = null, ?string $timezone = null): CarbonImmutable
    {
        $mornings = self::upcomingOpenMornings(1, $now, $timezone);

        if ($mornings === []) {
            // Misconfigured hours: fall back to next calendar 08:00.
            $timezone ??= ShopDisplayTimezone::resolve();
            $localNow = ($now ?? CarbonImmutable::now('UTC'))->timezone($timezone);
            $candidate = $localNow->setTime(self::LOCAL_HOUR, self::LOCAL_MINUTE, 0);

            if ($localNow->greaterThanOrEqualTo($candidate)) {
                $candidate = $candidate->addDay();
            }

            return $candidate->utc();
        }

        return CarbonImmutable::parse($mornings[0]['scheduled_for'])->utc();
    }

    /**
     * @return list<array{scheduled_for: string, label: string, day_key: string}>
     */
    public static function upcomingOpenMornings(
        int $limit = self::DEFAULT_UPCOMING_COUNT,
        ?CarbonImmutable $now = null,
        ?string $timezone = null,
    ): array {
        $flow = TelephonyCallFlowSettings::fromShopSettings();
        $timezone ??= $flow->timezone();
        $localNow = ($now ?? CarbonImmutable::now('UTC'))->timezone($timezone);
        $candidate = $localNow->setTime(self::LOCAL_HOUR, self::LOCAL_MINUTE, 0);

        if ($localNow->greaterThanOrEqualTo($candidate)) {
            $candidate = $candidate->addDay();
        }

        $limit = max(1, min(14, $limit));
        $slots = [];

        for ($i = 0; $i < self::LOOKAHEAD_DAYS && count($slots) < $limit; $i++) {
            if ($flow->isOpenDay($candidate, $timezone)) {
                $utc = $candidate->utc();
                $slots[] = [
                    'scheduled_for' => $utc->toIso8601String(),
                    'label' => ShopDisplayTimezone::format($utc, 'D M j · g:i A') ?? $candidate->format('D M j · g:i A'),
                    'day_key' => $candidate->toDateString(),
                ];
            }

            $candidate = $candidate->addDay()->setTime(self::LOCAL_HOUR, self::LOCAL_MINUTE, 0);
        }

        return $slots;
    }

    public static function isAllowedOpenMorning(
        CarbonImmutable $scheduledFor,
        ?CarbonImmutable $now = null,
        ?string $timezone = null,
    ): bool {
        $targetDate = $scheduledFor->timezone($timezone ?? ShopDisplayTimezone::resolve())->toDateString();

        foreach (self::upcomingOpenMornings(14, $now, $timezone) as $slot) {
            if ($slot['day_key'] === $targetDate) {
                return true;
            }
        }

        return false;
    }

    public static function resolveAllowedInstant(
        ?string $scheduledForIso,
        ?CarbonImmutable $now = null,
        ?string $timezone = null,
    ): CarbonImmutable {
        if (! filled($scheduledForIso)) {
            return self::nextInstant($now, $timezone);
        }

        try {
            $parsed = CarbonImmutable::parse($scheduledForIso)->utc();
        } catch (\Throwable) {
            throw new \RuntimeException('Choose a morning when the shop is open.');
        }

        $timezone ??= ShopDisplayTimezone::resolve();
        $local = $parsed->timezone($timezone)->setTime(self::LOCAL_HOUR, self::LOCAL_MINUTE, 0);

        if (! self::isAllowedOpenMorning($local->utc(), $now, $timezone)) {
            throw new \RuntimeException('Choose a morning when the shop is open.');
        }

        return $local->utc();
    }
}
