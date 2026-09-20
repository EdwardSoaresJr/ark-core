<?php

namespace App\Ark\Operations\Audit;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OperationalAuditSeeder
{
    public const MARKER = '[ARK_OPERATIONAL_AUDIT]';

    public function __construct(
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly GenerateInvoiceSnapshotAction $generateInvoice,
        private readonly RecordLedgerEntryAction $recordLedger,
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return Collection<int, OperationalAuditScenario>
     */
    public function seed(bool $financialOnly = false): Collection
    {
        $this->prepareShopSettings();
        $actor = User::query()->orderBy('id')->first();

        $scenarios = collect([
            $this->seedHappyPath($actor),
            $this->seedPartialPayment($actor),
            $this->seedMultiplePayments($actor),
            $this->seedOverpaymentStoreCredit($actor),
            $this->seedNoApprovedWork($actor),
            $this->seedInvoiceNotYetAllowed($actor),
            $this->seedDepositApplied($actor),
            $this->seedReadyPickupUnpaidAging($actor),
            $this->seedClosedPaid($actor),
        ]);

        if (! $financialOnly) {
            $scenarios->push($this->seedMultiVehicleRepeatCustomer($actor));
        }

        return $scenarios;
    }

    public function reset(): void
    {
        $customerIds = Customer::query()
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->pluck('id');

        if ($customerIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($customerIds): void {
            RepairOrder::query()->whereIn('customer_id', $customerIds)->delete();
            Vehicle::query()->whereIn('customer_id', $customerIds)->delete();
            Customer::query()->whereIn('id', $customerIds)->delete();
        });
    }

    private function prepareShopSettings(): void
    {
        ShopSettings::current()->update([
            'tax_enabled' => false,
            'shop_fee_enabled' => false,
        ]);
    }

    private function seedHappyPath(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '01',
            firstName: 'Happy',
            lastName: 'Path',
            year: 2017,
            make: 'Subaru',
            model: 'Outback',
            concernSummary: 'Front brake pulsation under light stop.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Front brake job',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Replace front pads and rotors', 'total_cents' => 42000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Front pad and rotor kit', 'total_cents' => 28500],
                ],
            ]],
        );

        $this->assertNoIssuedInvoice($repairOrder);

        return new OperationalAuditScenario(
            key: 'happy_path',
            title: 'Happy Path',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Brake job approved and ready for pickup. Invoice not issued — exercise Generate Final Invoice and first payment.',
            expectations: 'Generate Final Invoice → record payment → close.',
        );
    }

    private function seedPartialPayment(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '02',
            firstName: 'Partial',
            lastName: 'Payment',
            year: 2016,
            make: 'Ford',
            model: 'F-150',
            concernSummary: 'Transmission service and rear main concern.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Transmission service',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Transmission service', 'total_cents' => 85000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Fluid and filter kit', 'total_cents' => 40000],
                ],
            ]],
        );

        $invoice = $this->generateInvoice->execute($repairOrder->fresh(), $actor);
        $this->recordLedger->recordPayment($repairOrder->fresh(), 50000, PaymentMethod::Card, $actor, 'Counter card swipe');

        $this->assertBalance($repairOrder, 75000);
        $this->assertInvoiceTotal($invoice, 125000);

        return new OperationalAuditScenario(
            key: 'partial_payment',
            title: 'Partial Payment',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Invoice $1,250 with $500 card collected. Balance should read $750.',
            expectations: 'Partially paid posture. Close blocked until balance cleared.',
        );
    }

    private function seedMultiplePayments(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '03',
            firstName: 'Split',
            lastName: 'Tender',
            year: 2015,
            make: 'Chevrolet',
            model: 'Silverado',
            concernSummary: 'Cooling system repair after overheat.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Cooling system repair',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Cooling system repair', 'total_cents' => 65000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Radiator and hoses', 'total_cents' => 30000],
                ],
            ]],
        );

        $this->generateInvoice->execute($repairOrder->fresh(), $actor);
        $this->recordLedger->recordPayment($repairOrder->fresh(), 30000, PaymentMethod::Cash, $actor, 'First split');
        $this->recordLedger->recordPayment($repairOrder->fresh(), 30000, PaymentMethod::Card, $actor, 'Second split');
        $this->recordLedger->recordPayment($repairOrder->fresh(), 35000, PaymentMethod::Check, $actor, 'Check #1042');

        $this->assertBalance($repairOrder, 0);

        return new OperationalAuditScenario(
            key: 'multiple_payments',
            title: 'Multiple Payments',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Invoice $950 paid as $300 cash + $300 card + $350 check.',
            expectations: 'Ledger history shows three payments. Paid / ready to close.',
        );
    }

    private function seedOverpaymentStoreCredit(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '04',
            firstName: 'Overpay',
            lastName: 'Customer',
            year: 2019,
            make: 'Toyota',
            model: 'Tacoma',
            concernSummary: 'Alignment after lift install.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Alignment',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Four-wheel alignment', 'total_cents' => 100000],
                ],
            ]],
        );

        $this->generateInvoice->execute($repairOrder->fresh(), $actor);
        // Non-cash overpay still mints store credit; cash overpay assumes change at the counter.
        $this->recordLedger->recordPayment($repairOrder->fresh(), 110000, PaymentMethod::Card, $actor, 'Card charged above balance');

        $this->assertBalance($repairOrder, 0);

        $customer = $repairOrder->fresh()->customer;

        if ((int) $customer->store_credit_balance_cents !== 10000) {
            throw new RuntimeException('Audit overpayment scenario expected $100.00 store credit.');
        }

        return new OperationalAuditScenario(
            key: 'overpayment_store_credit',
            title: 'Overpayment / Store Credit',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Invoice $1,000 with $1,100 card. $100 issued to store credit.',
            expectations: 'Balance due $0. Store credit note visible. No negative invoice balance.',
        );
    }

    private function seedNoApprovedWork(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '05',
            firstName: 'Recommend',
            lastName: 'Only',
            year: 2014,
            make: 'Nissan',
            model: 'Altima',
            concernSummary: 'Customer declined everything except inspection.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Coolant flush recommendation',
                'disposition' => RepairOrderConcernDisposition::Recommended,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Coolant flush', 'total_cents' => 18000],
                ],
            ], [
                'summary' => 'Deferred rear brakes',
                'disposition' => RepairOrderConcernDisposition::Deferred,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Rear brake service', 'total_cents' => 42000],
                ],
            ]],
        );

        $this->assertNoIssuedInvoice($repairOrder);

        return new OperationalAuditScenario(
            key: 'no_approved_work',
            title: 'No Approved Work',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Recommendations and deferred work only at ready pickup.',
            expectations: 'Generate Final Invoice must not be available. Attempting issuance should fail authority.',
        );
    }

    private function seedInvoiceNotYetAllowed(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '06',
            firstName: 'Too',
            lastName: 'Early',
            year: 2020,
            make: 'Jeep',
            model: 'Wrangler',
            concernSummary: 'Still on the lift — customer waiting in lobby.',
            status: RepairOrderStatus::InProgress,
            concerns: [[
                'summary' => 'Suspension clunk',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Diagnose suspension clunk', 'total_cents' => 18500],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Control arm bushing kit', 'total_cents' => 24000],
                ],
            ]],
        );

        $this->assertNoIssuedInvoice($repairOrder);

        return new OperationalAuditScenario(
            key: 'invoice_not_yet_allowed',
            title: 'Invoice Not Yet Allowed',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Approved work exists but RO is still in progress.',
            expectations: 'Financial rail hidden or invoice action unavailable until ready pickup.',
        );
    }

    private function seedDepositApplied(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '07',
            firstName: 'Deposit',
            lastName: 'First',
            year: 2021,
            make: 'Ram',
            model: '1500',
            concernSummary: 'Engine repair with large parts deposit collected upfront.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Engine repair',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Engine repair', 'total_cents' => 120000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Engine parts kit', 'total_cents' => 80000],
                ],
            ]],
        );

        $this->recordLedger->recordDeposit($repairOrder->fresh(), 50000, PaymentMethod::Cash, $actor, 'Parts deposit at drop-off');
        $this->generateInvoice->execute($repairOrder->fresh(), $actor);

        $this->assertBalance($repairOrder, 150000);

        return new OperationalAuditScenario(
            key: 'deposit_applied',
            title: 'Deposit Applied',
            repairOrder: $repairOrder->fresh(),
            purpose: '$500 deposit collected before invoice. Invoice $2,000. Balance $1,500.',
            expectations: 'Deposit visible against invoice. Remaining balance collectible.',
        );
    }

    private function seedReadyPickupUnpaidAging(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '08',
            firstName: 'Refuses',
            lastName: 'Pickup',
            year: 2013,
            make: 'Honda',
            model: 'Accord',
            concernSummary: 'Customer arguing about price at pickup.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Timing cover reseal',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Timing cover reseal', 'total_cents' => 95000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Gaskets and sealant', 'total_cents' => 18000],
                ],
            ]],
        );

        $this->generateInvoice->execute($repairOrder->fresh(), $actor);
        $this->assertBalance($repairOrder, 113000);

        $repairOrder->forceFill([
            'updated_at' => now()->subDays(6),
        ])->save();

        return new OperationalAuditScenario(
            key: 'ready_pickup_unpaid',
            title: 'Ready Pickup Unpaid / Aging',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Invoice issued, full balance due, vehicle sitting ready for pickup.',
            expectations: 'Unpaid pickup queue posture. Close blocked. Aging signal should feel urgent.',
        );
    }

    private function seedClosedPaid(?User $actor): OperationalAuditScenario
    {
        $repairOrder = $this->createRepairOrder(
            code: '09',
            firstName: 'Closed',
            lastName: 'Paid',
            year: 2018,
            make: 'Mazda',
            model: 'CX-5',
            concernSummary: 'Completed brake and tire service — reference closeout.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Brake and tire service',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Brake and tire service', 'total_cents' => 32000],
                    ['type' => RepairOrderLineType::Part, 'description' => 'Pads and tires', 'total_cents' => 48000],
                ],
            ]],
        );

        $this->generateInvoice->execute($repairOrder->fresh(), $actor);
        $balance = $this->balanceDue->forRepairOrder($repairOrder->fresh());
        $this->recordLedger->recordPayment($repairOrder->fresh(), $balance->balanceDueCents, PaymentMethod::Card, $actor, 'Paid at pickup');
        $this->lifecycle->move($repairOrder->fresh(), RepairOrderStatus::Closed, $actor);

        $this->assertBalance($repairOrder, 0);

        return new OperationalAuditScenario(
            key: 'closed_paid',
            title: 'Closed Paid',
            repairOrder: $repairOrder->fresh(),
            purpose: 'Reference RO that finished invoice → payment → close.',
            expectations: 'Terminal closed posture. Compare against active unpaid scenarios.',
        );
    }

    private function seedMultiVehicleRepeatCustomer(?User $actor): OperationalAuditScenario
    {
        $customer = $this->createCustomer('10', 'Repeat', 'Fleet');
        $camry = $this->createVehicle($customer, '2016', 'Toyota', 'Camry', 'Audit Camry');
        $crv = $this->createVehicle($customer, '2019', 'Honda', 'CR-V', 'Audit CR-V');

        $priorClosed = $this->createRepairOrderFor(
            customer: $customer,
            vehicle: $camry,
            concernSummary: 'Prior visit — paid oil service, deferred rear shocks.',
            status: RepairOrderStatus::ReadyPickup,
            concerns: [[
                'summary' => 'Oil service',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Oil service', 'total_cents' => 6500],
                ],
            ], [
                'summary' => 'Rear shocks worn',
                'disposition' => RepairOrderConcernDisposition::Deferred,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Replace rear shocks', 'total_cents' => 52000],
                ],
            ]],
        );

        $this->generateInvoice->execute($priorClosed->fresh(), $actor);
        $balance = $this->balanceDue->forRepairOrder($priorClosed->fresh());
        $this->recordLedger->recordPayment($priorClosed->fresh(), $balance->balanceDueCents, PaymentMethod::Cash, $actor);
        $this->lifecycle->move($priorClosed->fresh(), RepairOrderStatus::Closed, $actor);

        $active = $this->createRepairOrderFor(
            customer: $customer,
            vehicle: $crv,
            concernSummary: 'Customer returned — vibration at highway speed.',
            status: RepairOrderStatus::Estimate,
            concerns: [[
                'summary' => 'Wheel balance and rotation',
                'disposition' => RepairOrderConcernDisposition::Approved,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Balance and rotate', 'total_cents' => 9500],
                ],
            ], [
                'summary' => 'Recommended alignment',
                'disposition' => RepairOrderConcernDisposition::Recommended,
                'lines' => [
                    ['type' => RepairOrderLineType::Labor, 'description' => 'Alignment', 'total_cents' => 12000],
                ],
            ]],
        );

        return new OperationalAuditScenario(
            key: 'multi_vehicle_repeat',
            title: 'Multi-Vehicle Repeat Customer',
            repairOrder: $active->fresh(),
            purpose: 'Repeat customer with deferred work on another vehicle and a new active RO.',
            expectations: 'Customer hub and vehicle continuity should surface prior deferred work without sales posture.',
            relatedRepairOrderIds: [$priorClosed->fresh()->repair_order_id],
        );
    }

    /**
     * @param  list<array{summary: string, disposition: RepairOrderConcernDisposition, lines: list<array{type: RepairOrderLineType, description: string, total_cents: int}>}>  $concerns
     */
    private function createRepairOrder(
        string $code,
        string $firstName,
        string $lastName,
        int $year,
        string $make,
        string $model,
        string $concernSummary,
        RepairOrderStatus $status,
        array $concerns,
    ): RepairOrder {
        $customer = $this->createCustomer($code, $firstName, $lastName);
        $vehicle = $this->createVehicle($customer, (string) $year, $make, $model, "{$firstName} {$model}");

        return $this->createRepairOrderFor($customer, $vehicle, $concernSummary, $status, $concerns);
    }

    /**
     * @param  list<array{summary: string, disposition: RepairOrderConcernDisposition, lines: list<array{type: RepairOrderLineType, description: string, total_cents: int}>}>  $concerns
     */
    private function createRepairOrderFor(
        Customer $customer,
        Vehicle $vehicle,
        string $concernSummary,
        RepairOrderStatus $status,
        array $concerns,
    ): RepairOrder {
        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => $status,
            'concern_summary' => $concernSummary,
        ]);

        foreach ($concerns as $position => $concernData) {
            $concern = RepairOrderConcern::query()->create([
                'repair_order_id' => $repairOrder->id,
                'summary' => $concernData['summary'],
                'disposition' => $concernData['disposition'],
                'recommendation_intent' => 'maintenance',
                'position' => $position + 1,
            ]);

            foreach ($concernData['lines'] as $lineData) {
                RepairOrderLine::query()->create([
                    'repair_order_id' => $repairOrder->id,
                    'repair_order_concern_id' => $concern->id,
                    'type' => $lineData['type'],
                    'description' => $lineData['description'],
                    'quantity' => '1.00',
                    'unit_price_cents' => $lineData['total_cents'],
                    'subtotal_cents' => $lineData['total_cents'],
                    'tax_cents' => 0,
                    'shop_fee_cents' => 0,
                    'total_cents' => $lineData['total_cents'],
                ]);
            }
        }

        $this->totalsCalculator->recalculateRepairOrder($repairOrder->fresh());

        return $repairOrder->fresh();
    }

    private function createCustomer(string $code, string $firstName, string $lastName): Customer
    {
        return Customer::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '555-09'.$code,
            'email' => 'audit-'.$code.'@arksms.audit',
            'notes' => self::MARKER.' '.$firstName.' '.$lastName,
        ]);
    }

    private function createVehicle(Customer $customer, string $year, string $make, string $model, string $nickname): Vehicle
    {
        return Vehicle::query()->create([
            'customer_id' => $customer->id,
            'year' => (int) $year,
            'make' => $make,
            'model' => $model,
            'nickname' => $nickname,
            'plate' => 'AUD'.substr(md5($nickname), 0, 4),
            'plate_state' => 'CO',
        ]);
    }

    private function assertBalance(RepairOrder $repairOrder, int $expectedCents): void
    {
        $actual = $this->balanceDue->forRepairOrder($repairOrder->fresh())->balanceDueCents;

        if ($actual !== $expectedCents) {
            throw new RuntimeException(
                "Audit balance mismatch on RO #{$repairOrder->repair_order_id}: expected {$expectedCents}, got {$actual}.",
            );
        }
    }

    private function assertNoIssuedInvoice(RepairOrder $repairOrder): void
    {
        if ($this->balanceDue->issuedInvoice($repairOrder->fresh()) !== null) {
            throw new RuntimeException("Audit scenario expected no issued invoice on RO #{$repairOrder->repair_order_id}.");
        }
    }

    private function assertInvoiceTotal(EstimateDocument $invoice, int $expectedCents): void
    {
        $actual = (int) data_get($invoice->snapshot_json, 'totals.total_cents', 0);

        if ($actual !== $expectedCents) {
            throw new RuntimeException("Audit invoice total mismatch: expected {$expectedCents}, got {$actual}.");
        }
    }
}
