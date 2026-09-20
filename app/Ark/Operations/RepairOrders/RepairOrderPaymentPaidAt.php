<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Carbon;

final class RepairOrderPaymentPaidAt
{
    public static function fromDateInput(?string $date): ?Carbon
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        return Carbon::parse($date, \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve())
            ->startOfDay()
            ->timezone(config('app.timezone'));
    }
}
