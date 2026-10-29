<?php

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\CommunicationsMessageQueuePresenter;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Communications\ShopTurnAttentionQueue;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\GenerateInvoiceSnapshotAction;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionItemCategory;
use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Parts\DealerQuote;
use App\Ark\Operations\Parts\DealerQuoteLine;
use App\Ark\Operations\RepairOrders\PartLineClassification;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderReadCache;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Settings\ShopSettingsSchema;
use App\Ark\Operations\Telephony\CallQueueResolver;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Orientation\Orientation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('matched caller orientation scaling: query count remains constant between 1 and 20 callers', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $createCallerWithOpenRo = function (int $index): CallSession {
        $technician = User::factory()->create([
            'name' => "Tech {$index}",
        ])->assignRole(ArkRole::Technician->value);

        $customer = Customer::query()->create([
            'first_name' => "Caller{$index}",
            'last_name' => 'Customer',
            'phone' => sprintf('555%07d', $index),
        ]);

        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'plate' => "PLT{$index}",
            'year' => 2020,
            'make' => 'Toyota',
            'model' => 'Tacoma',
        ]);

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => "Concern summary {$index}",
            'assigned_technician_id' => $technician->id,
            'opened_at' => Carbon::parse('2026-06-01 08:00:00')->addMinutes($index),
        ]);

        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => "Brake issue {$index}",
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ]);

        app(CommunicationEventRecorder::class)->record(
            $repairOrder,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            "Estimate emailed {$index}",
            occurredAt: Carbon::parse('2026-06-01 09:00:00')->addMinutes($index),
        );

        $inspection = Inspection::query()->create([
            'repair_order_id' => $repairOrder->id,
            'vehicle_id' => $vehicle->id,
            'inspector_user_id' => $technician->id,
        ]);

        $inspectionItem = InspectionItem::query()->create([
            'inspection_id' => $inspection->id,
            'category' => InspectionItemCategory::Brakes,
            'label' => 'Brake Pads',
            'position' => 1,
        ]);

        InspectionItemPhoto::query()->create([
            'inspection_item_id' => $inspectionItem->id,
            'purpose' => 'customer',
            'storage_path' => "inspections/{$index}/photo.jpg",
            'content_type' => 'image/jpeg',
            'original_name' => 'photo.jpg',
            'byte_size' => 1024,
            'uploaded_by_user_id' => $technician->id,
            'position' => 1,
        ]);

        return CallSession::query()->create([
            'provider' => 'twilio',
            'direction' => CallSessionDirection::Inbound,
            'status' => CallSessionStatus::Ringing,
            'customer_id' => $customer->id,
            'from_number' => $customer->phone,
            'to_number' => '5550000000',
            'normalized_from' => $customer->phone,
            'started_at' => now()->subMinutes(10 - ($index % 5)),
        ]);
    };

    // Scenario A: 1 matched caller
    $createCallerWithOpenRo(1);
    $resolver = app(CallQueueResolver::class);

    $measured1 = measureQueries(fn () => $resolver->resolve($advisor));
    expect($measured1['result']['count'])->toBe(1);
    $call1 = $measured1['result']['calls'][0];
    expect($call1['matched'])->toBeTrue()
        ->and($call1['customer_name'])->toBe('Caller1 Customer')
        ->and($call1['orientation'])->not->toBeNull()
        ->and($call1['orientation']['situation'])->toBe('Waiting on Customer Approval');

    // Clean up calls to test 20 callers in isolation
    CallSession::query()->delete();

    for ($i = 1; $i <= 20; $i++) {
        $createCallerWithOpenRo(100 + $i);
    }

    $measured20 = measureQueries(fn () => $resolver->resolve($advisor));
    expect($measured20['result']['count'])->toBe(20);

    // Verify user-visible orientation payload across all 20 calls
    foreach ($measured20['result']['calls'] as $call) {
        expect($call['matched'])->toBeTrue()
            ->and($call['orientation'])->not->toBeNull()
            ->and($call['orientation']['situation'])->toBe('Waiting on Customer Approval');
    }

    // Query count should remain constant (no per-RO query growth)
    expect($measured20['count'])->toBeLessThanOrEqual($measured1['count'] + 1);
});

test('unknown and duplicate phone lookups: query count remains constant with batch misses', function () {
    $resolver = app(CustomerCallContextResolver::class);

    // 1 unknown phone
    $measured1 = measureQueries(fn () => $resolver->mapForAttentionList(['5550001111']));
    expect($measured1['count'])->toBe(1);
    $res1 = $measured1['result']['5550001111'] ?? null;
    expect($res1)->not->toBeNull()
        ->and($res1->hasMatch())->toBeFalse()
        ->and($res1->customer)->toBeNull()
        ->and($res1->openRepairOrders)->toBeEmpty();

    // 20 unknown and duplicate phones
    $twentyPhones = [];
    for ($i = 1; $i <= 20; $i++) {
        $twentyPhones[] = sprintf('555000%04d', $i);
    }
    // Add duplicates
    $twentyPhones[] = '5550000001';
    $twentyPhones[] = '5550000002';

    $measured20 = measureQueries(fn () => $resolver->mapForAttentionList($twentyPhones));

    // Must be exactly 1 query (Customer::whereIn), with 0 redundant single lookups
    expect($measured20['count'])->toBe(1)
        ->and(count($measured20['result']))->toBe(20);

    foreach ($measured20['result'] as $context) {
        expect($context->hasMatch())->toBeFalse()
            ->and($context->customer)->toBeNull()
            ->and($context->openRepairOrders)->toBeEmpty();
    }
});

