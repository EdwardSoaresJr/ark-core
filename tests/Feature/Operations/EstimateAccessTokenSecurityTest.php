<?php

use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\Portal\ResolveEstimateAccessTokenAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('generated estimate token is stored as a hash only', function () {
    $repairOrder = estimateTokenSecurityRepairOrder();

    $result = app(CreateOrReuseEstimateAccessTokenAction::class)->execute($repairOrder);

    $stored = EstimateAccessToken::query()->sole();

    expect($stored->token_hash)->toBe(hash('sha256', $result->plainToken))
        ->and(strlen($result->plainToken))->toBe(64)
        ->and($stored->getAttributes())->not->toHaveKey('token');
});

test('public estimate access works with the raw token', function () {
    $repairOrder = estimateTokenSecurityRepairOrder();
    $plainToken = str_repeat('a', 64);

    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Repair estimate');
});

test('invalid estimate token is denied', function () {
    $repairOrder = estimateTokenSecurityRepairOrder();
    EstimateAccessToken::createForPlainToken($repairOrder, str_repeat('b', 64));

    $this->get(route('portal.estimates.show', ['token' => str_repeat('z', 64)]))
        ->assertNotFound();
});

test('estimate token lookup uses sha256 hash comparison', function () {
    $repairOrder = estimateTokenSecurityRepairOrder();
    $plainToken = str_repeat('c', 64);

    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $resolved = app(ResolveEstimateAccessTokenAction::class)->execute($plainToken, touchViewed: false);

    expect($resolved)->toBeInstanceOf(EstimateAccessToken::class)
        ->and($resolved?->repair_order_id)->toBe($repairOrder->id)
        ->and($resolved?->token_hash)->toBe(hash('sha256', $plainToken));
});

test('resending estimate link creates a new token row without invalidating prior links', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMestimate-security',
            'status' => 'queued',
        ], 201),
    ]);

    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateTokenSecurityRepairOrder();

    $firstPlainToken = (string) str(
        $this->actingAs($advisor)
            ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
            ->assertOk()
            ->json('estimate_url')
    )->after('/portal/estimates/');

    $secondPlainToken = (string) str(
        $this->actingAs($advisor)
            ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
            ->assertOk()
            ->json('estimate_url')
    )->after('/portal/estimates/');

    expect(EstimateAccessToken::query()->count())->toBe(2)
        ->and($secondPlainToken)->not->toBe($firstPlainToken);

    $this->get(route('portal.estimates.show', ['token' => $firstPlainToken]))->assertOk();
    $this->get(route('portal.estimates.show', ['token' => $secondPlainToken]))->assertOk();
});

test('staff preview token does not invalidate existing customer estimate link', function () {
    $repairOrder = estimateTokenSecurityRepairOrder();
    $plainToken = str_repeat('d', 64);

    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview');

    expect(EstimateAccessToken::query()->count())->toBe(2);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Repair estimate');
});

function estimateTokenSecurityRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Portal',
        'last_name' => 'Customer',
        'phone' => '7195556060',
        'customer_type' => 'Retail',
    ]);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize('7195556060')],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
        ],
    );

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Subaru',
        'model' => 'Outback',
        'vin' => '4S4BSACC0K3123456',
        'normalized_vin' => '4S4BSACC0K3123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 5501,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Brake noise',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brake inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $concern = $repairOrder->concerns()->firstOrFail();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Brake inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 12000,
    ]);

    return $repairOrder;
}
