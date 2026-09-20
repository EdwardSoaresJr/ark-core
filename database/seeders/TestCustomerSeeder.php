<?php

namespace Database\Seeders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Database\Seeders\Concerns\AssignsRepairOrderShopNumber;
use Illuminate\Database\Seeder;

class TestCustomerSeeder extends Seeder
{
    use AssignsRepairOrderShopNumber;

    public function run(): void
    {
        $records = [
            [
                'customer' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Sanchez',
                    'phone' => '7195550112',
                    'email' => 'maria.sanchez@example.test',
                    'address_line_1' => '2145 Academy Blvd',
                    'city' => 'Demo City',
                    'state' => 'CO',
                    'postal_code' => '80909',
                    'customer_type' => 'Retail',
                ],
                'vehicle' => [
                    'vin' => '5FNRL6H71KB030303',
                    'plate' => 'DIA303',
                    'plate_state' => 'CO',
                    'year' => 2016,
                    'make' => 'Honda',
                    'model' => 'Odyssey',
                    'trim' => 'EX-L',
                    'engine' => '3.5L V6',
                    'transmission' => 'Automatic',
                    'drive' => 'FWD',
                ],
                'concern_summary' => 'Check engine light and rough idle',
            ],
            [
                'customer' => [
                    'first_name' => 'James',
                    'last_name' => 'Walker',
                    'phone' => '7195550110',
                    'email' => 'james.walker@example.test',
                    'address_line_1' => '880 Powers Blvd',
                    'address_line_2' => 'Suite 12',
                    'city' => 'Demo City',
                    'state' => 'CO',
                    'postal_code' => '80915',
                    'customer_type' => 'Warranty',
                    'referral_source' => 'repairpal',
                ],
                'vehicle' => [
                    'vin' => '1FTEW1EG6EFD10101',
                    'plate' => 'DRV101',
                    'plate_state' => 'CO',
                    'year' => 2014,
                    'make' => 'Ford',
                    'model' => 'F-150',
                    'trim' => 'XLT',
                    'engine' => '3.5L EcoBoost',
                    'transmission' => 'Automatic',
                    'drive' => '4WD/4-Wheel Drive/4x4',
                ],
                'concern_summary' => 'Vibration under acceleration',
            ],
            [
                'customer' => [
                    'first_name' => 'Linda',
                    'last_name' => 'Patel',
                    'phone' => '7195550111',
                    'email' => 'linda.patel@example.test',
                    'address_line_1' => '1520 Austin Bluffs Pkwy',
                    'city' => 'Demo City',
                    'state' => 'CO',
                    'postal_code' => '80918',
                    'customer_type' => 'Retail',
                ],
                'vehicle' => [
                    'vin' => '2T3RFREV6KW020202',
                    'plate' => 'MNT202',
                    'plate_state' => 'CO',
                    'year' => 2019,
                    'make' => 'Toyota',
                    'model' => 'RAV4',
                    'trim' => 'XLE',
                    'engine' => '2.5L',
                    'transmission' => 'Automatic',
                    'drive' => 'AWD',
                ],
                'concern_summary' => 'Maintenance inspection and oil service',
            ],
            [
                'customer' => [
                    'first_name' => 'Robert',
                    'last_name' => 'Kim',
                    'phone' => '7195550113',
                    'email' => 'robert.kim@example.test',
                    'address_line_1' => '4110 Barnes Rd',
                    'city' => 'Demo City',
                    'state' => 'CO',
                    'postal_code' => '80917',
                    'customer_type' => 'Military',
                ],
                'vehicle' => [
                    'vin' => '1GNSKCE07CR040404',
                    'plate' => 'MLT404',
                    'plate_state' => 'CO',
                    'year' => 2012,
                    'make' => 'Chevrolet',
                    'model' => 'Tahoe',
                    'trim' => 'LT',
                    'engine' => '5.3L V8',
                    'transmission' => 'Automatic',
                    'drive' => '4WD/4-Wheel Drive/4x4',
                ],
                'concern_summary' => 'Brake noise and coolant smell',
            ],
            [
                'customer' => [
                    'first_name' => 'Alex',
                    'last_name' => 'Rivera',
                    'phone' => '7195550199',
                    'email' => 'alex.rivera@example.test',
                    'address_line_1' => '100 Main Street',
                    'address_line_2' => 'Suite A',
                    'city' => 'Demo City',
                    'state' => 'CO',
                    'postal_code' => '80909',
                    'customer_type' => 'Retail',
                ],
                'vehicle' => [
                    'vin' => '1HGCM82633A004352',
                    'plate' => 'DEMO01',
                    'plate_state' => 'CO',
                    'year' => 2016,
                    'make' => 'RAM',
                    'model' => '2500',
                    'trim' => 'Laramie',
                    'engine' => '6.4L Hemi',
                    'transmission' => 'Automatic',
                    'drive' => '4WD/4-Wheel Drive/4x4',
                ],
                'concern_summary' => 'Coolant smell',
            ],
        ];

        foreach ($records as $record) {
            $customer = Customer::query()
                ->where('email', $record['customer']['email'])
                ->orWhere('phone', $record['customer']['phone'])
                ->first();

            if ($customer) {
                $customer->fill([
                    'phone' => $customer->phone ?: $record['customer']['phone'],
                    'email' => $customer->email ?: $record['customer']['email'],
                    'customer_type' => $customer->customer_type ?: $record['customer']['customer_type'],
                    'address_line_1' => $customer->address_line_1 ?: ($record['customer']['address_line_1'] ?? null),
                    'address_line_2' => $customer->address_line_2 ?: ($record['customer']['address_line_2'] ?? null),
                    'city' => $customer->city ?: ($record['customer']['city'] ?? null),
                    'state' => $customer->state ?: ($record['customer']['state'] ?? null),
                    'postal_code' => $customer->postal_code ?: ($record['customer']['postal_code'] ?? null),
                ])->save();
            } else {
                $customer = Customer::create($record['customer']);
            }

            $vehicle = Vehicle::query()
                ->where('vin', $record['vehicle']['vin'])
                ->first();

            if (! $vehicle) {
                $vehicle = Vehicle::create([
                    ...$record['vehicle'],
                    'customer_id' => $customer->id,
                    'normalized_vin' => strtoupper($record['vehicle']['vin']),
                ]);
            }

            $this->assignRepairOrderShopNumber(RepairOrder::firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'concern_summary' => $record['concern_summary'],
                ],
                ['status' => RepairOrderStatus::Draft],
            ));
        }
    }
}
