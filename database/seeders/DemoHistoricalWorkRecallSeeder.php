<?php

namespace Database\Seeders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use Illuminate\Database\Seeder;

/**
 * LOCAL ONLY — Tacoma Front Brake historical recall fixtures.
 * Not registered in DatabaseSeeder.
 *
 * php artisan db:seed --class=DemoHistoricalWorkRecallSeeder
 */
class DemoHistoricalWorkRecallSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoHistoricalWorkRecallSeeder skipped outside local/testing.');

            return;
        }

        $this->call(DemoWorkTemplatesSeeder::class);

        $template = WorkTemplate::query()->where('title', 'Front Brake Service')->first();
        if ($template === null) {
            return;
        }

        $rows = [
            ['year' => 2017, 'drive' => '4wd', 'hours' => 3.0],
            ['year' => 2017, 'drive' => '4wd', 'hours' => 3.2],
            ['year' => 2017, 'drive' => '4wd', 'hours' => 3.4],
            ['year' => 2018, 'drive' => '4wd', 'hours' => 3.4], // nearby year → Likely vs 2017 current
            ['year' => 2017, 'drive' => '2wd', 'hours' => 2.4], // ambiguous 2WD → Possible vs 4WD current
        ];

        foreach ($rows as $index => $row) {
            $marker = 'Historical Recall Demo Tacoma '.$row['year'].' '.$row['drive'].' #'.($index + 1);
            if (Customer::query()->where('last_name', $marker)->exists()) {
                continue;
            }

            $customer = Customer::query()->create([
                'first_name' => 'Recall',
                'last_name' => $marker,
                'phone' => '555-'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
            ]);

            $vehicle = Vehicle::query()->create([
                'customer_id' => $customer->id,
                'year' => $row['year'],
                'make' => 'Toyota',
                'model' => 'Tacoma',
                'engine' => '3.5L',
                'engine_display' => '3.5L',
                'displacement_liters' => 3.5,
                'drivetrain' => $row['drive'],
                'drive' => match ($row['drive']) {
                    '4wd' => '4WD/4-Wheel Drive/4x4',
                    '2wd' => '2WD',
                    'fwd' => 'FWD',
                    'rwd' => 'RWD',
                    'awd' => 'AWD',
                    default => (string) $row['drive'],
                },
            ]);

            $ro = RepairOrder::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => RepairOrderStatus::Closed,
                'close_variant_key' => 'paid',
                'posted_at' => now()->subDays(30 - $index),
                'closed_at' => now()->subDays(30 - $index),
                'concern_summary' => 'Front brakes',
            ]);

            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $ro->id,
                'summary' => 'Front Brake Service',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'position' => 1,
            ]);

            $workGroup = $concern->workGroups()->create([
                'title' => 'Front Brake Service',
                'position' => 1,
                'created_from_template_id' => $template->id,
            ]);

            $hours = number_format($row['hours'], 2, '.', '');
            $rateCents = 16500;
            $subtotal = (int) round($row['hours'] * $rateCents);

            $ro->lines()->create([
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $workGroup->id,
                'type' => RepairOrderLineType::Labor,
                'description' => 'Replace front brake pads and rotors',
                'quantity' => $hours,
                'unit_price_cents' => $rateCents,
                'labor_entered_hours' => $hours,
                'labor_billed_hours' => $hours,
                'labor_rate_cents' => $rateCents,
                'subtotal_cents' => $subtotal,
                'tax_cents' => 0,
                'total_cents' => $subtotal,
                'matrix_applied' => false,
                'has_core' => false,
                'save_old_part' => false,
                'is_overridden' => false,
                'is_private' => false,
            ]);
        }

        if (Customer::query()->where('last_name', 'Recall Current 4WD')->doesntExist()) {
            $customer = Customer::query()->create([
                'first_name' => 'Recall',
                'last_name' => 'Current 4WD',
                'phone' => '555-2099',
            ]);
            $vehicle = Vehicle::query()->create([
                'customer_id' => $customer->id,
                'year' => 2017,
                'make' => 'Toyota',
                'model' => 'Tacoma',
                'engine' => '3.5L',
                'engine_display' => '3.5L',
                'displacement_liters' => 3.5,
                'drivetrain' => '4wd',
                'drive' => '4WD/4-Wheel Drive/4x4',
            ]);
            RepairOrder::query()->create([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'status' => RepairOrderStatus::Estimate,
                'concern_summary' => 'Front brake noise',
            ]);
        }
    }
}