test('dealer parts scaling: ro show query count remains constant with 1 vs 20 dealer parts inside and outside work groups', function () {
    $this->actingAs(actingAsLearnCurrentAdvisor());

    $createRepairOrderWithDealerParts = function (int $partCount, int $index = 1): RepairOrder {
        $customer = Customer::query()->create([
            'first_name' => "Dealer{$index}",
            'last_name' => 'Customer',
            'phone' => sprintf('719555%04d', 2000 + $index),
            'email' => "dealer{$index}.customer@example.test",
        ]);

        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'plate' => "DLR{$index}",
            'year' => 2021,
            'make' => 'Audi',
            'model' => 'Q5',
        ]);

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => "Dealer parts test {$partCount}",
            'opened_at' => Carbon::parse('2026-06-01 08:30:00'),
        ]);

        $concern = RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => 'Suspension and drivetrain overhaul',
            'disposition' => RepairOrderConcernDisposition::Approved,
            'position' => 1,
        ]);

        $workGroup = RepairOrderWorkGroup::query()->create([
            'repair_order_id' => $repairOrder->id,
            'repair_order_concern_id' => $concern->id,
            'title' => 'Subframe assembly',
            'position' => 1,
        ]);

        $quote1 = DealerQuote::query()->create([
            'repair_order_id' => $repairOrder->id,
            'supplier_name' => 'Audi Genuine Parts East',
            'quote_number' => 'AUD-8821',
            'captured_at' => now(),
        ]);

        $quote2 = DealerQuote::query()->create([
            'repair_order_id' => $repairOrder->id,
            'supplier_name' => 'Audi Wholesale North',
            'quote_number' => 'AUD-9932',
            'captured_at' => now(),
        ]);

        for ($i = 1; $i <= $partCount; $i++) {
            $quote = ($i % 2 === 0) ? $quote2 : $quote1;

            $quoteLine = DealerQuoteLine::query()->create([
                'dealer_quote_id' => $quote->id,
                'part_number' => "8K0-{$i}",
                'description' => "Control Arm Bushing {$i}",
                'quantity' => 1,
                'unit_cost_cents' => 4500 + ($i * 100),
                'unit_price_cents' => 7500 + ($i * 100),
            ]);

            $inWorkGroup = ($i % 2 === 0);

            $line = RepairOrderLine::query()->create([
                'repair_order_id' => $repairOrder->id,
                'repair_order_concern_id' => $concern->id,
                'repair_order_work_group_id' => $inWorkGroup ? $workGroup->id : null,
                'dealer_quote_line_id' => $quoteLine->id,
                'type' => RepairOrderLineType::Part,
                'part_line_classification' => PartLineClassification::Oem,
                'description' => "Control Arm Bushing {$i}",
                'part_number' => "8K0-{$i}",
                'vendor_name' => $quote->supplier_name,
                'quantity' => '1.00',
                'unit_cost_cents' => 4500 + ($i * 100),
                'unit_price_cents' => 7500 + ($i * 100),
                'procurement_state' => PartProcurementState::Ordered,
                'position' => $i,
            ]);

            $quoteLine->update(['repair_order_line_id' => $line->id]);
        }

        return $repairOrder;
    };

    // Measure 1 dealer part (1 quote, outside work group)
    $ro1 = $createRepairOrderWithDealerParts(1, 1);
    $this->get(route('operations.repair-orders.show', $ro1))->assertOk();

    $measured1 = measureQueries(function () use ($ro1) {
        return $this->get(route('operations.repair-orders.show', $ro1))
            ->assertOk()
            ->assertSee('Control Arm Bushing 1')
            ->assertSee('Source')
            ->assertSee('AUD-8821');
    });

    // Measure 2 dealer parts (1 inside work group, 1 outside, 2 different quotes)
    $ro2 = $createRepairOrderWithDealerParts(2, 2);
    $this->get(route('operations.repair-orders.show', $ro2))->assertOk();

    $measured2 = measureQueries(function () use ($ro2) {
        return $this->get(route('operations.repair-orders.show', $ro2))
            ->assertOk()
            ->assertSee('Control Arm Bushing 1')
            ->assertSee('Control Arm Bushing 2')
            ->assertSee('Source')
            ->assertSee('AUD-8821')
            ->assertSee('AUD-9932');
    });

    // Measure 20 dealer parts (inside & outside work groups, 2 different quotes)
    $ro20 = $createRepairOrderWithDealerParts(20, 3);
    $this->get(route('operations.repair-orders.show', $ro20))->assertOk();

    $measured20 = measureQueries(function () use ($ro20) {
        return $this->get(route('operations.repair-orders.show', $ro20))
            ->assertOk()
            ->assertSee('Control Arm Bushing 1')
            ->assertSee('Control Arm Bushing 20')
            ->assertSee('Source')
            ->assertSee('AUD-8821')
            ->assertSee('AUD-9932');
    });

    // 20 parts from multiple quotes must not grow queries beyond 2 parts, and at most 3 over 1 part
    expect($measured20['count'])->toBeLessThanOrEqual($measured2['count'])
        ->and($measured20['count'])->toBeLessThanOrEqual($measured1['count'] + 3);
});

