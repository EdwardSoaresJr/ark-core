<?php

namespace Database\Seeders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Database\Seeders\Concerns\AssignsRepairOrderShopNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkOperationalDemoSeeder extends Seeder
{
    use AssignsRepairOrderShopNumber;

    private const CUSTOMER_TARGET = 500;

    private const REPAIR_ORDER_TARGET = 800;

    /**
     * Handcrafted demo data contributes 7 open repair orders; keep bulk open volume small.
     */
    private const BULK_OPEN_REPAIR_ORDER_TARGET = 13;

    public function __construct(private readonly EstimateTotalsCalculator $totals) {}

    public function run(): void
    {
        $existingDemoCustomers = Customer::query()
            ->whereNotLike('email', 'bulk-%@example.test')
            ->count();
        $existingDemoRepairOrders = RepairOrder::query()
            ->whereDoesntHave('customer', fn ($customers) => $customers->whereLike('email', 'bulk-%@example.test'))
            ->count();

        $bulkCustomerTarget = max(0, self::CUSTOMER_TARGET - $existingDemoCustomers);
        $bulkRepairOrderTarget = max(0, self::REPAIR_ORDER_TARGET - $existingDemoRepairOrders);

        $technicianId = User::query()->where('email', 'tech@ark.test')->value('id');

        foreach (range(1, $bulkCustomerTarget) as $customerIndex) {
            DB::transaction(function () use ($customerIndex, $bulkRepairOrderTarget, $bulkCustomerTarget, $technicianId): void {
                $customer = Customer::query()->updateOrCreate(
                    ['email' => $this->email($customerIndex)],
                    [
                        'first_name' => $this->firstName($customerIndex),
                        'last_name' => $this->lastName($customerIndex),
                        'phone' => $this->phone($customerIndex),
                        'customer_type' => $this->customerType($customerIndex),
                        'address_line_1' => $this->addressLine1($customerIndex),
                        'address_line_2' => $customerIndex % 7 === 0 ? 'Unit '.($customerIndex % 20 + 1) : null,
                        'city' => $this->addressCity($customerIndex),
                        'state' => 'CO',
                        'postal_code' => $this->postalCode($customerIndex),
                        'notes' => $customerIndex % 9 === 0 ? 'Bulk demo customer with multi-visit history.' : null,
                    ],
                );

                $vehicle = Vehicle::query()->updateOrCreate(
                    ['vin' => $this->vin($customerIndex, 1)],
                    [
                        'customer_id' => $customer->id,
                        'plate' => $this->plate($customerIndex, 1),
                        'plate_state' => 'CO',
                        'year' => $this->vehicleYear($customerIndex),
                        'make' => $this->vehicleMake($customerIndex),
                        'model' => $this->vehicleModel($customerIndex),
                        'trim' => $this->vehicleTrim($customerIndex),
                        'engine' => $this->engine($customerIndex),
                        'transmission' => $customerIndex % 11 === 0 ? 'Manual' : 'Automatic',
                        'drive' => $customerIndex % 4 === 0 ? 'AWD' : ($customerIndex % 5 === 0 ? '4WD' : 'FWD'),
                        'color' => $this->color($customerIndex),
                        'normalized_vin' => $this->vin($customerIndex, 1),
                    ],
                );

                $ordersForCustomer = $this->ordersForCustomer($customerIndex, $bulkRepairOrderTarget, $bulkCustomerTarget);

                foreach (range(1, $ordersForCustomer) as $orderIndex) {
                    $this->seedRepairOrder($customer, $vehicle, $customerIndex, $orderIndex, $technicianId);
                }
            });
        }
    }

