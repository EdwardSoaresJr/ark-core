<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('send estimate creates access token and sends sms conversation message', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder));

    $response->assertOk()
        ->assertJsonPath('token_reused', false)
        ->assertJsonPath('awaiting_approval.moved', true)
        ->assertJsonPath('awaiting_approval.reason', 'moved')
        ->assertJsonPath('awaiting_approval.from_status', RepairOrderStatus::Estimate->value)
        ->assertJsonPath('awaiting_approval.to_status', RepairOrderStatus::WaitingApproval->value)
        ->assertJsonStructure(['estimate_url', 'html', 'message_id', 'awaiting_approval' => ['toast']]);

    $token = EstimateAccessToken::query()->sole();
    $message = ConversationMessage::query()->sole();
    $responseUrl = $response->json('estimate_url');

    expect($token->repair_order_id)->toBe($repairOrder->id)
        ->and($token->created_by_user_id)->toBe($advisor->id)
        ->and($token->token_hash)->toBe(EstimateAccessToken::hashPlainToken(
            (string) str($responseUrl)->after('/portal/estimates/'),
        ))
        ->and($token->getAttributes())->not->toHaveKey('token')
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('/go/')
        ->and($message->body)->not->toContain($responseUrl)
        ->and($message->body)->toContain('Your estimate is ready:')
        ->and(strlen($message->body))->toBeLessThan(120)
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->metadata['repair_order_id'])->toBe($repairOrder->id)
        ->and($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeTrue()
        ->and($response->json('awaiting_approval.toast'))->toContain('Waiting Approval');
});

test('resending estimate link keeps waiting approval and reports already waiting', function (): void {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();

    $firstResponse = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk()
        ->assertJsonPath('awaiting_approval.moved', true);

    $firstPlainToken = (string) str($firstResponse->json('estimate_url'))->after('/portal/estimates/');
    $firstTokenId = EstimateAccessToken::query()->sole()->id;

    $secondResponse = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk()
        ->assertJsonPath('token_reused', true)
        ->assertJsonPath('awaiting_approval.moved', false)
        ->assertJsonPath('awaiting_approval.reason', 'already_waiting');

    $secondPlainToken = (string) str($secondResponse->json('estimate_url'))->after('/portal/estimates/');

    expect(EstimateAccessToken::query()->count())->toBe(2)
        ->and($secondPlainToken)->not->toBe($firstPlainToken)
        ->and(EstimateAccessToken::query()->findOrFail($firstTokenId)->revoked_at)->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(2);

    $this->get(route('portal.estimates.show', ['token' => $firstPlainToken]))
        ->assertOk()
        ->assertSee('Repair estimate');

    $this->get(route('portal.estimates.show', ['token' => $secondPlainToken]))
        ->assertOk()
        ->assertSee('Repair estimate');
});

test('portal estimate opens with customer safe projection and no pdf requirement', function () {
    $repairOrder = estimateLinkRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => 'labor',
        'description' => 'Replace water pump',
        'quantity' => '3.75',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 56250,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 56250,
    ]);

    $plainToken = str_repeat('a', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => null,
    ]);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Repair estimate')
        ->assertSee($repairOrder->vehicle->display_name)
        ->assertSee('RO #'.$repairOrder->repair_order_id)
        ->assertSee('Total')
        ->assertDontSee('Estimate total')
        ->assertSee('$562.50')
        ->assertDontSee('labor_rate_cents')
        ->assertDontSee('part_cost');
});

test('portal estimate renders without marketing financing chrome', function () {
    $repairOrder = estimateLinkRepairOrder();

    $plainToken = str_repeat('a', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'created_by_user_id' => null,
    ]);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertDontSee('public-financing-note', false)
        ->assertDontSee('Prequalify now', false);
});

test('portal estimate hides draft concerns from customer view', function () {
    $repairOrder = estimateLinkRepairOrder();

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Hidden draft diagnostic',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    $plainToken = str_repeat('d', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Water pump replacement')
        ->assertSee('Awaiting your approval')
        ->assertDontSee('Awaiting Approval')
        ->assertDontSee('Hidden draft diagnostic');
});