test('scaling profile benchmark for report verification', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    // 1. Unknown calls: 1 vs 20
    $callContextResolver = app(CustomerCallContextResolver::class);
    $qUnknown1 = measureQueries(fn () => $callContextResolver->mapForAttentionList(['5550001111']))['count'];
    $twentyPhones = array_map(fn ($i) => sprintf('555000%04d', $i), range(1, 20));
    $qUnknown20 = measureQueries(fn () => $callContextResolver->mapForAttentionList($twentyPhones))['count'];

    // 2. Call queue with open ROs: 1 vs 20
    $queueResolver = app(CallQueueResolver::class);
    // Measure 1 matched call
    $customer1 = Customer::query()->create(['first_name' => 'C1', 'last_name' => 'Test', 'phone' => '5551000001']);
    $v1 = Vehicle::query()->create(['customer_id' => $customer1->id, 'plate' => 'P1', 'year' => 2020, 'make' => 'Toyota', 'model' => 'Tacoma']);
    $ro1 = RepairOrder::query()->create(['customer_id' => $customer1->id, 'vehicle_id' => $v1->id, 'status' => RepairOrderStatus::WaitingApproval, 'concern_summary' => 'C1', 'opened_at' => now()]);
    RepairOrderConcern::query()->create(['repair_order_id' => $ro1->id, 'summary' => 'Brakes', 'disposition' => RepairOrderConcernDisposition::Recommended, 'position' => 1]);
    CallSession::query()->create(['provider' => 'twilio', 'direction' => CallSessionDirection::Inbound, 'status' => CallSessionStatus::Ringing, 'customer_id' => $customer1->id, 'from_number' => '5551000001', 'to_number' => '5550000000', 'normalized_from' => '5551000001', 'started_at' => now()]);

    $qMatchedCall1 = measureQueries(fn () => $queueResolver->resolve($advisor))['count'];

    CallSession::query()->delete();
    for ($i = 1; $i <= 20; $i++) {
        $c = Customer::query()->create(['first_name' => "C{$i}", 'last_name' => 'Test', 'phone' => sprintf('555200%04d', $i)]);
        $v = Vehicle::query()->create(['customer_id' => $c->id, 'plate' => "P{$i}", 'year' => 2020, 'make' => 'Toyota', 'model' => 'Tacoma']);
        $r = RepairOrder::query()->create(['customer_id' => $c->id, 'vehicle_id' => $v->id, 'status' => RepairOrderStatus::WaitingApproval, 'concern_summary' => "C{$i}", 'opened_at' => now()]);
        RepairOrderConcern::query()->create(['repair_order_id' => $r->id, 'summary' => 'Brakes', 'disposition' => RepairOrderConcernDisposition::Recommended, 'position' => 1]);
        CallSession::query()->create(['provider' => 'twilio', 'direction' => CallSessionDirection::Inbound, 'status' => CallSessionStatus::Ringing, 'customer_id' => $c->id, 'from_number' => $c->phone, 'to_number' => '5550000000', 'normalized_from' => $c->phone, 'started_at' => now()]);
    }
    $qMatchedCall20 = measureQueries(fn () => $queueResolver->resolve($advisor))['count'];

    // 3. Shop-turn conversations: 1 vs 20
    $shopTurnQueue = app(ShopTurnAttentionQueue::class);
    $cSt1 = Customer::query()->create(['first_name' => 'ST1', 'last_name' => 'Test', 'phone' => '5553000001']);
    $conv1 = Conversation::query()->create(['customer_id' => $cSt1->id, 'contact_surface' => ConversationContactSurface::Phone, 'contact_address' => $cSt1->phone, 'waiting_on' => ConversationWaitingOn::Shop->value, 'posture_changed_at' => now()]);
    $part1 = ConversationParticipant::query()->create(['conversation_id' => $conv1->id, 'participant_type' => ConversationParticipantType::Customer, 'customer_id' => $cSt1->id]);
    ConversationMessage::query()->create(['conversation_id' => $conv1->id, 'conversation_participant_id' => $part1->id, 'direction' => OperationalCommunicationDirection::Inbound, 'channel' => OperationalCommunicationChannel::Sms, 'body' => 'Ready?', 'occurred_at' => now()]);
    $qShopTurn1 = measureQueries(fn () => $shopTurnQueue->rowsFor($advisor))['count'];

    ConversationMessage::query()->delete();
    ConversationParticipant::query()->delete();
    Conversation::query()->delete();

    for ($i = 1; $i <= 20; $i++) {
        $c = Customer::query()->create(['first_name' => "ST{$i}", 'last_name' => 'Test', 'phone' => sprintf('555400%04d', $i)]);
        $conv = Conversation::query()->create(['customer_id' => $c->id, 'contact_surface' => ConversationContactSurface::Phone, 'contact_address' => $c->phone, 'waiting_on' => ConversationWaitingOn::Shop->value, 'posture_changed_at' => now()]);
        $part = ConversationParticipant::query()->create(['conversation_id' => $conv->id, 'participant_type' => ConversationParticipantType::Customer, 'customer_id' => $c->id]);
        ConversationMessage::query()->create(['conversation_id' => $conv->id, 'conversation_participant_id' => $part->id, 'direction' => OperationalCommunicationDirection::Inbound, 'channel' => OperationalCommunicationChannel::Sms, 'body' => "Ready {$i}?", 'occurred_at' => now()]);
    }
    $qShopTurn20 = measureQueries(fn () => $shopTurnQueue->rowsFor($advisor))['count'];

    expect($qUnknown20)->toBe(1)
        ->and($qMatchedCall20)->toBeLessThanOrEqual($qMatchedCall1 + 1)
        ->and($qShopTurn20)->toBeLessThanOrEqual($qShopTurn1 + 1);
});

