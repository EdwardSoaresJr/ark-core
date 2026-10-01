<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Platform\Communications\CommunicationMessageContext;
use App\Ark\Platform\Communications\PlatformSmsTimelineProjection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_send', true);
    config()->set('services.ark_platform.communications_core_mirror', false);
    config()->set('app.display_timezone', 'America/Denver');
});

test('a hosted repair order send does not write a second core message', function () {
    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Bennett',
        'phone' => '7195550184',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
        'opened_at' => now(),
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/sms/messages/conversation' => Http::response([
            'ok' => true,
            'status' => 'provider_sent',
            'provider_message_id' => 'SMcontext1',
            'comm_message_public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'context' => [
                ['type' => 'repair_order', 'id' => (string) $repairOrder->repair_order_id],
                ['type' => 'vehicle', 'id' => (string) $vehicle->id],
            ],
        ], 200),
    ]);

    $result = app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'We found the noise.',
        repairOrder: $repairOrder,
    );

    expect($result['message'])->toBeNull()
        ->and($result['provider_message_sid'])->toBe('SMcontext1')
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(config('services.ark_platform.communications_core_mirror'))->toBeFalse();

    Http::assertSent(function ($request) use ($repairOrder, $vehicle) {
        $data = $request->data();
        $context = $data['context'] ?? [];

        return str_contains($request->url(), '/messages/conversation')
            && ($data['body'] ?? null) === 'We found the noise.'
            && ($data['media_urls'] ?? null) === []
            && collect($context)->contains(fn (array $reference): bool => $reference['type'] === 'repair_order' && $reference['id'] === (string) $repairOrder->repair_order_id)
            && collect($context)->contains(fn (array $reference): bool => $reference['type'] === 'vehicle' && $reference['id'] === (string) $vehicle->id)
            && ! str_contains($request->url(), 'twilio');
    });
});

test('the repair order timeline shows only messages that name that repair order', function () {
    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Ben',
        'last_name' => 'Carter',
        'phone' => '7195550188',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2015,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Brakes',
        'opened_at' => now(),
    ]);
    $other = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Oil',
        'opened_at' => now(),
    ]);

    $photoId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    Http::fake(function ($request) use ($repairOrder, $vehicle, $photoId) {
        if (! str_contains($request->url(), '/communications/messages')) {
            return Http::response(['ok' => true, 'conversations' => [], 'messages' => []], 200);
        }

        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        if (($query['reference_id'] ?? '') !== (string) $repairOrder->repair_order_id) {
            return Http::response(['ok' => true, 'messages' => []], 200);
        }

        return Http::response([
            'ok' => true,
            'messages' => [[
                'public_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'direction' => 'outbound',
                'body' => 'Inspection is ready',
                'occurred_at' => now()->toIso8601String(),
                'delivery_status' => 'sent',
                'attachments' => [[
                    'public_id' => $photoId,
                    'disposition' => 'stored',
                    'content_type' => 'image/jpeg',
                ]],
                'context' => [
                    ['type' => 'repair_order', 'id' => (string) $repairOrder->repair_order_id],
                    ['type' => 'vehicle', 'id' => (string) $vehicle->id],
                    ['type' => 'inspection', 'id' => '90'],
                ],
            ]],
        ], 200);
    });

    $entries = app(UnifiedOperationalTimeline::class)->forRepairOrderRelationship($repairOrder, 20);
    $sms = $entries->first(fn ($entry) => $entry->kind === OperationalEventKind::Sms);

    expect($sms)->not->toBeNull()
        ->and($sms->body)->toBe('Inspection is ready')
        ->and($sms->metadata['context_label'])->toContain('RO '.$repairOrder->repair_order_id)
        ->and($sms->metadata['context_label'])->toContain('2015 Honda Civic')
        ->and($sms->metadata['attachments'][0]['url'])->toContain('/app/communications/attachments/'.$photoId);

    $html = view('operations.timeline.partials.event-bubble', ['event' => $sms])->render();
    expect($html)->toContain('alt="Photo"')
        ->and($html)->toContain('RO '.$repairOrder->repair_order_id)
        ->and($html)->toContain('2015 Honda Civic')
        ->and($html)->not->toContain('api.twilio.com');

    expect(app(PlatformSmsTimelineProjection::class)->forRepairOrder($other))->toHaveCount(0);
});

test('repair order sends keep added inspection and payment references on the same message', function () {
    bindFakeOutboundSms();
    seedMobileSmsCapability('7195550191');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $customer = Customer::query()->create([
        'first_name' => 'Rita',
        'last_name' => 'Nguyen',
        'phone' => '7195550191',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);
    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise',
        'opened_at' => now(),
    ]);

    app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Inspection is ready.',
        repairOrder: $repairOrder,
        context: CommunicationMessageContext::reference([], 'inspection', 90),
    );

    $inspectionTypes = collect(app(OutboundSmsTransport::class)->sent[0]['context'])->pluck('type')->all();
    expect($inspectionTypes)->toBe(['repair_order', 'vehicle', 'inspection']);

    app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Payment link.',
        repairOrder: $repairOrder,
        context: CommunicationMessageContext::reference([], 'payment_request', 15),
    );

    $paymentTypes = collect(app(OutboundSmsTransport::class)->sent[1]['context'])->pluck('type')->all();
    expect($paymentTypes)->toBe(['repair_order', 'vehicle', 'payment_request']);

    app(SendOutboundMessageAction::class)->execute(
        customer: $customer,
        actor: $advisor,
        body: 'Deposit request.',
        repairOrder: $repairOrder,
        context: CommunicationMessageContext::reference([], 'deposit_request', 16),
    );

    $depositTypes = collect(app(OutboundSmsTransport::class)->sent[2]['context'])->pluck('type')->all();
    expect($depositTypes)->toBe(['repair_order', 'vehicle', 'deposit_request']);
});

test('the composer source submits every selected file', function () {
    $script = file_get_contents(base_path('resources/js/ark-conversation-quick-reply.js'));
    $composer = file_get_contents(base_path('resources/views/operations/communications/workspace/partials/composer-panel.blade.php'));

    expect($script)->toContain("formData.append('attachments[]', file)")
        ->and($script)->toContain('this.attachments = files')
        ->and($script)->not->toContain("formData.append('attachment', this.attachment)")
        ->and($composer)->toContain('multiple');
});
