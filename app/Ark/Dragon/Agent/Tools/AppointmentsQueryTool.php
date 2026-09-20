<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentsBoardProjection;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;

final class AppointmentsQueryTool implements DragonAgentTool
{
    public function __construct(private readonly AppointmentsBoardProjection $board) {}

    public function name(): string
    {
        return 'appointments.query';
    }

    public function description(): string
    {
        return 'Read-only shop appointments for today, tomorrow, upcoming, or no-shows. Use for who is coming in, unconfirmed appointments, or waiting-parts returns. Never schedules, cancels, or checks in.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'window' => [
                    'type' => 'string',
                    'enum' => ['today', 'tomorrow', 'upcoming', 'no_show'],
                    'description' => 'Calendar window. Default today.',
                ],
                'waiting_parts_return' => [
                    'type' => 'boolean',
                    'description' => 'Only ROs whose workflow is waiting_parts and have an upcoming appointment.',
                ],
                'unconfirmed_only' => [
                    'type' => 'boolean',
                ],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $window = (string) ($arguments['window'] ?? 'today');
        $waitingPartsReturn = (bool) ($arguments['waiting_parts_return'] ?? false);
        $unconfirmedOnly = (bool) ($arguments['unconfirmed_only'] ?? false);

        $rows = match ($window) {
            'tomorrow' => $this->board->comingInOn(ShopDisplayTimezone::now()->addDay()),
            'upcoming' => $this->board->upcoming(30),
            'no_show' => Appointment::query()
                ->with(['repairOrder:id,repair_order_id,status', 'vehicle:id,year,make,model'])
                ->where('status', AppointmentStatus::NoShow->value)
                ->orderByDesc('starts_at')
                ->limit(30)
                ->get()
                ->map(fn (Appointment $appointment): array => $this->board->row($appointment))
                ->all(),
            default => $this->board->comingInOn(ShopDisplayTimezone::now()),
        };

        if ($waitingPartsReturn) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['repair_order_status'] ?? null) === RepairOrderStatus::WaitingParts->value
                    && in_array($row['appointment_status'] ?? null, [
                        AppointmentStatus::Scheduled->value,
                        AppointmentStatus::Confirmed->value,
                    ], true),
            ));
        }

        if ($unconfirmedOnly) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['appointment_status'] ?? null) === AppointmentStatus::Scheduled->value,
            ));
        }

        return [
            'read_only' => true,
            'writes' => false,
            'window' => $window,
            'count' => count($rows),
            'appointments' => $rows,
        ];
    }
}