test('shop turn attention queue scaling: query count remains constant between 1 and 20 conversations', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $createShopTurnConversation = function (int $index): Conversation {
        $customer = Customer::query()->create([
            'first_name' => "ShopTurn{$index}",
            'last_name' => 'Customer',
            'phone' => sprintf('555111%04d', $index),
        ]);

        $vehicle = Vehicle::query()->create([
            'customer_id' => $customer->id,
            'plate' => "ST{$index}",
            'year' => 2019,
            'make' => 'Honda',
            'model' => 'CR-V',
        ]);

        $repairOrder = RepairOrder::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => "Shop turn concern {$index}",
            'opened_at' => Carbon::parse('2026-06-01 08:00:00')->addMinutes($index),
        ]);

        RepairOrderConcern::query()->create([
            'repair_order_id' => $repairOrder->id,
            'summary' => "Shop turn concern item {$index}",
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ]);

        $conversation = Conversation::query()->create([
            'customer_id' => $customer->id,
            'contact_surface' => ConversationContactSurface::Phone,
            'contact_address' => $customer->phone,
            'waiting_on' => ConversationWaitingOn::Shop->value,
            'posture_changed_at' => now()->subMinutes(20 - ($index % 10)),
        ]);

        $participant = ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => ConversationParticipantType::Customer,
            'customer_id' => $customer->id,
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'conversation_participant_id' => $participant->id,
            'direction' => OperationalCommunicationDirection::Inbound,
            'channel' => OperationalCommunicationChannel::Sms,
            'body' => "Is my car ready? {$index}",
            'occurred_at' => now()->subMinutes(20 - ($index % 10)),
        ]);

        return $conversation;
    };

    // 1 shop-turn conversation
    $createShopTurnConversation(1);
    $queue = app(ShopTurnAttentionQueue::class);

    $measured1 = measureQueries(fn () => $queue->rowsFor($advisor));
    expect(count($measured1['result']))->toBe(1);

    // Clean up and create 20 conversations
    ConversationMessage::query()->delete();
    Conversation::query()->delete();

    for ($i = 1; $i <= 20; $i++) {
        $createShopTurnConversation(100 + $i);
    }

    $measured20 = measureQueries(fn () => $queue->rowsFor($advisor));
    expect(count($measured20['result']))->toBe(20);

    // Verify payload
    foreach ($measured20['result'] as $row) {
        expect($row['matched'])->toBeTrue()
            ->and($row['orientation'])->not->toBeNull()
            ->and($row['orientation']['situation'])->toBe('Waiting on Customer Approval');
    }

    // Query count should remain constant
    expect($measured20['count'])->toBeLessThanOrEqual($measured1['count'] + 1);
});

test('multiple open repair orders for one customer: preserves primary order selection, sorting, and orientation', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'MultiRO',
        'last_name' => 'Owner',
        'phone' => '5557771234',
    ]);

    $vehicleA = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'RO111',
        'year' => 2018,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    $vehicleB = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'RO222',
        'year' => 2022,
        'make' => 'Subaru',
        'model' => 'WRX',
    ]);

    // Older open RO
    $olderRo = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicleA->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Older repair order concern',
        'opened_at' => Carbon::parse('2026-06-01 08:00:00'),
        'updated_at' => Carbon::parse('2026-06-01 08:00:00'),
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $olderRo->id,
        'summary' => 'Older oil change',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    // Newer / primary open RO (more recently updated)
    $newerRo = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicleB->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Newer primary repair order concern',
        'opened_at' => Carbon::parse('2026-06-01 09:00:00'),
        'updated_at' => Carbon::parse('2026-06-01 10:30:00'),
    ]);
    RepairOrderConcern::query()->create([
        'repair_order_id' => $newerRo->id,
        'summary' => 'Newer clutch replacement',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'position' => 1,
    ]);

    // 1. Resolve call context
    $resolver = app(CustomerCallContextResolver::class);
    $contexts = $resolver->mapForAttentionList([$customer->phone]);
    $context = $contexts[$customer->phone] ?? null;

    expect($context)->not->toBeNull()
        ->and($context->openRepairOrders)->toHaveCount(2);

    // Verify exact sorting: newest updated_at first
    $firstOpen = $context->openRepairOrders->first();
    $secondOpen = $context->openRepairOrders->last();
    expect($firstOpen->repairOrder->id)->toBe($newerRo->id)
        ->and($secondOpen->repairOrder->id)->toBe($olderRo->id);

    // 2. Preload orientation
    Orientation::preloadForContexts($contexts);

    // Verify preload loaded relations on primary RO without altering order or collection
    expect($firstOpen->repairOrder->relationLoaded('concerns'))->toBeTrue()
        ->and($context->openRepairOrders->first()->repairOrder->id)->toBe($newerRo->id);

    // 3. Call queue resolution
    CallSession::query()->create([
        'provider' => 'twilio',
        'direction' => CallSessionDirection::Inbound,
        'status' => CallSessionStatus::Ringing,
        'customer_id' => $customer->id,
        'from_number' => $customer->phone,
        'to_number' => '5550000000',
        'normalized_from' => $customer->phone,
        'started_at' => now(),
    ]);

    $queueResolver = app(CallQueueResolver::class);
    $queueResult = $queueResolver->resolve($advisor);

    expect($queueResult['count'])->toBe(1);
    $callRow = $queueResult['calls'][0];
    expect($callRow['matched'])->toBeTrue()
        ->and($callRow['open_repair_orders'])->toHaveCount(2)
        ->and($callRow['orientation'])->not->toBeNull()
        ->and($callRow['orientation']['situation'])->toBe('Repair In Progress');
});

