<?php

use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('customer hub timeline includes platform sms when core conversation messages are empty', function () {
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Platform Sms Customer');
    $customer = $repairOrder->customer;
    $customer->forceFill(['phone' => '7195554400'])->save();

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) {
            if (str_contains($request->url(), '/conversations/pc_abc')) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => 'pc_abc',
                        'contact_address' => '+17195554400',
                        'core_customer_id' => null,
                    ],
                    'messages' => [[
                        'public_id' => 'pm_1',
                        'direction' => 'inbound',
                        'body' => 'Did anyone call me back?',
                        'occurred_at' => '2026-09-16T18:04:00Z',
                        'provider_message_id' => 'SMinbound01',
                    ]],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => 'pc_abc',
                    'contact_address' => '+17195554400',
                    'core_customer_id' => null,
                    'preview' => 'Did anyone call me back?',
                    'last_message_at' => '2026-09-16T18:04:00Z',
                ]],
            ], 200);
        },
    ]);

    $this->actingAs($advisor);

    $timeline = app(CustomerHubCommsTimeline::class)->buildForCustomer(
        $customer,
        PhoneNumber::normalize($customer->phone),
        50,
    );

    expect($timeline)->not->toBeEmpty()
        ->and($timeline->first()->source)->toBe(OperationalEventSource::PlatformMessage)
        ->and($timeline->first()->body)->toBe('Did anyone call me back?')
        ->and($timeline->first()->hubFilter())->toBe('text');

    $this->get(route('operations.customers.show', $customer).'?tab=comms')
        ->assertOk()
        ->assertSee('Did anyone call me back?');
});

test('customer hub comms updates return platform sms newer than the last stamp', function () {
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Platform Sms Customer');
    $customer = $repairOrder->customer;
    $customer->forceFill(['phone' => '7195554400'])->save();

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) {
            if (str_contains($request->url(), '/conversations/pc_abc')) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => 'pc_abc',
                        'contact_address' => '+17195554400',
                        'core_customer_id' => null,
                    ],
                    'messages' => [
                        [
                            'public_id' => 'pm_old',
                            'direction' => 'outbound',
                            'body' => 'Older text.',
                            'occurred_at' => '2026-09-16T17:00:00Z',
                        ],
                        [
                            'public_id' => 'pm_new',
                            'direction' => 'inbound',
                            'body' => 'Reply just came in.',
                            'occurred_at' => '2026-09-16T18:30:00Z',
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => 'pc_abc',
                    'contact_address' => '+17195554400',
                    'core_customer_id' => null,
                    'preview' => 'Reply just came in.',
                    'last_message_at' => '2026-09-16T18:30:00Z',
                ]],
            ], 200);
        },
    ]);

    $this->actingAs($advisor)
        ->getJson(route('operations.customers.hub-comms.updates', [
            'customer' => $customer,
            'since_message_id' => 0,
            'since_occurred_at' => '2026-09-16T18:00:00Z',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.platform_message_id', 'pm_new')
        ->assertSee('Reply just came in.', false)
        ->assertDontSee('Older text.', false);
});
