<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonImmutable;

final class MissedCallRescueCopy
{
    public const DEFAULT_OPEN = 'Hey! This is {{business.name}}. Sorry we missed your call — reply with your year/make/model and what it\'s doing, or text us to get scheduled.';

    public const DEFAULT_CLOSED = 'Hey! This is {{business.name}}. Sorry we missed your call — we\'re currently closed. Reply with your year/make/model and what it\'s doing and we\'ll get back to you first thing.';

    public static function bodyFor(
        CallSession $session,
        TelephonyCallFlowSettings $flow,
        ?ShopSettings $settings = null,
    ): string {
        $settings ??= ShopSettings::current();
        $moment = self::momentFor($session, $flow);
        $isOpen = $flow->isOpenAt($moment);
        $template = $isOpen
            ? ($flow->missedCallRescueTextOpen() !== '' ? $flow->missedCallRescueTextOpen() : self::DEFAULT_OPEN)
            : ($flow->missedCallRescueTextClosed() !== '' ? $flow->missedCallRescueTextClosed() : self::DEFAULT_CLOSED);

        $caller = app(InboundCallerDisplayPhone::class)->normalizedForSession($session)
            ?? $session->normalized_from
            ?? $session->from_number;

        $shopName = trim((string) ($settings->shop_name ?? ''));
        if ($shopName === '') {
            $shopName = 'the shop';
        }

        return str_replace(
            ['{{business.name}}', '{{caller.number}}'],
            [
                $shopName,
                PhoneNumber::display($caller) ?? (string) $caller,
            ],
            $template,
        );
    }

    private static function momentFor(CallSession $session, TelephonyCallFlowSettings $flow): CarbonImmutable
    {
        if ($session->started_at !== null) {
            return CarbonImmutable::instance($session->started_at)->timezone($flow->timezone());
        }

        return CarbonImmutable::now($flow->timezone());
    }
}