test('shop turn attention queue: always presents latest message after relationship reuse and does not display older messages', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $customer = Customer::query()->create([
        'first_name' => 'Timeline',
        'last_name' => 'Tester',
        'phone' => '5558882222',
    ]);

    $conversation = Conversation::query()->create([
        'customer_id' => $customer->id,
        'contact_surface' => ConversationContactSurface::Phone,
        'contact_address' => $customer->phone,
        'waiting_on' => ConversationWaitingOn::Shop->value,
        'posture_changed_at' => Carbon::parse('2026-06-01 11:30:00'),
    ]);

    $participant = ConversationParticipant::query()->create([
        'conversation_id' => $conversation->id,
        'participant_type' => ConversationParticipantType::Customer,
        'customer_id' => $customer->id,
    ]);

    // Message 1 (older: 09:00)
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'direction' => OperationalCommunicationDirection::Inbound,
        'channel' => OperationalCommunicationChannel::Sms,
        'body' => 'Old message from early morning',
        'occurred_at' => Carbon::parse('2026-06-01 09:00:00'),
    ]);

    // Message 2 (middle: 10:15)
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'direction' => OperationalCommunicationDirection::Outbound,
        'channel' => OperationalCommunicationChannel::Sms,
        'body' => 'Middle advisor reply',
        'occurred_at' => Carbon::parse('2026-06-01 10:15:00'),
    ]);

    // Message 3 (latest: 11:30)
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'conversation_participant_id' => $participant->id,
        'direction' => OperationalCommunicationDirection::Inbound,
        'channel' => OperationalCommunicationChannel::Sms,
        'body' => 'Latest incoming message: need an update please!',
        'occurred_at' => Carbon::parse('2026-06-01 11:30:00'),
    ]);

    $queue = app(ShopTurnAttentionQueue::class);
    $rows = $queue->rowsFor($advisor);

    expect($rows)->toHaveCount(1);
    $row = $rows[0];

    // Assert latest message content is displayed, never older message
    expect($row['snippet'])->toBe('Latest incoming message: need an update please!')
        ->and($row['occurred_at'])->toBe(Carbon::parse('2026-06-01 11:30:00')->toIso8601String())
        ->and($row['snippet'])->not->toContain('Old message from early morning')
        ->and($row['snippet'])->not->toContain('Middle advisor reply');
});