test('portal estimate shows approved customer status when only approved work is visible', function () {
    $repairOrder = estimateLinkRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $concern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Hidden draft follow-up',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    $repairOrder->update(['status' => RepairOrderStatus::WaitingApproval]);

    $plainToken = str_repeat('e', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Approved')
        ->assertSee('Work approved')
        ->assertDontSee('Waiting Approval')
        ->assertDontSee('Submit authorization')
        ->assertDontSee('Hidden draft follow-up');
});

test('portal estimate uses customer-facing part labels matching estimate pdf', function () {
    $repairOrder = estimateLinkRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Brakebest Select Ceramic Disc Brake Pad Set',
        'quantity' => '1',
        'unit_price_cents' => 12500,
        'subtotal_cents' => 12500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 12500,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $plainToken = str_repeat('f', 64);
    EstimateAccessToken::createForPlainToken($repairOrder->fresh(), $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Ceramic Disc Brake Pad Set', false);
});

test('portal estimate denies invalid token', function () {
    $this->get(route('portal.estimates.show', ['token' => str_repeat('z', 64)]))
        ->assertNotFound();
});

test('portal estimate denies revoked token', function () {
    $repairOrder = estimateLinkRepairOrder();

    $plainToken = str_repeat('b', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken, [
        'revoked_at' => now(),
    ]);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertNotFound();
});

test('ro review shows send estimate action on quick reply rail', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertSee('Send Estimate')
        ->assertDontSee('SMS Inbox');
});

test('portal estimate prepared date uses first estimate sent not latest access token', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    ShopSettings::current()->update(['shop_timezone' => 'America/Denver']);
    ShopDisplayTimezone::apply();

    Carbon::setTestNow(Carbon::parse('2026-06-26 17:30:00', 'America/Denver'));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();

    $firstResponse = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk();

    $firstPlainToken = (string) str($firstResponse->json('estimate_url'))->after('/portal/estimates/');

    Carbon::setTestNow(Carbon::parse('2026-06-27 08:15:00', 'America/Denver'));

    $secondResponse = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk()
        ->assertJsonPath('token_reused', true);

    $secondPlainToken = (string) str($secondResponse->json('estimate_url'))->after('/portal/estimates/');

    $this->get(route('portal.estimates.show', ['token' => $secondPlainToken]))
        ->assertOk()
        ->assertSee('Prepared Jun 26, 2026')
        ->assertDontSee('Prepared Jun 27, 2026');

    $this->get(route('portal.estimates.show', ['token' => $firstPlainToken]))
        ->assertOk()
        ->assertSee('Prepared Jun 26, 2026');
});

test('portal estimate shows step indicator and collapsible service details', function () {
    $repairOrder = estimateLinkRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Water pump assembly',
        'quantity' => '1',
        'unit_price_cents' => 12500,
        'subtotal_cents' => 12500,
        'tax_cents' => 0,
        'shop_fee_cents' => 0,
        'total_cents' => 12500,
    ]);

    app(EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $plainToken = str_repeat('g', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Authorize')
        ->assertSee('Approved total', false)
        ->assertSee('portal-estimate-authorize-shell', false)
        ->assertDontSee('Authorize work', false)
        ->assertSee('Review')
        ->assertSee('Recommended Work')
        ->assertSee('Service 1')
        ->assertSee('portal-estimate-mobile-bar');
});

function estimateLinkRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Estimate',
        'last_name' => 'Customer',
        'phone' => '7195558080',
        'customer_type' => 'Retail',
    ]);

    \App\Ark\Operations\Messaging\PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => \App\Ark\Operations\PhoneNumber::normalize('7195558080')],
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
        'year' => 2018,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'vin' => '1C4HJXDG6EW123456',
        'normalized_vin' => '1C4HJXDG6EW123456',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'repair_order_id' => 4401,
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Water pump noise',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Water pump replacement',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace water pump',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
        'position' => 1,
    ]);

    return $repairOrder;
}

test('send estimate blocks timing job missing oil and coolant', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = timingJobEstimateRepairOrder();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder));

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('This job is missing oil and coolant');
});

test('send estimate timing fluids override is allowed', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = timingJobEstimateRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'acknowledge_timing_fluids' => true,
        ])
        ->assertOk();
});

function timingJobEstimateRepairOrder(): RepairOrder
{
    $repairOrder = estimateLinkRepairOrder();
    $repairOrder->forceFill(['concern_summary' => 'Timing belt replacement'])->save();
    $repairOrder->concerns()->update(['summary' => 'Replace timing belt']);
    $repairOrder->lines()->update(['description' => 'Replace timing belt']);

    return $repairOrder->fresh(['lines', 'concerns', 'vehicle', 'customer']);
}
