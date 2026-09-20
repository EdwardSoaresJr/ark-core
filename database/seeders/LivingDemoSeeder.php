<?php

namespace Database\Seeders;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Settings\ApplyOperationalProfileDefaults;
use App\Ark\Operations\Settings\OperationalProfile;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Living Demo — busy Tuesday schedule for sales/demo.
 * Idempotent on living-demo* markers. Safe for local/testing only via reset command.
 */
class LivingDemoSeeder extends Seeder
{
    public const DEMO_EMAIL_PREFIX = 'living-demo+';

    public function run(): void
    {
        $settings = ShopSettings::current();
        $settings->forceFill([
            'appointments_enabled' => true,
            'scheduling_hours' => SchedulingHours::defaultWeekly(),
        ])->save();
        ShopSettings::forgetCurrent();

        app(ApplyOperationalProfileDefaults::class)->apply(OperationalProfile::RepairShop);
        ShopSettings::forgetCurrent();

        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->role(ArkRole::Advisor->value)->active()->first();
        $technician = User::query()->where('email', 'tech@ark.test')->first()
            ?? User::query()->role(ArkRole::Technician->value)->active()->first();

        if ($advisor === null) {
            $advisor = User::query()->create([
                'name' => 'Demo Advisor',
                'email' => 'living-demo+advisor@ark.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $advisor->assignRole(ArkRole::Advisor->value);
        }

        if ($technician === null) {
            $technician = User::query()->create([
                'name' => 'Demo Technician',
                'email' => 'living-demo+tech@ark.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $technician->assignRole(ArkRole::Technician->value);
        }

        $bay1 = Workstation::query()->updateOrCreate(
            ['name' => 'Bay 1', 'shop_settings_id' => $settings->id],
            [
                'location_label' => 'Bay 1',
                'is_active' => true,
                'accepts_scheduled_work' => true,
            ],
        );
        $bay2 = Workstation::query()->updateOrCreate(
            ['name' => 'Bay 2', 'shop_settings_id' => $settings->id],
            [
                'location_label' => 'Bay 2',
                'is_active' => true,
                'accepts_scheduled_work' => true,
            ],
        );

        Appointment::query()
            ->whereHas('customer', fn ($q) => $q->where('email', 'like', self::DEMO_EMAIL_PREFIX.'%'))
            ->delete();

        $timezone = (string) ($settings->shop_timezone ?: ShopSettings::INSTALL_DEFAULT_TIMEZONE);
        $tuesday = Carbon::now($timezone)->next(Carbon::TUESDAY)->startOfDay();

        if ($tuesday->isPast()) {
            $tuesday = $tuesday->addWeek();
        }

        $slots = [
            ['first' => 'Maria', 'last' => 'Chen', 'concern' => 'Brake vibration', 'start' => 8, 'hours' => 1.0, 'bay' => $bay1, 'labor' => 3.5],
            ['first' => 'James', 'last' => 'Ortiz', 'concern' => 'Oil change + inspection', 'start' => 9, 'hours' => 1.0, 'bay' => $bay2, 'labor' => 1.0],
            ['first' => 'Priya', 'last' => 'Shah', 'concern' => 'Check engine light', 'start' => 10, 'hours' => 2.0, 'bay' => $bay1, 'labor' => 3.0],
            ['first' => 'Chris', 'last' => 'Nguyen', 'concern' => 'AC not cold', 'start' => 13, 'hours' => 1.5, 'bay' => $bay2, 'labor' => 1.5],
            ['first' => 'Elena', 'last' => 'Brooks', 'concern' => 'Tire rotation', 'start' => 15, 'hours' => 1.0, 'bay' => $bay1, 'labor' => 2.5],
        ];

        foreach ($slots as $index => $slot) {
            $email = self::DEMO_EMAIL_PREFIX.$index.'@ark.test';
            $customer = Customer::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $slot['first'],
                    'last_name' => $slot['last'],
                    'phone' => '555-020'.(string) $index,
                ],
            );

            $vehicle = Vehicle::query()->updateOrCreate(
                ['vin' => 'LDVIN'.str_pad((string) $index, 12, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'year' => 2018 + ($index % 5),
                    'make' => ['Toyota', 'Ford', 'Honda', 'Chevy', 'Subaru'][$index % 5],
                    'model' => ['Camry', 'F-150', 'Civic', 'Silverado', 'Outback'][$index % 5],
                    'plate' => 'LD'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'plate_state' => 'CO',
                ],
            );

            $starts = Carbon::create(
                (int) $tuesday->format('Y'),
                (int) $tuesday->format('n'),
                (int) $tuesday->format('j'),
                $slot['start'],
                0,
                0,
                $timezone,
            );
            $ends = $starts->copy()->addMinutes((int) ($slot['hours'] * 60));

            Appointment::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'advisor_user_id' => $advisor->id,
                'technician_user_id' => $technician->id,
                'workstation_id' => $slot['bay']->id,
                'created_by_user_id' => $advisor->id,
                // Persist UTC wall-clock — Eloquent datetime casts do not convert TZ on write.
                'starts_at' => $starts->copy()->utc(),
                'ends_at' => $ends->copy()->utc(),
                'estimated_labor_hours' => $slot['labor'],
                'concern' => $slot['concern'],
                'notes' => 'Living Demo appointment — busy Tuesday.',
                'status' => AppointmentStatus::Confirmed,
            ]);
        }
    }
}
