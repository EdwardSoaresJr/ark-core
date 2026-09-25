<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Today\TodayPipelineInventoryQuery;
use App\Ark\Operations\Workboard\WorkboardSwimlaneCatalog;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

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

test('a radiator job sends without a missing-item warning', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();
    $repairOrder->forceFill(['concern_summary' => 'Radiator replacement'])->save();
    $repairOrder->concerns()->update(['summary' => 'Replace radiator']);
    $repairOrder->lines()->update(['description' => 'Replace radiator']);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertDontSee('This job is missing', false);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk();
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
        ->assertSee('Approve')
        ->assertSee('Approved total', false)
        ->assertSee('portal-estimate-authorize-shell', false)
        ->assertDontSee('Authorize work', false)
        ->assertSee('Review')
        ->assertSee('Recommended Work')
        ->assertSee('Water pump replacement')
        ->assertSee('Replace water pump', false)
        ->assertSee('Water Pump', false)
        ->assertDontSee('Water pump assembly', false)
        ->assertSee('Price details', false)
        ->assertSee('portal-estimate-mobile-bar');
});

test('send is blocked when every concern on the estimate is still draft', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();
    $repairOrder->concerns()->update([
        'disposition' => RepairOrderConcernDisposition::Draft,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft coolant leak',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('message', RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeFalse()
        ->and(EstimateAccessToken::query()->count())->toBe(0)
        ->and($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue()
        ->and($repairOrder->fresh()->concerns->every(
            fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Draft,
        ))->toBeTrue();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'email' => 'estimate.customer@example.test',
        ])
        ->assertSessionHasErrors([
            'email' => RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE,
        ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.estimate-portal-link', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('message', RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE);

    $projection = app(\App\Ark\Operations\Messaging\RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder->fresh(), $advisor)['estimate'];

    expect($projection['can_sms'])->toBeFalse()
        ->and($projection['can_email'])->toBeFalse()
        ->and($projection['send_block_reason'])->toBe(RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.workspace-tabs.show', [
            'repairOrder' => $repairOrder,
            'tab' => 'comms',
        ]))
        ->assertOk()
        ->assertSee(RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE, false)
        ->assertSee('Review concerns', false)
        ->assertSee('#estimate-lines', false);
});

test('a mixed estimate sends recommended work and leaves draft concerns draft', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();
    $recommended = $repairOrder->concerns()->firstOrFail();

    $includedDraft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft brake inspection',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $includedDraft->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 8000,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
        'position' => 1,
    ]);

    $unrelatedDraft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft alignment note',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 3,
    ]);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder));

    $response->assertOk()
        ->assertJsonPath('awaiting_approval.moved', true)
        ->assertJsonPath('awaiting_approval.to_status', RepairOrderStatus::WaitingApproval->value);

    expect($recommended->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($includedDraft->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft)
        ->and($unrelatedDraft->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft)
        ->and($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and(WorkboardSwimlaneCatalog::laneKeyForRepairOrder($repairOrder->fresh()))->toBe('waiting_approval')
        ->and(TodayPipelineInventoryQuery::apply(
            RepairOrder::query(),
            TodayPipelineInventoryQuery::AWAITING_APPROVAL,
        )->whereKey($repairOrder->id)->exists())->toBeTrue();

    $snapshot = app(\App\Ark\Operations\Documents\EstimateSnapshotBuilder::class)->build($repairOrder->fresh());
    $presented = app(\App\Ark\Operations\Documents\CustomerFacingDocumentBoundary::class)->sanitize($snapshot);
    $summaries = collect($presented['concerns'] ?? [])->pluck('summary');

    expect($summaries->all())->toContain('Water pump replacement')
        ->and($summaries->all())->not->toContain('Draft brake inspection')
        ->and($summaries->all())->not->toContain('Draft alignment note')
        ->and(app(\App\Ark\Operations\Portal\PortalEstimateAuthorization::class)
            ->presentedConcerns($repairOrder->fresh())
            ->pluck('id')
            ->all())->toBe([$recommended->id]);
});

test('sending an estimate does not authorize work', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195558080');
    ShopSettings::current()->update(['portal_signature_required' => false]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();
    $recommended = $repairOrder->concerns()->firstOrFail();
    $draft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft coolant leak',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk();

    expect($recommended->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($draft->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft);

    $plainToken = (string) str($response->json('estimate_url'))->after('/portal/estimates/');

    $this->post(route('portal.estimates.authorize', ['token' => $plainToken]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommended->id => RepairOrderConcernDisposition::Approved->value,
            $draft->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect(route('portal.estimates.show', ['token' => $plainToken]));

    expect($recommended->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($draft->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Draft);
});

test('a failed estimate text does not move the repair order to waiting approval', function () {
    bindFailingOutboundSms('Outbound SMS failed.');
    seedMobileSmsCapability('7195558080');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = estimateLinkRepairOrder();
    $concern = $repairOrder->concerns()->firstOrFail();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Outbound SMS failed.');

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeFalse()
        ->and($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue()
        ->and($concern->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended);
});

function estimateLinkRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Estimate',
        'last_name' => 'Customer',
        'phone' => '7195558080',
        'customer_type' => 'Retail',
    ]);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => PhoneNumber::normalize('7195558080')],
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