test('financial regression: locks exact authoritative calculations for repair order totals, invoices, and default deposits', function () {
    $settings = ShopSettings::current();
    $settings->update([
        'tax_enabled' => true,
        'default_tax_rate' => '8.250',
        'taxable_labor' => false,
        'taxable_parts' => true,
        'shop_fee_enabled' => false,
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => true,
        'default_deposit_diagnostic_labor_category_keys' => ['diagnostic'],
    ]);

    $customer = Customer::query()->create([
        'first_name' => 'Finance',
        'last_name' => 'Tester',
        'phone' => '5559993333',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'FIN123',
        'year' => 2021,
        'make' => 'BMW',
        'model' => 'M3',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::ReadyPickup,
        'concern_summary' => 'Financial validation test',
        'opened_at' => Carbon::parse('2026-06-01 08:00:00'),
    ]);

    // Approved concern with labor, dealer part, and diagnostic labor
    $approvedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Approved brake and control arm repair',
        'disposition' => RepairOrderConcernDisposition::Approved,
        'recommendation_intent' => RecommendationIntent::Diagnostic->value,
        'position' => 1,
    ]);

    // Diagnostic Labor: 1.0 hr @ $150.00 = $150.00 (taxable: false)
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'labor_category_key' => 'diagnostic',
        'description' => 'Diagnostic inspection',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    // Dealer Part: 2 qty @ $125.00 cost, $250.00 each = $500.00 (taxable: true, 8.25% = $41.25)
    $quote = DealerQuote::query()->create([
        'repair_order_id' => $repairOrder->id,
        'supplier_name' => 'BMW OEM Parts',
        'quote_number' => 'QUOTE-FIN-100',
        'captured_at' => now(),
    ]);
    $quoteLine = DealerQuoteLine::query()->create([
        'dealer_quote_id' => $quote->id,
        'part_number' => '31126775971',
        'description' => 'Control Arm Bushing',
        'quantity' => 2,
        'unit_cost_cents' => 12500,
        'unit_price_cents' => 25000,
    ]);
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $approvedConcern->id,
        'type' => RepairOrderLineType::Part,
        'part_line_classification' => PartLineClassification::Oem,
        'part_procurement_state' => PartProcurementState::Sourcing,
        'dealer_quote_line_id' => $quoteLine->id,
        'description' => 'OEM Control Arm Bushing',
        'quantity' => '2.00',
        'unit_price_cents' => 25000,
        'subtotal_cents' => 50000,
        'total_cents' => 54125, // 50000 + 4125 tax
        'tax_cents' => 4125,
    ]);

    // Declined concern (should not be included in approved totals or invoice)
    $declinedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Declined cabin filter',
        'disposition' => RepairOrderConcernDisposition::Declined,
        'position' => 2,
    ]);
    $repairOrder->lines()->create([
        'repair_order_concern_id' => $declinedConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Cabin Air Filter',
        'quantity' => '1.00',
        'unit_price_cents' => 4500,
        'subtotal_cents' => 4500,
        'total_cents' => 4871,
        'tax_cents' => 371,
    ]);

    // 1. Estimate Totals Calculator verification
    $calculator = app(EstimateTotalsCalculator::class);
    $calculator->recalculateRepairOrder($repairOrder);
    $totals = $calculator->totalsForApprovedWork($repairOrder);

    expect($totals->laborCents())->toBe(15000)
        ->and($totals->partsCents())->toBe(50000)
        ->and($totals->taxCents())->toBe(4125)
        ->and($totals->totalCents())->toBe(69125); // 15000 + 50000 + 4125

    // 2. Default Deposit Calculator verification
    $depositCalculator = app(RepairOrderDefaultDepositCalculator::class);
    $depositResult = $depositCalculator->forRepairOrder($repairOrder, $settings);

    expect($depositResult->enabled)->toBeTrue()
        ->and($depositResult->partsCents)->toBe(54125)
        ->and($depositResult->diagnosticsCents)->toBe(15000)
        ->and($depositResult->totalCents)->toBe(69125);

    // 3. Invoice Generation and Snapshot verification
    $invoiceAction = app(GenerateInvoiceSnapshotAction::class);
    $invoiceDocument = $invoiceAction->execute($repairOrder);

    expect($invoiceDocument->status)->toBe(InvoiceStatus::Issued->value)
        ->and($invoiceDocument->snapshot_json['totals']['total_cents'])->toBe(69125)
        ->and($invoiceDocument->snapshot_json['totals']['tax_cents'])->toBe(4125)
        ->and($invoiceDocument->snapshot_json['totals']['parts_cents'])->toBe(50000)
        ->and($invoiceDocument->snapshot_json['totals']['labor_cents'])->toBe(15000)
        ->and(count($invoiceDocument->snapshot_json['concerns']))->toBe(1);
});

