<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\AppointmentsBoardProjection;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('appointments board eager-loads appointment technician to avoid N+1', function () {
    $technician = User::factory()->create(['name' => 'Tech One']);
    $customer = Customer::query()->create(['first_name' => 'Pat', 'last_name' => 'Owner', 'phone' => '555-0111']);
    $vehicle = Vehicle::query()->create(['customer_id' => $customer->id, 'year' => 2020, 'make' => 'Ford', 'model' => 'F-150']);

    for ($i = 0; $i < 5; $i++) {
        Appointment::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'technician_user_id' => $technician->id,
            'status' => AppointmentStatus::Scheduled,
            'concern' => 'Board eager-load coverage '.$i,
            'starts_at' => now()->addHours($i + 1),
            'ends_at' => now()->addHours($i + 2),
        ]);
    }

    $userQueries = 0;
    $listener = function (QueryExecuted $query) use (&$userQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, ' from `users` ') || str_contains($sql, ' from "users" ')) {
            $userQueries++;
        }
    };

    Event::listen(QueryExecuted::class, $listener);
    $rows = app(AppointmentsBoardProjection::class)->upcoming(20);
    Event::forget(QueryExecuted::class);

    expect($rows)->toHaveCount(5)
        ->and(collect($rows)->pluck('technician_label')->unique()->all())->toBe(['Tech One'])
        // One eager users query for technicians (assignedTechnician may add another), not one per row.
        ->and($userQueries)->toBeLessThan(5);
});
