<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Disposable projection: which calendar days /book may offer for appointment requests.
 */
final class AppointmentRequestAvailabilityProjection
{
    /**
     * @return array{
     *     accepting_requests: bool,
     *     horizon_days: int,
     *     minimum_notice_days: int,
     *     dates: list<array{date: string, label: string, weekday: string}>,
     *     periods: list<array{value: string, label: string}>,
     *     empty_message: string|null
     * }
     */
    public function forBook(?ShopSettings $settings = null, ?CarbonInterface $now = null): array
    {
        $settings ??= ShopSettings::current();
        $config = AppointmentRequestAvailability::forShop($settings);
        $timezone = ShopDisplayTimezone::resolve();
        $now = ($now ?? ShopDisplayTimezone::now())->copy()->timezone($timezone);

        $dates = $this->requestableDates($config, $timezone, $now);

        return [
            'accepting_requests' => $dates !== [],
            'horizon_days' => $config['horizon_days'],
            'minimum_notice_days' => $config['minimum_notice_days'],
            'dates' => $dates,
            'periods' => AppointmentRequestAvailability::enabledPeriodOptions($settings),
            'request_windows' => $config['request_windows'] ?? ScheduleRequestWindows::defaults(),
            'empty_message' => $dates === []
                ? 'We’re not accepting online appointment requests right now. Call or text us and we’ll help you find a time.'
                : null,
        ];
    }

    /**
     * Whether a specific shop-local calendar day currently accepts public requests.
     *
     * @param  array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int
     * }|null  $config
     */
    public function isRequestableDate(
        string $dateYmd,
        ?array $config = null,
        ?ShopSettings $settings = null,
        ?CarbonInterface $now = null,
    ): bool {
        $settings ??= ShopSettings::current();
        $config ??= AppointmentRequestAvailability::forShop($settings);
        $timezone = ShopDisplayTimezone::resolve();
        $now = ($now ?? ShopDisplayTimezone::now())->copy()->timezone($timezone);

        foreach ($this->requestableDates($config, $timezone, $now) as $row) {
            if ($row['date'] === $dateYmd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effective requestability for a calendar day ignoring horizon/notice (weekly + exception).
     * Used by Schedule day staff controls.
     *
     * @return array{requestable: bool, weekly_enabled: bool, exception: AppointmentRequestException|null}
     */
    public function dayStatus(string $dateYmd, ?ShopSettings $settings = null): array
    {
        $settings ??= ShopSettings::current();
        $config = AppointmentRequestAvailability::forShop($settings);
        $timezone = ShopDisplayTimezone::resolve();
        $day = Carbon::parse($dateYmd, $timezone)->startOfDay();
        $weekday = strtolower($day->englishDayOfWeek);
        $weeklyEnabled = (bool) ($config['weekly'][$weekday]['enabled'] ?? false);
        $exception = AppointmentRequestException::query()
            ->whereDate('date', $dateYmd)
            ->first();

        $requestable = $weeklyEnabled;
        if ($exception instanceof AppointmentRequestException) {
            $requestable = $exception->enablesRequests();
        }

        return [
            'requestable' => $requestable,
            'weekly_enabled' => $weeklyEnabled,
            'exception' => $exception,
        ];
    }

    /**
     * @param  array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int
     * }  $config
     * @return list<array{date: string, label: string, weekday: string}>
     */
    private function requestableDates(array $config, string $timezone, CarbonInterface $now): array
    {
        $horizon = (int) $config['horizon_days'];
        $notice = (int) $config['minimum_notice_days'];
        $start = $now->copy()->startOfDay()->addDays($notice);
        $end = $now->copy()->startOfDay()->addDays(max(0, $horizon - 1));

        if ($end->lessThan($start)) {
            return [];
        }

        $exceptions = AppointmentRequestException::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (AppointmentRequestException $row): string => $row->date->toDateString());

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $ymd = $cursor->toDateString();
            $weekday = strtolower($cursor->englishDayOfWeek);
            $weeklyEnabled = (bool) ($config['weekly'][$weekday]['enabled'] ?? false);
            /** @var AppointmentRequestException|null $exception */
            $exception = $exceptions->get($ymd);

            $requestable = $weeklyEnabled;
            if ($exception instanceof AppointmentRequestException) {
                $requestable = $exception->enablesRequests();
            }

            if ($requestable) {
                $dates[] = [
                    'date' => $ymd,
                    'label' => $cursor->format('l, F j'),
                    'weekday' => $weekday,
                ];
            }

            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