    private function seedRepairOrder(Customer $customer, Vehicle $vehicle, int $customerIndex, int $orderIndex, ?int $technicianId): void
    {
        $sequence = (($customerIndex - 1) * 2) + $orderIndex;
        $status = $this->status($sequence);
        $paid = $status === RepairOrderStatus::Closed || ($status === RepairOrderStatus::ReadyPickup && $sequence % 3 === 0);
        $summary = $this->concernSummary($sequence);

        $repairOrder = $this->assignRepairOrderShopNumber(RepairOrder::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'concern_summary' => $summary,
            ],
            [
                'assigned_technician_id' => in_array($status, [RepairOrderStatus::ReadyForWork, RepairOrderStatus::InProgress, RepairOrderStatus::WaitingParts, RepairOrderStatus::ReadyPickup], true) ? $technicianId : null,
                'status' => $status,
                'payment_status' => $paid ? RepairOrderPaymentStatus::Paid : RepairOrderPaymentStatus::Unpaid,
                'paid_at' => $paid ? now()->subDays(($sequence % 21) + 1) : null,
                'created_at' => now()->subDays(($sequence % 75) + 1),
                'updated_at' => now()->subMinutes(($sequence % 600) + 15),
            ],
        ));

        $repairOrder->lines()->delete();
        $repairOrder->concerns()->delete();

        $concern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => $summary,
            'recommendation_intent' => $sequence % 7 === 0 ? 'immediate_attention' : 'maintenance',
            'notes' => 'Bulk demo RO seeded for queue, search, estimate, and lifecycle volume.',
            'customer_states' => $this->customerStates($sequence),
            'verified_findings' => $status === RepairOrderStatus::Draft ? null : $this->verifiedFindings($sequence),
            'dtcs_summary' => $sequence % 8 === 0 ? 'P0'.str_pad((string) (($sequence % 900) + 100), 3, '0', STR_PAD_LEFT) : null,
            'recommendation' => $status === RepairOrderStatus::Draft ? null : $this->recommendation($sequence),
            'disposition' => $this->disposition($status)->value,
            'position' => 1,
        ]);

        foreach ($this->lines($sequence, $status) as $line) {
            $this->seedLine($repairOrder, $concern, $line);
        }

        $this->totals->recalculateRepairOrder($repairOrder);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(int $sequence, RepairOrderStatus $status): array
    {
        if ($status === RepairOrderStatus::Draft) {
            return [
                ['type' => RepairOrderLineType::Labor, 'description' => 'Initial diagnostic inspection', 'quantity' => '0.70', 'unit_price_cents' => 16500],
            ];
        }

        $partState = match ($status) {
            RepairOrderStatus::WaitingParts => $sequence % 2 === 0 ? PartProcurementState::Backordered : PartProcurementState::Ordered,
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Closed => PartProcurementState::Installed,
            RepairOrderStatus::InProgress => $sequence % 3 === 0 ? PartProcurementState::Received : PartProcurementState::Installed,
            default => PartProcurementState::None,
        };

        $partCost = 2800 + (($sequence % 19) * 325);
        $partSell = (int) round($partCost * 1.85);

        return [
            ['type' => RepairOrderLineType::Labor, 'description' => $this->laborDescription($sequence), 'quantity' => $sequence % 4 === 0 ? '1.40' : '0.90', 'unit_price_cents' => 16500],
            ['type' => RepairOrderLineType::Part, 'description' => $this->partDescription($sequence), 'quantity' => '1.00', 'unit_price_cents' => $partSell, 'part_cost_cents' => $partCost, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => $sequence % 5 === 0 ? 'oem-parts' : 'aft-parts', 'procurement_state' => $partState],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function seedLine(RepairOrder $repairOrder, RepairOrderConcern $concern, array $line): void
    {
        $settings = ShopSettings::current();
        $matrix = $line['pricing_matrix_key'] ?? null
            ? $settings->partsMatrixByKey($line['pricing_matrix_key'])
            : null;
        $suggestedPriceCents = isset($line['part_cost_cents'], $line['pricing_matrix_key'])
            ? $this->totals->matrixSuggestedPriceCents($line['part_cost_cents'], $settings, $line['pricing_matrix_key'])
            : null;

        $repairOrder->lines()->create([
            'repair_order_concern_id' => $concern->id,
            'type' => $line['type']->value,
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price_cents' => $line['unit_price_cents'],
            'part_cost_cents' => $line['part_cost_cents'] ?? null,
            'matrix_suggested_price_cents' => $suggestedPriceCents,
            'pricing_mode' => $line['pricing_mode'] ?? null,
            'pricing_matrix_key' => $line['pricing_matrix_key'] ?? null,
            'pricing_matrix_name' => $matrix['name'] ?? null,
            'matrix_applied' => $suggestedPriceCents !== null && $suggestedPriceCents === $line['unit_price_cents'],
            'vendor_name' => $line['type'] === RepairOrderLineType::Part ? $this->vendor((int) $repairOrder->id) : null,
            'part_number' => $line['type'] === RepairOrderLineType::Part ? 'BULK-'.str_pad((string) $repairOrder->id, 5, '0', STR_PAD_LEFT) : null,
            'procurement_state' => ($line['procurement_state'] ?? PartProcurementState::None)->value,
            'sourcing_notes' => ($line['procurement_state'] ?? null) === PartProcurementState::Backordered ? 'Bulk demo backorder: call vendor for ETA before noon.' : null,
            'subtotal_cents' => $this->totals->lineTotalCents($line['quantity'], $line['unit_price_cents']),
        ]);
    }

    private function ordersForCustomer(int $customerIndex, int $repairOrderTarget, int $customerTarget): int
    {
        $base = intdiv($repairOrderTarget, max(1, $customerTarget));
        $remainder = $repairOrderTarget % max(1, $customerTarget);

        return $base + ($customerIndex <= $remainder ? 1 : 0);
    }

    private function status(int $sequence): RepairOrderStatus
    {
        if ($sequence > self::BULK_OPEN_REPAIR_ORDER_TARGET) {
            return RepairOrderStatus::Closed;
        }

        return match ($sequence) {
            1, 2 => RepairOrderStatus::Draft,
            3, 4 => RepairOrderStatus::Estimate,
            5, 6 => RepairOrderStatus::WaitingApproval,
            7 => RepairOrderStatus::Approved,
            8, 9 => RepairOrderStatus::WaitingParts,
            10 => RepairOrderStatus::ReadyForWork,
            11, 12 => RepairOrderStatus::InProgress,
            13 => RepairOrderStatus::ReadyPickup,
        };
    }

    private function disposition(RepairOrderStatus $status): RepairOrderConcernDisposition
    {
        return match ($status) {
            RepairOrderStatus::Draft,
            RepairOrderStatus::Estimate,
            RepairOrderStatus::WaitingApproval => RepairOrderConcernDisposition::Recommended,
            default => RepairOrderConcernDisposition::Approved,
        };
    }

    private function email(int $index): string
    {
        return 'bulk-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'@example.test';
    }

    private function firstName(int $index): string
    {
        $names = ['Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Avery', 'Quinn', 'Jamie', 'Cameron'];

        return $names[$index % count($names)];
    }

    private function lastName(int $index): string
    {
        $names = ['Miller', 'Garcia', 'Johnson', 'Brown', 'Davis', 'Wilson', 'Martinez', 'Anderson', 'Thomas', 'Moore'];

        return $names[$index % count($names)].' '.$index;
    }

    private function phone(int $index): string
    {
        return '719555'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT);
    }

    private function customerType(int $index): string
    {
        return match ($index % 12) {
            0 => 'Military',
            1, 2 => 'Warranty',
            default => 'Retail',
        };
    }

    private function addressLine1(int $index): string
    {
        $streets = ['Main Street', 'Academy Blvd', 'Powers Blvd', 'Barnes Rd', 'Austin Bluffs Pkwy', 'Nevada Ave'];

        return (100 + ($index % 850)).' '.$streets[$index % count($streets)];
    }

    private function addressCity(int $index): string
    {
        return match ($index % 5) {
            1 => 'Fountain',
            2 => 'Monument',
            3 => 'Pueblo',
            default => 'Demo City',
        };
    }

    private function postalCode(int $index): string
    {
        return match ($index % 5) {
            1 => '80817',
            2 => '80132',
            3 => '81003',
            default => '809'.str_pad((string) (10 + ($index % 19)), 2, '0', STR_PAD_LEFT),
        };
    }

    private function vin(int $customerIndex, int $vehicleIndex): string
    {
        return 'ARK'.str_pad((string) $customerIndex, 10, '0', STR_PAD_LEFT).str_pad((string) $vehicleIndex, 4, '0', STR_PAD_LEFT);
    }

    private function plate(int $customerIndex, int $vehicleIndex): string
    {
        return 'B'.str_pad((string) $customerIndex, 5, '0', STR_PAD_LEFT).$vehicleIndex;
    }

    private function vehicleYear(int $index): int
    {
        return 2010 + ($index % 15);
    }

    private function vehicleMake(int $index): string
    {
        $makes = ['Toyota', 'Honda', 'Ford', 'Chevrolet', 'Subaru', 'Nissan', 'Jeep', 'Hyundai'];

        return $makes[$index % count($makes)];
    }

    private function vehicleModel(int $index): string
    {
        $models = ['Camry', 'Accord', 'F-150', 'Tahoe', 'Outback', 'Altima', 'Wrangler', 'Santa Fe'];

        return $models[$index % count($models)];
    }

    private function vehicleTrim(int $index): string
    {
        return $index % 3 === 0 ? 'Limited' : ($index % 3 === 1 ? 'EX' : 'Base');
    }

    private function engine(int $index): string
    {
        return match ($index % 5) {
            0 => '2.0L I4',
            1 => '2.5L I4',
            2 => '3.5L V6',
            3 => '5.3L V8',
            default => '1.8L Hybrid',
        };
    }

    private function color(int $index): string
    {
        $colors = ['White', 'Black', 'Silver', 'Blue', 'Red', 'Gray'];

        return $colors[$index % count($colors)];
    }

    private function concernSummary(int $sequence): string
    {
        $summaries = ['Brake noise', 'Check engine light', 'Oil service', 'No start', 'Coolant leak', 'Suspension clunk', 'A/C not cold', 'Transmission service'];

        return $summaries[$sequence % count($summaries)].' bulk visit '.$sequence;
    }

    private function customerStates(int $sequence): string
    {
        return 'Customer reports '.$this->concernSummary($sequence).' during normal driving.';
    }

    private function verifiedFindings(int $sequence): string
    {
        return 'Technician inspection confirmed the concern and documented recommended next steps.';
    }

    private function recommendation(int $sequence): string
    {
        return 'Perform '.$this->laborDescription($sequence).' and replace '.$this->partDescription($sequence).'.';
    }

    private function laborDescription(int $sequence): string
    {
        $descriptions = ['Brake inspection and repair', 'Diagnostic test and verification', 'Maintenance service labor', 'Starting system diagnostic', 'Cooling system pressure test', 'Suspension inspection', 'A/C performance diagnostic', 'Transmission service labor'];

        return $descriptions[$sequence % count($descriptions)];
    }

    private function partDescription(int $sequence): string
    {
        $descriptions = ['Brake hardware kit', 'Sensor assembly', 'Oil and filter kit', 'Starter relay', 'Coolant hose', 'Sway bar link', 'A/C service valve', 'Transmission filter kit'];

        return $descriptions[$sequence % count($descriptions)];
    }

    private function vendor(int $sequence): string
    {
        $vendors = ['Local Parts Counter', 'Denver Warehouse', 'OEM Dealer', 'Aftermarket Hub'];

        return $vendors[$sequence % count($vendors)];
    }
}
