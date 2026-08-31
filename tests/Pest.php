<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/Support/FinancialAuthorityFixture.php';
require_once __DIR__.'/Support/FinancialCloseout.php';
require_once __DIR__.'/Support/FlagRecognitionFixture.php';
require_once __DIR__.'/Support/IdentityHeaderFixture.php';
require_once __DIR__.'/Support/LearnArkTraining.php';
require_once __DIR__.'/Support/QueryBudget.php';
require_once __DIR__.'/Support/QueryBudgetFixtures.php';
require_once __DIR__.'/Support/SmsConsentTestHelpers.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'shop_timezone')) {
            return;
        }

        ShopSettings::forgetCurrent();

        $settings = ShopSettings::current();

        if (! filled($settings->shop_timezone)) {
            $settings->update(['shop_timezone' => ShopSettings::INSTALL_DEFAULT_TIMEZONE]);
        }

        if ($settings->learn_training_gate_enabled !== false) {
            $settings->update(['learn_training_gate_enabled' => false]);
        }

        ShopDisplayTimezone::apply();
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function repairOrderForCommunication(RepairOrderStatus $status, string $customerName = 'Comm Customer'): RepairOrder
{
    [$firstName, $lastName] = array_pad(explode(' ', $customerName, 2), 2, 'Customer');

    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
        'email' => 'customer@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'COM123',
        'year' => 2021,
        'make' => 'Honda',
        'model' => 'Civic',
        'vin' => '2HGFC2F59MH123456',
        'normalized_vin' => '2HGFC2F59MH123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Customer states vehicle needs service.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_intent' => 'maintenance',
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    return $repairOrder;
}

function decisionPressureRepairOrder(
    string $firstName,
    string $lastName,
    RepairOrderStatus $status,
    int $lineCents,
    RepairOrderConcernDisposition $disposition = RepairOrderConcernDisposition::Recommended,
): RepairOrder {
    $customer = Customer::query()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => '555-0100',
        'email' => strtolower($firstName).'.'.strtolower($lastName).'@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DP'.random_int(100, 999),
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'concern_summary' => 'Decision pressure coverage.',
    ])->fresh();

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Customer concern',
        'disposition' => $disposition,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Labor',
        'quantity' => '1.00',
        'unit_price_cents' => $lineCents,
        'subtotal_cents' => $lineCents,
        'total_cents' => $lineCents,
    ]);

    if ($disposition === RepairOrderConcernDisposition::Approved) {
        ApprovalEvent::query()->create([
            'visit_id' => $repairOrder->id,
            'estimate_snapshot_reference' => 'test-snapshot',
            'approval_type' => ApprovalType::Repair,
            'approved_amount_cents' => $lineCents,
            'source' => ApprovalSource::InPerson,
            'approved_by' => 'Advisor',
            'approved_at' => now(),
        ]);
    }

    return $repairOrder->fresh(['customer', 'vehicle', 'lines', 'concerns']);
}

function repairOrderForEstimateWorkspace(string $customerType = 'Retail'): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
        'customer_type' => $customerType,
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'ARK123',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'concern_summary' => 'Customer states engine rattles on cold start.',
    ])->fresh();
}

function concernForEstimateWorkspace(RepairOrder $repairOrder): RepairOrderConcern
{
    $repairOrder->loadMissing('customer');

    return RepairOrderConcern::query()->firstOrCreate(
        [
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Estimate work',
        ],
        [
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'billing_posture' => ConcernBillingPosture::defaultForCustomerTag($repairOrder->customer?->customer_type),
            'recommendation_intent' => 'maintenance',
            'position' => 1,
        ],
    );
}
