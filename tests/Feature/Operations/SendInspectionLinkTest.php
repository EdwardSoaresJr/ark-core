<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Portal\InspectionAccessToken;
use App\Ark\Operations\Portal\PortalShortLink;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

function seedInspectionLinkSmsCapablePhone(string $phone = '7195551212'): void
{
    $normalized = PhoneNumber::normalize($phone) ?? $phone;

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => $normalized],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test Carrier',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ],
    );
}

test('send inspection link creates access token and sends sms conversation message', function () {
    seedInspectionLinkSmsCapablePhone();
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMinspection01',
            'status' => 'queued',
        ], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionLinkRepairOrder();

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder));

    $response->assertOk()
        ->assertJsonStructure(['inspection_url', 'html', 'message_id']);

    expect($response->json('inspection_url'))->toContain('/portal/inspections/');

    $token = InspectionAccessToken::query()->sole();
    $message = ConversationMessage::query()->sole();

    expect($token->repair_order_id)->toBe($repairOrder->id)
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('/go/')
        ->and($message->body)->not->toContain('/portal/inspections/')
        ->and($message->body)->toContain('inspection results')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::Advisor)
        ->and($message->metadata['repair_order_id'])->toBe($repairOrder->id)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InspectionSent)->exists())->toBeTrue();
});

test('resending inspection link reuses the same short url', function () {
    seedInspectionLinkSmsCapablePhone();
    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMinspection-resend', 'status' => 'queued'], 201),
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionLinkRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder))
        ->assertOk();

    $firstGo = (string) str(ConversationMessage::query()->orderBy('id')->value('body'))->after('/go/');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder))
        ->assertOk();

    $secondGo = (string) str(ConversationMessage::query()->orderByDesc('id')->value('body'))->after('/go/');

    expect($secondGo)->toBe($firstGo)
        ->and($firstGo)->not->toBe('')
        ->and(PortalShortLink::query()->count())->toBe(1)
        ->and(InspectionAccessToken::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->count())->toBe(2);
});

test('send inspection link requires recorded findings', function () {
    seedInspectionLinkSmsCapablePhone();
    Http::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionLinkRepairOrder(recordFinding: false);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Record at least one inspection finding before sharing.');

    expect(InspectionAccessToken::query()->count())->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('customer can open inspection portal with token', function () {
    seedInspectionLinkSmsCapablePhone();
    $repairOrder = inspectionLinkRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMinspection02', 'status' => 'queued'], 201),
    ]);

    $response = $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder));

    $url = (string) $response->json('inspection_url');
    $plainToken = str($url)->afterLast('/')->toString();

    $this->get(route('portal.inspections.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Vehicle Inspection')
        ->assertSee('Front brake pads');
});

test('staff preview token does not invalidate existing customer inspection link', function () {
    $repairOrder = inspectionLinkRepairOrder();
    $plainToken = str_repeat('e', 64);

    InspectionAccessToken::createForPlainToken($repairOrder, $plainToken);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.inspection-portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview')
        ->assertSee('Vehicle Inspection');

    expect(InspectionAccessToken::query()->count())->toBe(2);

    $this->get(route('portal.inspections.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Front brake pads');
});

test('inspection portal link json returns customer url', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionLinkRepairOrder();

    $response = $this->actingAs($advisor)
        ->getJson(route('operations.repair-orders.inspection-portal-link', $repairOrder));

    $response->assertOk()
        ->assertJsonStructure(['url', 'token_reused']);

    expect($response->json('url'))->toContain('/portal/inspections/');
    expect(InspectionAccessToken::query()->count())->toBe(1);
});

function inspectionLinkRepairOrder(bool $recordFinding = true): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Pat',
        'last_name' => 'Customer',
        'phone' => '7195551212',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'repair_order_id' => 9001,
        'concern_summary' => 'Brakes',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes',
        'position' => 1,
    ]);

    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, null);

    if ($recordFinding) {
        InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => 'brakes',
            'checklist_category_name' => 'Brakes',
            'label' => 'Front brake pads',
            'observed_state' => InspectionObservedState::Fail->value,
            'notes' => 'Pads worn.',
            'position' => 0,
        ]);
    }

    return $repairOrder->fresh(['customer', 'vehicle']);
}

test('hosted platform inspection send records inspection_sent without a core conversation message', function () {
    enableHostedPlatformSendWithoutCoreMirror();
    seedInspectionLinkSmsCapablePhone();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = inspectionLinkRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder))
        ->assertOk()
        ->assertJsonPath('message_id', null);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::InspectionSent)->exists())->toBeTrue();
});

