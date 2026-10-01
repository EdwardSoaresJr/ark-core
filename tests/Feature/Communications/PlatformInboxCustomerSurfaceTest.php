<?php

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) {
            if (str_contains($request->url(), '/read')) {
                return Http::response(['ok' => true], 200);
            }

            if (str_contains($request->url(), '/conversations/pc_floor')) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => 'pc_floor',
                        'contact_address' => '+17195557700',
                        'core_customer_id' => null,
                    ],
                    'messages' => [
                        [
                            'public_id' => 'pm_out_1',
                            'direction' => 'outbound',
                            'body' => 'Your estimate is ready.',
                            'occurred_at' => '2026-09-16T16:04:00Z',
                            'delivery_status' => 'delivered',
                        ],
                        [
                            'public_id' => 'pm_in_1',
                            'direction' => 'inbound',
                            'body' => 'Ok, please call me.',
                            'occurred_at' => '2026-09-16T16:12:00Z',
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => 'pc_floor',
                    'contact_address' => '+17195557700',
                    'core_customer_id' => null,
                    'preview' => 'Ok, please call me.',
                    'last_message_at' => '2026-09-16T16:12:00Z',
                ]],
            ], 200);
        },
    ]);
});

test('platform inbox names the thread from the customer phone when platform omits core_customer_id', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Maricruz Floor');
    $customer = $repairOrder->customer;
    $customer->forceFill(['phone' => '7195557700'])->save();

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox'))
        ->assertRedirect();

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', ['filter' => 'needs']))
        ->assertOk()
        ->assertSee('Maricruz Floor')
        ->assertSee('Your estimate is ready.')
        ->assertSee('Ok, please call me.')
        ->assertSee('Customer wrote last')
        ->assertSee('Needs attention')
        ->assertSee('Estimate received')
        ->assertSee('Scheduling')
        ->assertSee('Send Estimate')
        ->assertSee('Actions')
        ->assertSee(route('operations.communications.platform-conversations.work', ['platformConversation' => 'pc_floor']), false)
        ->assertDontSee('Mark handled', false)
        ->assertDontSee('No customer', false);
});

test('platform inbox fragment poll keeps platform messages instead of replacing with core', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = repairOrderForCommunication(RepairOrderStatus::InProgress, 'Maricruz Floor');
    $repairOrder->customer->forceFill(['phone' => '7195557700'])->save();

    $this->actingAs($advisor)
        ->getJson(route('operations.communications.workspace.fragment', [
            'section' => 'inbox',
            'platform_conversation' => 'pc_floor',
        ]))
        ->assertOk()
        ->assertJsonPath('unchanged', false)
        ->assertSee('Maricruz Floor', false)
        ->assertSee('Your estimate is ready.', false)
        ->assertSee('Ok, please call me.', false);
});