test('tenant isolation: batch loading paths do not cross database or tenant boundaries', function () {
    $originalDefault = config('database.default');

    config([
        'database.connections.tenant_a' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.tenant_b' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    try {
        // Run full migrations on two genuinely separate SQLite in-memory databases
        $this->artisan('migrate', ['--database' => 'tenant_a', '--force' => true]);
        $this->artisan('migrate', ['--database' => 'tenant_b', '--force' => true]);

        // --- TENANT A SETUP & VERIFICATION ---
        DB::setDefaultConnection('tenant_a');
        config(['database.default' => 'tenant_a']);
        ShopSettings::forgetCurrent();
        ShopSettingsSchema::flush();
        $this->seed(ArkAuthorizationSeeder::class);
        $this->seed(RepairOrderStatusCatalogSeeder::class);

        $advisorA = actingAsLearnCurrentAdvisor();
        $techA = User::factory()->create(['name' => 'Tech TenantA'])->assignRole(ArkRole::Technician->value);

        $customerA = Customer::query()->create([
            'first_name' => 'Alice',
            'last_name' => 'TenantA',
            'phone' => '5551110001',
        ]);

        $vehicleA = Vehicle::query()->create([
            'customer_id' => $customerA->id,
            'plate' => 'TENA-RO1',
            'year' => 2020,
            'make' => 'Honda',
            'model' => 'Civic',
        ]);

        $roA = RepairOrder::query()->create([
            'customer_id' => $customerA->id,
            'vehicle_id' => $vehicleA->id,
            'status' => RepairOrderStatus::InProgress,
            'concern_summary' => 'Tenant A brake squeal',
            'assigned_technician_id' => $techA->id,
            'opened_at' => Carbon::parse('2026-06-01 08:00:00'),
            'updated_at' => Carbon::parse('2026-06-01 08:00:00'),
        ]);

        RepairOrderConcern::query()->create([
            'repair_order_id' => $roA->id,
            'summary' => 'Tenant A front brakes squeaking',
            'disposition' => RepairOrderConcernDisposition::Approved,
            'position' => 1,
        ]);

        app(CommunicationEventRecorder::class)->record(
            $roA,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            'Tenant A estimate sent to Alice',
            occurredAt: Carbon::parse('2026-06-01 08:30:00'),
        );

        $inspectionA = Inspection::query()->create([
            'repair_order_id' => $roA->id,
            'vehicle_id' => $vehicleA->id,
            'inspector_user_id' => $techA->id,
        ]);
        $itemA = InspectionItem::query()->create([
            'inspection_id' => $inspectionA->id,
            'category' => InspectionItemCategory::Brakes,
            'label' => 'Brake Pad Thickness',
            'position' => 1,
        ]);
        InspectionItemPhoto::query()->create([
            'inspection_item_id' => $itemA->id,
            'purpose' => 'customer',
            'storage_path' => 'inspections/tenant_a_pad.jpg',
            'content_type' => 'image/jpeg',
            'original_name' => 'pad.jpg',
            'byte_size' => 1024,
            'uploaded_by_user_id' => $techA->id,
            'position' => 1,
        ]);

        CallSession::query()->create([
            'provider' => 'twilio',
            'direction' => CallSessionDirection::Inbound,
            'status' => CallSessionStatus::Ringing,
            'customer_id' => $customerA->id,
            'from_number' => $customerA->phone,
            'to_number' => '5550000000',
            'normalized_from' => $customerA->phone,
            'started_at' => now(),
        ]);

        // Exercise caller resolution and orientation batch path on Tenant A
        $resolver = app(CustomerCallContextResolver::class);
        $contextsA = $resolver->mapForAttentionList([$customerA->phone, '5552220002']);

        expect($contextsA[$customerA->phone]->hasMatch())->toBeTrue()
            ->and($contextsA[$customerA->phone]->customer->first_name)->toBe('Alice')
            ->and($contextsA[$customerA->phone]->openRepairOrders)->toHaveCount(1)
            ->and($contextsA[$customerA->phone]->openRepairOrders->first()->repairOrder->id)->toBe($roA->id)
            ->and($contextsA['5552220002']->hasMatch())->toBeFalse();

        Orientation::preloadForContexts($contextsA);
        $primaryRoA = $contextsA[$customerA->phone]->openRepairOrders->first()->repairOrder;
        expect($primaryRoA->relationLoaded('concerns'))->toBeTrue()
            ->and($primaryRoA->relationLoaded('communicationEvents'))->toBeTrue()
            ->and($primaryRoA->relationLoaded('inspection'))->toBeTrue();

        $orientationA = Orientation::forRepairOrder($primaryRoA);
        expect($orientationA['situation'])->toBe('Repair In Progress')
            ->and($primaryRoA->concerns->first()->summary)->toBe('Tenant A front brakes squeaking')
            ->and($primaryRoA->inspection->items->first()->photos->first()->storage_path)->toBe('inspections/tenant_a_pad.jpg');

        $queueResolver = app(CallQueueResolver::class);
        $queueResultA = $queueResolver->resolve($advisorA);
        expect($queueResultA['count'])->toBe(1)
            ->and($queueResultA['calls'][0]['customer_name'])->toBe('Alice TenantA')
            ->and($queueResultA['calls'][0]['orientation']['situation'])->toBe('Repair In Progress');

        // Populate Read Cache on Tenant A
        $readCache = app(RepairOrderReadCache::class);
        $readCache->remember("phone-lookup:{$customerA->phone}", fn () => 'alice-cached-tenant-a');
        $readCache->remember('shared-cache-key', fn () => 'tenant-a-value');

        // --- TENANT B SETUP & VERIFICATION ---
        // Switch connection to Tenant B with genuine separate schema and records
        DB::setDefaultConnection('tenant_b');
        config(['database.default' => 'tenant_b']);
        ShopSettings::forgetCurrent();
        ShopSettingsSchema::flush();

        // Verify database-switch cache isolation: Tenant B scope evaluates its own closure
        $cachedSharedOnB = $readCache->remember('shared-cache-key', fn () => 'tenant-b-value');
        expect($cachedSharedOnB)->toBe('tenant-b-value');

        $this->seed(ArkAuthorizationSeeder::class);
        $this->seed(RepairOrderStatusCatalogSeeder::class);

        $advisorB = actingAsLearnCurrentAdvisor();
        $techB = User::factory()->create(['name' => 'Tech TenantB'])->assignRole(ArkRole::Technician->value);

        // Tenant B has records with overlapping autoincrement ID (ID 1)
        $customerB = Customer::query()->create([
            'first_name' => 'Bob',
            'last_name' => 'TenantB',
            'phone' => '5552220002',
        ]);

        $vehicleB = Vehicle::query()->create([
            'customer_id' => $customerB->id,
            'plate' => 'TENB-RO1',
            'year' => 2022,
            'make' => 'Ford',
            'model' => 'F-150',
        ]);

        $roB = RepairOrder::query()->create([
            'customer_id' => $customerB->id,
            'vehicle_id' => $vehicleB->id,
            'status' => RepairOrderStatus::WaitingApproval,
            'concern_summary' => 'Tenant B transmission slip',
            'assigned_technician_id' => $techB->id,
            'opened_at' => Carbon::parse('2026-06-01 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-01 09:00:00'),
        ]);

        RepairOrderConcern::query()->create([
            'repair_order_id' => $roB->id,
            'summary' => 'Tenant B transmission slipping in 2nd gear',
            'disposition' => RepairOrderConcernDisposition::Recommended,
            'position' => 1,
        ]);

        app(CommunicationEventRecorder::class)->record(
            $roB,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Sms,
            OperationalCommunicationDirection::Outbound,
            'Tenant B SMS sent to Bob',
            occurredAt: Carbon::parse('2026-06-01 09:15:00'),
        );

        $inspectionB = Inspection::query()->create([
            'repair_order_id' => $roB->id,
            'vehicle_id' => $vehicleB->id,
            'inspector_user_id' => $techB->id,
        ]);
        $itemB = InspectionItem::query()->create([
            'inspection_id' => $inspectionB->id,
            'category' => InspectionItemCategory::Fluids,
            'label' => 'Transmission Fluid Condition',
            'position' => 1,
        ]);
        InspectionItemPhoto::query()->create([
            'inspection_item_id' => $itemB->id,
            'purpose' => 'customer',
            'storage_path' => 'inspections/tenant_b_trans.jpg',
            'content_type' => 'image/jpeg',
            'original_name' => 'trans.jpg',
            'byte_size' => 1024,
            'uploaded_by_user_id' => $techB->id,
            'position' => 1,
        ]);

        CallSession::query()->create([
            'provider' => 'twilio',
            'direction' => CallSessionDirection::Inbound,
            'status' => CallSessionStatus::Ringing,
            'customer_id' => $customerB->id,
            'from_number' => $customerB->phone,
            'to_number' => '5550000000',
            'normalized_from' => $customerB->phone,
            'started_at' => now(),
        ]);

        // Verify overlapping IDs in DB
        expect($customerA->id)->toBe($customerB->id)
            ->and($roA->id)->toBe($roB->id);

        // Exercise caller resolution and orientation batch path on Tenant B
        $contextsB = $resolver->mapForAttentionList([$customerA->phone, $customerB->phone]);

        // Tenant B cannot see Tenant A customer or data
        expect($contextsB[$customerA->phone]->hasMatch())->toBeFalse()
            ->and($contextsB[$customerA->phone]->openRepairOrders)->toBeEmpty()
            ->and($contextsB[$customerB->phone]->hasMatch())->toBeTrue()
            ->and($contextsB[$customerB->phone]->customer->first_name)->toBe('Bob')
            ->and($contextsB[$customerB->phone]->openRepairOrders)->toHaveCount(1)
            ->and($contextsB[$customerB->phone]->openRepairOrders->first()->repairOrder->id)->toBe($roB->id);

        Orientation::preloadForContexts($contextsB);
        $primaryRoB = $contextsB[$customerB->phone]->openRepairOrders->first()->repairOrder;
        expect($primaryRoB->relationLoaded('concerns'))->toBeTrue()
            ->and($primaryRoB->relationLoaded('communicationEvents'))->toBeTrue()
            ->and($primaryRoB->relationLoaded('inspection'))->toBeTrue();

        $orientationB = Orientation::forRepairOrder($primaryRoB);
        expect($orientationB['situation'])->toBe('Waiting on Customer Approval')
            ->and($primaryRoB->concerns->first()->summary)->toBe('Tenant B transmission slipping in 2nd gear')
            ->and($primaryRoB->inspection->items->first()->photos->first()->storage_path)->toBe('inspections/tenant_b_trans.jpg');

        $queueResultB = $queueResolver->resolve($advisorB);
        expect($queueResultB['count'])->toBe(1)
            ->and($queueResultB['calls'][0]['customer_name'])->toBe('Bob TenantB')
            ->and($queueResultB['calls'][0]['orientation']['situation'])->toBe('Waiting on Customer Approval');

        // --- SWITCH BACK TO TENANT A & VERIFY FIDELITY ---
        DB::setDefaultConnection('tenant_a');
        config(['database.default' => 'tenant_a']);
        ShopSettings::forgetCurrent();
        ShopSettingsSchema::flush();

        // Exercise unit of work / request boundary cache drop
        RepairOrderReadCache::beginUnitOfWork();
        $freshCacheValue = $readCache->remember('unit-of-work-key', fn () => 'unit-of-work-fresh');
        expect($freshCacheValue)->toBe('unit-of-work-fresh');

        $contextsAAfter = $resolver->mapForAttentionList([$customerA->phone, $customerB->phone]);
        expect($contextsAAfter[$customerA->phone]->hasMatch())->toBeTrue()
            ->and($contextsAAfter[$customerA->phone]->customer->first_name)->toBe('Alice')
            ->and($contextsAAfter[$customerB->phone]->hasMatch())->toBeFalse();

        Orientation::preloadForContexts($contextsAAfter);
        $primaryRoAAfter = $contextsAAfter[$customerA->phone]->openRepairOrders->first()->repairOrder;
        expect($primaryRoAAfter->concerns->first()->summary)->toBe('Tenant A front brakes squeaking');

        $queueResultAAfter = $queueResolver->resolve($advisorA);
        expect($queueResultAAfter['count'])->toBe(1)
            ->and($queueResultAAfter['calls'][0]['customer_name'])->toBe('Alice TenantA')
            ->and($queueResultAAfter['calls'][0]['orientation']['situation'])->toBe('Repair In Progress');

    } finally {
        DB::setDefaultConnection($originalDefault);
        config(['database.default' => $originalDefault]);
        ShopSettings::forgetCurrent();
        ShopSettingsSchema::flush();
    }
});
