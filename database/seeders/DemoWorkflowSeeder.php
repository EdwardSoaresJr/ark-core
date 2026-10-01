<?php

namespace Database\Seeders;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocumentService;
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
use Throwable;

class DemoWorkflowSeeder extends Seeder
{
    use AssignsRepairOrderShopNumber;

    public function __construct(
        private readonly EstimateTotalsCalculator $totals,
        private readonly EstimateDocumentService $documents,
    ) {}

    public function run(): void
    {
        ShopSettings::current()->update([
            'tax_enabled' => true,
            'tax_label' => 'C/S Tax',
            'default_tax_rate' => '8.250',
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'shop_fee_enabled' => true,
            'shop_fee_rate' => '5.000',
            'shop_fee_cap_cents' => 3500,
            'parts_matrix' => ShopSettings::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => ShopSettings::DEFAULT_PARTS_MATRICES,
            'customer_types' => ShopSettings::DEFAULT_CUSTOMER_TYPES,
        ]);

        foreach ($this->customers() as $customerRecord) {
            DB::transaction(function () use ($customerRecord): void {
                $customer = Customer::updateOrCreate(
                    ['email' => $customerRecord['email']],
                    [
                        'first_name' => $customerRecord['first_name'],
                        'last_name' => $customerRecord['last_name'],
                        'phone' => $customerRecord['phone'],
                        'customer_type' => $customerRecord['customer_type'] ?? 'Retail',
                        'address_line_1' => $customerRecord['address_line_1'] ?? null,
                        'address_line_2' => $customerRecord['address_line_2'] ?? null,
                        'city' => $customerRecord['city'] ?? null,
                        'state' => $customerRecord['state'] ?? null,
                        'postal_code' => $customerRecord['postal_code'] ?? null,
                        'notes' => $customerRecord['notes'] ?? null,
                    ],
                );

                foreach ($customerRecord['vehicles'] as $vehicleRecord) {
                    $vehicle = Vehicle::updateOrCreate(
                        ['vin' => $vehicleRecord['vin']],
                        [
                            'customer_id' => $customer->id,
                            'plate' => $vehicleRecord['plate'],
                            'plate_state' => $vehicleRecord['plate_state'] ?? 'CO',
                            'year' => $vehicleRecord['year'],
                            'make' => $vehicleRecord['make'],
                            'model' => $vehicleRecord['model'],
                            'trim' => $vehicleRecord['trim'] ?? null,
                            'engine' => $vehicleRecord['engine'] ?? null,
                            'transmission' => $vehicleRecord['transmission'] ?? null,
                            'drive' => $vehicleRecord['drive'] ?? null,
                            'color' => $vehicleRecord['color'] ?? null,
                            'nickname' => $vehicleRecord['nickname'] ?? null,
                            'public_notes' => $vehicleRecord['public_notes'] ?? null,
                            'private_notes' => $vehicleRecord['private_notes'] ?? null,
                            'normalized_vin' => strtoupper($vehicleRecord['vin']),
                        ],
                    );

                    foreach ($vehicleRecord['repair_orders'] as $repairOrderRecord) {
                        $this->seedRepairOrder($customer, $vehicle, $repairOrderRecord);
                    }
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function seedRepairOrder(Customer $customer, Vehicle $vehicle, array $record): void
    {
        $repairOrder = $this->assignRepairOrderShopNumber(RepairOrder::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'concern_summary' => $record['concern_summary'],
            ],
            [
                'assigned_technician_id' => $this->technicianId($record['assigned_technician_email'] ?? null),
                'status' => $record['status'],
                'payment_status' => $record['payment_status'] ?? RepairOrderPaymentStatus::Unpaid,
                'paid_at' => isset($record['paid_minutes_ago']) ? now()->subMinutes($record['paid_minutes_ago']) : null,
            ],
        ));

        $repairOrder->lines()->delete();
        $repairOrder->concerns()->delete();

        foreach ($record['concerns'] ?? [] as $position => $concernRecord) {
            $lines = $concernRecord['lines'] ?? [];
            unset($concernRecord['lines']);
            $concernRecord['disposition'] = ($concernRecord['disposition'] ?? RepairOrderConcernDisposition::Recommended)->value;

            $concern = RepairOrderConcern::create([
                ...$concernRecord,
                'repair_order_id' => $repairOrder->id,
                'position' => $position + 1,
            ]);

            foreach ($lines as $line) {
                $this->seedLine($repairOrder, $line, $concern->id);
            }
        }

        $this->totals->recalculateRepairOrder($repairOrder);
        $this->seedOperationalArtifacts($repairOrder->refresh(), $record);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function seedLine(RepairOrder $repairOrder, array $line, ?int $concernId = null): void
    {
        $settings = ShopSettings::current();
        $matrix = $line['pricing_matrix_key'] ?? null
            ? $settings->partsMatrixByKey($line['pricing_matrix_key'])
            : null;
        $suggestedPriceCents = isset($line['part_cost_cents'], $line['pricing_matrix_key'])
            ? $this->totals->matrixSuggestedPriceCents($line['part_cost_cents'], $settings, $line['pricing_matrix_key'])
            : null;

        $repairOrder->lines()->create([
            'repair_order_concern_id' => $concernId,
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
            'vendor_name' => $line['vendor_name'] ?? null,
            'part_number' => $line['part_number'] ?? null,
            'procurement_state' => ($line['procurement_state'] ?? PartProcurementState::None)->value,
            'sourcing_notes' => $line['sourcing_notes'] ?? null,
            'subtotal_cents' => $this->totals->lineTotalCents($line['quantity'], $line['unit_price_cents']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function seedOperationalArtifacts(RepairOrder $repairOrder, array $record): void
    {
        $admin = User::query()->where('email', 'admin@ark.test')->first();
        $advisor = User::query()->where('email', 'advisor@ark.test')->first() ?? $admin;

        foreach ($record['communications'] ?? [] as $communication) {
            CommunicationEvent::query()->firstOrCreate(
                [
                    'repair_order_id' => $repairOrder->id,
                    'event_type' => $communication['type']->value,
                    'summary' => $communication['summary'],
                ],
                [
                    'created_by' => $advisor?->id,
                    'channel' => $communication['channel']->value,
                    'direction' => $communication['direction']->value,
                    'occurred_at' => now()->subMinutes($communication['minutes_ago']),
                ],
            );
        }

        foreach ($record['approvals'] ?? [] as $approval) {
            ApprovalEvent::query()->firstOrCreate(
                [
                    'visit_id' => $repairOrder->id,
                    'approval_type' => $approval['type']->value,
                    'approved_by' => $approval['approved_by'],
                    'notes' => $approval['notes'],
                ],
                [
                    'estimate_snapshot_reference' => $approval['snapshot_reference'] ?? 'demo-current-estimate',
                    'approved_amount_cents' => $approval['amount_cents'] ?? $this->totals->totalsFor($repairOrder)->totalCents(),
                    'source' => $approval['source']->value,
                    'approved_at' => now()->subMinutes($approval['minutes_ago']),
                ],
            );
        }

        if (! ($record['estimate_document'] ?? $record['status'] !== RepairOrderStatus::Draft)) {
            return;
        }

        $document = $this->documents->createOrRefresh($repairOrder, $admin);

        try {
            $this->documents->generatePdf($document);
        } catch (Throwable) {
            // Demo seed data should keep the immutable snapshot even if local PDF tooling is unavailable.
        }
    }

    private function technicianId(?string $email): ?int
    {
        if ($email === null) {
            return null;
        }

        return User::query()->where('email', $email)->value('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(): array
    {
        return [
            [
                'first_name' => 'Maria',
                'last_name' => 'Sanchez',
                'phone' => '7195550112',
                'email' => 'maria.sanchez@example.test',
                'address_line_1' => '2145 Academy Blvd',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80909',
                'notes' => 'Prefers text updates before authorization calls.',
                'customer_type' => 'Retail',
                'vehicles' => [
                    [
                        'vin' => '5FNRL6H71KB030303',
                        'plate' => 'DIA303',
                        'year' => 2016,
                        'make' => 'Honda',
                        'model' => 'Odyssey',
                        'trim' => 'EX-L',
                        'engine' => '3.5L V6',
                        'transmission' => 'Automatic',
                        'drive' => 'FWD',
                        'color' => 'Silver',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Check engine light and rough idle',
                                'status' => RepairOrderStatus::WaitingApproval,
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::EstimateSent,
                                        'channel' => OperationalCommunicationChannel::Sms,
                                        'direction' => OperationalCommunicationDirection::Outbound,
                                        'summary' => 'Estimate link sent by SMS for misfire repair and deferred battery recommendation.',
                                        'minutes_ago' => 96,
                                    ],
                                    [
                                        'type' => OperationalCommunicationType::EstimateViewed,
                                        'channel' => OperationalCommunicationChannel::Sms,
                                        'direction' => OperationalCommunicationDirection::Inbound,
                                        'summary' => 'Customer viewed the estimate and asked for an authorization call after school pickup.',
                                        'minutes_ago' => 42,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Cylinder misfire',
                                        'recommendation_intent' => 'immediate_attention',
                                        'notes' => 'Customer approved diagnostic time by phone.',
                                        'customer_states' => 'Van shakes at stop lights and check engine light flashes during school pickup route.',
                                        'verified_findings' => 'Cylinder 3 misfire confirmed under load. Coil output weak compared to adjacent cylinders.',
                                        'dtcs_summary' => 'P0303 current, P0300 pending',
                                        'recommendation' => 'Replace cylinder 3 ignition coil and spark plug set, then retest under load.',
                                        'disposition' => RepairOrderConcernDisposition::Approved,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Misfire diagnostic and road test', 'quantity' => '1.00', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Ignition coil', 'quantity' => '1.00', 'unit_price_cents' => 9800, 'part_cost_cents' => 5300, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::None],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Spark plug set', 'quantity' => '1.00', 'unit_price_cents' => 7200, 'part_cost_cents' => 4100, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'oem-parts', 'procurement_state' => PartProcurementState::None],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace coil and spark plugs', 'quantity' => '1.30', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Battery test',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Slow crank noted during diagnostic pull-in.',
                                        'customer_states' => 'No starting complaint, but customer mentioned it cranks longer on cold mornings.',
                                        'verified_findings' => 'Battery tests marginal at 412 CCA against 550 CCA rating.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Advise battery replacement before winter if symptoms continue.',
                                        'disposition' => RepairOrderConcernDisposition::Deferred,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Battery replacement deferred for now.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Check engine light',
                                        'recommendation_intent' => 'diagnostic',
                                        'notes' => 'Pending full powertrain scan after misfire repair.',
                                        'customer_states' => 'Light stays on after short trips around school route.',
                                        'verified_findings' => 'Stored misfire history present. Additional monitors incomplete.',
                                        'dtcs_summary' => 'P0303 history, P0300 pending',
                                        'recommendation' => 'Complete post-repair drive cycle and retest for remaining monitor faults.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Post-repair scan and monitor verification', 'quantity' => '0.50', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Engine air filter',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Filter is due by mileage.',
                                        'customer_states' => 'Customer asked whether airflow could affect idle quality.',
                                        'verified_findings' => 'Air filter heavily restricted with road dust.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace engine air filter during this visit.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Engine air filter', 'quantity' => '1.00', 'unit_price_cents' => 2895, 'part_cost_cents' => 1450, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'filters'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace engine air filter', 'quantity' => '0.20', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Transmission service',
                                        'recommendation_intent' => 'plan_soon',
                                        'notes' => 'Not urgent this visit.',
                                        'customer_states' => 'Customer asked about towing interval service.',
                                        'verified_findings' => 'Fluid color dark but no slipping noted on road test.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Plan transmission fluid service before next towing season.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Transmission fluid service kit', 'quantity' => '1.00', 'unit_price_cents' => 8900, 'part_cost_cents' => 4800, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Transmission fluid exchange', 'quantity' => '1.00', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'first_name' => 'James',
                'last_name' => 'Walker',
                'phone' => '7195550110',
                'email' => 'james.walker@example.test',
                'address_line_1' => '880 Powers Blvd',
                'address_line_2' => 'Suite 12',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80915',
                'notes' => 'Fleet customer. Call before adding same-day work.',
                'customer_type' => 'Warranty',
                'vehicles' => [
                    [
                        'vin' => '1FTEW1EG6EFD10101',
                        'plate' => 'DRV101',
                        'year' => 2014,
                        'make' => 'Ford',
                        'model' => 'F-150',
                        'trim' => 'XLT',
                        'engine' => '3.5L EcoBoost',
                        'transmission' => 'Automatic',
                        'drive' => '4WD/4-Wheel Drive/4x4',
                        'color' => 'White',
                        'nickname' => 'Tow truck',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Vibration under acceleration',
                                'status' => RepairOrderStatus::Estimate,
                                'estimate_document' => true,
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::AdvisorNote,
                                        'channel' => OperationalCommunicationChannel::Internal,
                                        'direction' => OperationalCommunicationDirection::Internal,
                                        'summary' => 'Fleet customer needs estimate finished before dispatch route tomorrow morning.',
                                        'minutes_ago' => 31,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Driveline vibration',
                                        'recommendation_intent' => 'immediate_attention',
                                        'notes' => 'Customer uses truck for towing during the week.',
                                        'customer_states' => 'Vibration starts around 45 mph under throttle and fades while coasting.',
                                        'verified_findings' => 'Road test duplicated vibration. Rear U-joint has visible play and rust dust.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace rear driveshaft U-joint and retest.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Road test and driveline inspection', 'quantity' => '0.80', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Rear driveshaft U-joint', 'quantity' => '1.00', 'unit_price_cents' => 4600, 'part_cost_cents' => 2600, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace rear U-joint', 'quantity' => '1.20', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Tire balance check',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Steering wheel remains steady during concern.',
                                        'customer_states' => 'Customer asked whether tires could be causing the vibration.',
                                        'verified_findings' => 'No abnormal tire wear found during quick inspection.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Balance check not recommended until driveline repair is complete.',
                                        'disposition' => RepairOrderConcernDisposition::Deferred,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Recheck tire balance only if vibration remains after U-joint repair.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Front differential service',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Due by towing mileage interval.',
                                        'customer_states' => 'Fleet dispatch asked whether diff service is current.',
                                        'verified_findings' => 'Diff fluid dark with fine metallic sheen.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Perform front differential fluid service before heavy towing week.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Front differential fluid', 'quantity' => '2.00', 'unit_price_cents' => 1895, 'part_cost_cents' => 980, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Front differential fluid service', 'quantity' => '0.60', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Steering damper wear',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'No safety concern yet.',
                                        'customer_states' => 'Customer reports slight wander on highway after towing.',
                                        'verified_findings' => 'Steering damper shows minor seep. No abnormal free play.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Monitor steering damper at next service interval.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Reinspect steering damper at next oil service.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'concern_summary' => 'Previous oil service and tire rotation',
                                'status' => RepairOrderStatus::Closed,
                                'payment_status' => RepairOrderPaymentStatus::Paid,
                                'paid_minutes_ago' => 4320,
                                'estimate_document' => true,
                                'approvals' => [
                                    [
                                        'type' => ApprovalType::Repair,
                                        'source' => ApprovalSource::InPerson,
                                        'approved_by' => 'James Walker',
                                        'notes' => 'Customer approved maintenance package at counter.',
                                        'minutes_ago' => 4500,
                                    ],
                                ],
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::PickupNotified,
                                        'channel' => OperationalCommunicationChannel::Phone,
                                        'direction' => OperationalCommunicationDirection::Outbound,
                                        'summary' => 'Customer notified oil service was complete and picked up same afternoon.',
                                        'minutes_ago' => 4380,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Maintenance service',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Completed before current vibration visit.',
                                        'customer_states' => 'Routine service before towing trip.',
                                        'verified_findings' => 'Oil service due by mileage. Tires wearing evenly.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Return in 5,000 miles for next service.',
                                        'disposition' => RepairOrderConcernDisposition::Approved,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Synthetic blend oil and filter', 'quantity' => '1.00', 'unit_price_cents' => 5895, 'part_cost_cents' => 3150, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids', 'procurement_state' => PartProcurementState::Installed],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Oil service and tire rotation', 'quantity' => '0.70', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'first_name' => 'Linda',
                'last_name' => 'Patel',
                'phone' => '7195550111',
                'email' => 'linda.patel@example.test',
                'address_line_1' => '1520 Austin Bluffs Pkwy',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80918',
                'notes' => 'Usually waits in lobby if estimate is under two hours.',
                'customer_type' => 'Retail',
                'vehicles' => [
                    [
                        'vin' => '2T3RFREV6KW020202',
                        'plate' => 'MNT202',
                        'year' => 2019,
                        'make' => 'Toyota',
                        'model' => 'RAV4',
                        'trim' => 'XLE',
                        'engine' => '2.5L',
                        'transmission' => 'Automatic',
                        'drive' => 'AWD',
                        'color' => 'Blue',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Maintenance inspection and oil service',
                                'status' => RepairOrderStatus::WaitingApproval,
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::EstimateSent,
                                        'channel' => OperationalCommunicationChannel::Sms,
                                        'direction' => OperationalCommunicationDirection::Outbound,
                                        'summary' => 'Maintenance estimate texted while customer waits in lobby.',
                                        'minutes_ago' => 18,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Scheduled maintenance',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Customer waiting for text approval before lunch.',
                                        'customer_states' => 'Customer requested oil service and trip check before driving to Denver.',
                                        'verified_findings' => 'Oil change due. Tire pressures adjusted. Fluids inspected.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Perform synthetic oil service and maintenance inspection.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Maintenance inspection', 'quantity' => '0.50', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Synthetic oil and filter kit', 'quantity' => '1.00', 'unit_price_cents' => 6895, 'part_cost_cents' => 3750, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Oil and filter service', 'quantity' => '0.40', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Fee, 'description' => 'Oil disposal fee', 'quantity' => '1.00', 'unit_price_cents' => 595],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Cabin air filter',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'Filter is dirty but not urgent.',
                                        'customer_states' => 'Customer reports dusty smell from vents on startup.',
                                        'verified_findings' => 'Cabin filter has leaf debris and dust buildup.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace cabin air filter during this visit.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Cabin air filter', 'quantity' => '1.00', 'unit_price_cents' => 3495, 'part_cost_cents' => 1800, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'filters'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace cabin air filter', 'quantity' => '0.20', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Front brake pads 2mm',
                                        'recommendation_intent' => 'immediate_attention',
                                        'notes' => 'Customer asked for brake check before Denver trip.',
                                        'customer_states' => 'No noise yet, but customer wants brakes checked before mountain drive.',
                                        'verified_findings' => 'Front pads at 2mm. Rotors within service limit.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace front brake pads before long highway trip.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Front brake pad set', 'quantity' => '1.00', 'unit_price_cents' => 9800, 'part_cost_cents' => 6100, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace front brake pads', 'quantity' => '1.10', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Cooling system service',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Coolant interval due by time.',
                                        'customer_states' => 'Customer asked whether coolant should be changed before summer.',
                                        'verified_findings' => 'Coolant test strips show additive depletion.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Perform cooling system flush and refill.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Coolant flush kit', 'quantity' => '1.00', 'unit_price_cents' => 4200, 'part_cost_cents' => 2100, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Cooling system flush', 'quantity' => '1.00', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'vin' => 'JTDKARFU4H3050505',
                        'plate' => 'HYB505',
                        'year' => 2017,
                        'make' => 'Toyota',
                        'model' => 'Prius',
                        'engine' => '1.8L Hybrid',
                        'transmission' => 'Automatic',
                        'drive' => 'FWD',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Hybrid battery fan noise',
                                'status' => RepairOrderStatus::Draft,
                                'concerns' => [
                                    [
                                        'summary' => 'Hybrid battery fan noise',
                                        'recommendation_intent' => 'diagnostic',
                                        'notes' => 'Customer dropped off before first inspection.',
                                        'customer_states' => 'Whining noise from rear seat area after extended highway driving.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Hybrid cooling fan diagnostic', 'quantity' => '1.00', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => '12V auxiliary battery',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Aux battery age unknown.',
                                        'customer_states' => 'Customer mentioned slow crank after car sat for a week.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => '12V battery test', 'quantity' => '0.30', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Cabin filter overdue',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'Likely overdue by mileage.',
                                        'customer_states' => 'Musty smell from vents on humid mornings.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Inspect cabin filter during intake.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Kim',
                'phone' => '7195550113',
                'email' => 'robert.kim@example.test',
                'address_line_1' => '4110 Barnes Rd',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80917',
                'notes' => 'Approves safety work quickly; prefers detailed photos later.',
                'customer_type' => 'Military',
                'vehicles' => [
                    [
                        'vin' => '1GNSKCE07CR040404',
                        'plate' => 'MLT404',
                        'year' => 2012,
                        'make' => 'Chevrolet',
                        'model' => 'Tahoe',
                        'trim' => 'LT',
                        'engine' => '5.3L V8',
                        'transmission' => 'Automatic',
                        'drive' => '4WD/4-Wheel Drive/4x4',
                        'color' => 'Black',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Brake noise and coolant smell',
                                'status' => RepairOrderStatus::ReadyPickup,
                                'payment_status' => RepairOrderPaymentStatus::Unpaid,
                                'assigned_technician_email' => 'tech@ark.test',
                                'estimate_document' => true,
                                'approvals' => [
                                    [
                                        'type' => ApprovalType::Partial,
                                        'source' => ApprovalSource::Phone,
                                        'approved_by' => 'Robert Kim',
                                        'notes' => 'Approved front brake repair. Deferred coolant hose until next visit.',
                                        'minutes_ago' => 290,
                                    ],
                                ],
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::ApprovalFollowUp,
                                        'channel' => OperationalCommunicationChannel::Phone,
                                        'direction' => OperationalCommunicationDirection::Outbound,
                                        'summary' => 'Brake work approved by phone; coolant repair deferred.',
                                        'minutes_ago' => 290,
                                    ],
                                    [
                                        'type' => OperationalCommunicationType::PickupNotified,
                                        'channel' => OperationalCommunicationChannel::Sms,
                                        'direction' => OperationalCommunicationDirection::Outbound,
                                        'summary' => 'Pickup notification sent. Balance due before key release.',
                                        'minutes_ago' => 34,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Front brake noise',
                                        'recommendation_intent' => 'immediate_attention',
                                        'notes' => 'Customer heard grinding after mountain drive.',
                                        'customer_states' => 'Grinding noise from front wheels during low speed stops.',
                                        'verified_findings' => 'Front pads at 2mm. Rotors heavily grooved and below refinish margin.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace front pads and rotors, then perform brake burnish road test.',
                                        'disposition' => RepairOrderConcernDisposition::Approved,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Front brake pad set', 'quantity' => '1.00', 'unit_price_cents' => 11200, 'part_cost_cents' => 7200, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::Installed],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Front brake rotors', 'quantity' => '2.00', 'unit_price_cents' => 8900, 'part_cost_cents' => 5400, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::Installed],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace front pads and rotors', 'quantity' => '1.60', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Fee, 'description' => 'Brake cleaner and hardware supplies', 'quantity' => '1.00', 'unit_price_cents' => 1895],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Coolant seep',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'No active overheating concern.',
                                        'customer_states' => 'Sweet smell after parking in garage overnight.',
                                        'verified_findings' => 'Upper radiator hose seep at clamp. Coolant level slightly low.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace upper radiator hose and pressure test cooling system.',
                                        'disposition' => RepairOrderConcernDisposition::Deferred,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Upper radiator hose', 'quantity' => '1.00', 'unit_price_cents' => 5400, 'part_cost_cents' => 2850, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace hose and pressure test', 'quantity' => '1.10', 'unit_price_cents' => 16500],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Coolant top-off', 'quantity' => '1.00', 'unit_price_cents' => 2200, 'part_cost_cents' => 1100, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids'],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Rear brake measurement',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'Rear brakes still serviceable.',
                                        'customer_states' => 'Customer asked whether rear brakes need attention soon.',
                                        'verified_findings' => 'Rear pads at 5mm with even wear.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Monitor rear brakes at next tire rotation.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Rear brakes acceptable for now.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Valve cover seep',
                                        'recommendation_intent' => 'plan_soon',
                                        'notes' => 'Minor seep only.',
                                        'customer_states' => 'Customer noticed faint oil smell after long idle.',
                                        'verified_findings' => 'Valve cover gasket seep at rear corner. No active drip.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Plan valve cover gasket service on next visit if seep worsens.',
                                        'disposition' => RepairOrderConcernDisposition::Recommended,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Valve cover gasket set', 'quantity' => '1.00', 'unit_price_cents' => 7600, 'part_cost_cents' => 4200, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace valve cover gaskets', 'quantity' => '2.80', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'first_name' => 'Alex',
                'last_name' => 'Rivera',
                'phone' => '7195550199',
                'email' => 'alex.rivera@example.test',
                'address_line_1' => '100 Main Street',
                'address_line_2' => 'D',
                'city' => 'Demo City',
                'state' => 'CO',
                'postal_code' => '80909',
                'notes' => 'Owner demo record.',
                'customer_type' => 'Retail',
                'vehicles' => [
                    [
                        'vin' => '1HGCM82633A004352',
                        'plate' => 'DEMO01',
                        'year' => 2016,
                        'make' => 'RAM',
                        'model' => '2500',
                        'trim' => 'Laramie',
                        'engine' => '6.4L Hemi',
                        'transmission' => 'Automatic',
                        'drive' => '4WD/4-Wheel Drive/4x4',
                        'color' => 'White',
                        'repair_orders' => [
                            [
                                'concern_summary' => 'Coolant smell',
                                'status' => RepairOrderStatus::Draft,
                                'concerns' => [
                                    [
                                        'summary' => 'Overheating',
                                        'recommendation_intent' => 'diagnostic',
                                        'notes' => 'Truck was dropped off before opening.',
                                        'customer_states' => 'Customer smells coolant near grille after shutdown. No temperature warning seen.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Diagnostics and testing', 'quantity' => '1.00', 'unit_price_cents' => 15000],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Cooling system inspection',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Follow-up after diagnostic time.',
                                        'customer_states' => 'Customer wants full cooling system evaluation.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Cooling system pressure test', 'quantity' => '0.70', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Lower radiator hose seep',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'Possible minor seep noted on quick walkaround.',
                                        'customer_states' => 'Customer noticed dried coolant residue on lower hose.',
                                        'verified_findings' => null,
                                        'dtcs_summary' => null,
                                        'recommendation' => null,
                                        'disposition' => RepairOrderConcernDisposition::Draft,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Confirm hose seep during inspection.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'concern_summary' => 'Approved water pump replacement',
                                'status' => RepairOrderStatus::WaitingParts,
                                'assigned_technician_email' => 'tech@ark.test',
                                'estimate_document' => true,
                                'approvals' => [
                                    [
                                        'type' => ApprovalType::Repair,
                                        'source' => ApprovalSource::Sms,
                                        'approved_by' => 'Alex Rivera',
                                        'notes' => 'Customer approved water pump, thermostat, coolant, and labor by text.',
                                        'minutes_ago' => 165,
                                    ],
                                ],
                                'communications' => [
                                    [
                                        'type' => OperationalCommunicationType::CustomerReply,
                                        'channel' => OperationalCommunicationChannel::Sms,
                                        'direction' => OperationalCommunicationDirection::Inbound,
                                        'summary' => 'Customer replied APPROVED for water pump repair.',
                                        'minutes_ago' => 165,
                                    ],
                                    [
                                        'type' => OperationalCommunicationType::AdvisorNote,
                                        'channel' => OperationalCommunicationChannel::Internal,
                                        'direction' => OperationalCommunicationDirection::Internal,
                                        'summary' => 'Thermostat is backordered; water pump and coolant are staged.',
                                        'minutes_ago' => 24,
                                    ],
                                ],
                                'concerns' => [
                                    [
                                        'summary' => 'Water pump leak',
                                        'recommendation_intent' => 'immediate_attention',
                                        'notes' => 'Parts are staged on cart.',
                                        'customer_states' => 'Coolant smell worsened after towing.',
                                        'verified_findings' => 'Pressure test shows leak from water pump weep hole.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace water pump, thermostat, and coolant.',
                                        'disposition' => RepairOrderConcernDisposition::Approved,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Water pump assembly', 'quantity' => '1.00', 'unit_price_cents' => 18900, 'part_cost_cents' => 12200, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::Received, 'sourcing_notes' => 'Pump received and staged on cart.'],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Thermostat and gasket', 'quantity' => '1.00', 'unit_price_cents' => 6400, 'part_cost_cents' => 3600, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::Backordered, 'sourcing_notes' => 'Vendor ETA tomorrow morning; call by 10 AM if not confirmed.'],
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Coolant', 'quantity' => '2.00', 'unit_price_cents' => 2495, 'part_cost_cents' => 1200, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'fluids', 'procurement_state' => PartProcurementState::Received],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace water pump and bleed cooling system', 'quantity' => '3.40', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Radiator cap weak',
                                        'recommendation_intent' => 'maintenance',
                                        'notes' => 'Cap failed pressure hold during test.',
                                        'customer_states' => 'Customer asked whether cap should be replaced with pump.',
                                        'verified_findings' => 'Radiator cap releases below spec pressure.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Replace radiator cap with cooling repair.',
                                        'disposition' => RepairOrderConcernDisposition::Approved,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Radiator cap', 'quantity' => '1.00', 'unit_price_cents' => 1895, 'part_cost_cents' => 950, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts', 'procurement_state' => PartProcurementState::Received],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Serpentine belt glaze',
                                        'recommendation_intent' => 'information_only',
                                        'notes' => 'Belt still within service limit.',
                                        'customer_states' => 'Customer asked about squeal after cold start.',
                                        'verified_findings' => 'Belt shows glazing but no cracking.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Monitor belt noise after cooling repair.',
                                        'disposition' => RepairOrderConcernDisposition::Deferred,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Note, 'description' => 'Recheck belt noise after repair completion.', 'quantity' => '1.00', 'unit_price_cents' => 0],
                                        ],
                                    ],
                                    [
                                        'summary' => 'Coolant reservoir stain',
                                        'recommendation_intent' => 'plan_soon',
                                        'notes' => 'Cosmetic seep around cap area.',
                                        'customer_states' => 'Customer noticed dried coolant on reservoir neck.',
                                        'verified_findings' => 'Reservoir neck has stain but no active leak path found.',
                                        'dtcs_summary' => null,
                                        'recommendation' => 'Plan reservoir replacement if seep returns after repair.',
                                        'disposition' => RepairOrderConcernDisposition::Deferred,
                                        'lines' => [
                                            ['type' => RepairOrderLineType::Part, 'description' => 'Coolant reservoir', 'quantity' => '1.00', 'unit_price_cents' => 5400, 'part_cost_cents' => 2900, 'pricing_mode' => 'matrix', 'pricing_matrix_key' => 'aft-parts'],
                                            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace coolant reservoir', 'quantity' => '0.50', 'unit_price_cents' => 16500],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
