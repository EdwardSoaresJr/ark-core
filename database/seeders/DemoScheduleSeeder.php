<?php

namespace Database\Seeders;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentKind;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $settings = ShopSettings::current();
        $settings->forceFill(['appointments_enabled' => true])->save();

        $bays = [];
        foreach (['Bay 1', 'Bay 2', 'Bay 3'] as $name) {
            $bays[] = Workstation::query()->updateOrCreate(
                ['name' => $name, 'shop_settings_id' => $settings->id],
                [
                    'location_label' => $name,
                    'is_active' => true,
                    'accepts_scheduled_work' => true,
                ],
            );
        }

        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->where('email', 'demo@arksms.com')->first();
        $technician = User::query()->where('email', 'tech@ark.test')->first()
            ?? $advisor;

        if ($advisor === null || $technician === null || $bays === []) {
            return;
        }

        $timezone = (string) ($settings->shop_timezone ?: 'America/Denver');
        $today = Carbon::now($timezone)->startOfDay();
        $days = [];
        $cursor = $today->copy();
        while (count($days) < 30) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        $booked = Appointment::query()
            ->whereNotNull('repair_order_id')
            ->pluck('repair_order_id');

        $orders = RepairOrder::query()
            ->whereNotIn('status', [
                RepairOrderStatus::Closed->value,
                RepairOrderStatus::Completed->value,
                RepairOrderStatus::Invoiced->value,
            ])
            ->when($booked->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $booked))
            ->orderBy('id')
            ->get();

        $dayForStatus = [
            RepairOrderStatus::InProgress->value => 0,
            RepairOrderStatus::ReadyForWork->value => 0,
            RepairOrderStatus::ReadyPickup->value => 0,
            RepairOrderStatus::QualityCheck->value => 0,
            RepairOrderStatus::Approved->value => 1,
            RepairOrderStatus::WaitingApproval->value => 1,
            RepairOrderStatus::Estimate->value => 2,
            RepairOrderStatus::Draft->value => 3,
            RepairOrderStatus::WaitingParts->value => 4,
        ];
        $hours = [9, 10, 11, 13, 15];
        $slotByDay = [];

        foreach ($orders as $order) {
            $status = $order->status->value ?? (string) $order->status;
            $dayIndex = $dayForStatus[$status] ?? 5;
            $slot = $slotByDay[$dayIndex] ?? 0;
            $slotByDay[$dayIndex] = $slot + 1;

            $day = $days[min(count($days) - 1, $dayIndex + intdiv($slot, count($hours)))];
            $hour = $hours[$slot % count($hours)];
            $bay = $bays[$slot % count($bays)];
            $start = Carbon::create($day->year, $day->month, $day->day, $hour, 0, 0, $timezone);
            $concern = trim((string) ($order->concern_summary ?? ''));
            $arrived = $day->isSameDay($today);
            $confirmed = in_array($status, [
                RepairOrderStatus::WaitingParts->value,
                RepairOrderStatus::Approved->value,
                RepairOrderStatus::WaitingApproval->value,
            ], true);

            Appointment::query()->create([
                'customer_id' => $order->customer_id,
                'vehicle_id' => $order->vehicle_id,
                'repair_order_id' => $order->id,
                'advisor_user_id' => $advisor->id,
                'technician_user_id' => $technician->id,
                'workstation_id' => $bay->id,
                'created_by_user_id' => $advisor->id,
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $start->copy()->addMinutes(90)->utc(),
                'estimated_labor_hours' => 1.5,
                'concern' => mb_substr($concern !== '' ? $concern : 'Scheduled visit', 0, 1000),
                'status' => $arrived
                    ? AppointmentStatus::Arrived
                    : ($confirmed ? AppointmentStatus::Confirmed : AppointmentStatus::Scheduled),
                'kind' => $status === RepairOrderStatus::WaitingParts->value
                    ? AppointmentKind::Return
                    : AppointmentKind::Intake,
                'arrived_at' => $arrived ? $start->copy()->utc() : null,
            ]);
        }
    }
}
