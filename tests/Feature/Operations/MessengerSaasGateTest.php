<?php

/**
 * Permanent Messenger SaaS gate — every future Messenger PR must keep these green.
 *
 * Page 111111 → Shop A only
 * Page 222222 → Shop B only
 * Unknown Page → acknowledged, not ingested
 * Mixed payload → each entry.id routed independently
 */

use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\Messenger\MessengerChannelConnection;
use App\Ark\Operations\Messaging\Messenger\MessengerHealth;
use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;
use App\Ark\Operations\Messaging\Messenger\MessengerShopPageResolver;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    config()->set('services.meta_messenger.app_secret', 'platform-app-secret');
    config()->set('services.meta_messenger.verify_token', 'platform-verify-token');

    [$this->shopA, $this->shopB] = seedMessengerSaasShops();
});

test('saas gate: page 111111 routes to shop A only', function () {
    $customerA = seedMessengerSaasCustomer('psid-shop-a-111');

    postMessengerSaasPage('111111', 'psid-shop-a-111', 'mid-shop-a-111', 'Hello Shop A')
        ->assertOk()
        ->assertSee('EVENT_RECEIVED', false);

    expect(ConversationMessage::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->sole()->body)->toBe('Hello Shop A')
        ->and(ConversationMessage::query()->sole()->metadata['page_id'] ?? null)->toBe('111111');

    expect(cache()->has(MessengerHealth::webhookCacheKey('111111')))->toBeTrue()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('222222')))->toBeFalse();

    expect(messengerSaasHealth($this->shopA)->lastWebhookAt())->not->toBeNull()
        ->and(messengerSaasHealth($this->shopB)->lastWebhookAt())->toBeNull();

    expect(ConversationLink::query()
        ->where('linkable_type', Customer::class)
        ->where('linkable_id', $customerA->id)
        ->exists())->toBeTrue();
});

test('saas gate: page 222222 routes to shop B only', function () {
    $customerB = seedMessengerSaasCustomer('psid-shop-b-222');

    postMessengerSaasPage('222222', 'psid-shop-b-222', 'mid-shop-b-222', 'Hello Shop B')
        ->assertOk()
        ->assertSee('EVENT_RECEIVED', false);

    expect(ConversationMessage::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->sole()->body)->toBe('Hello Shop B')
        ->and(ConversationMessage::query()->sole()->metadata['page_id'] ?? null)->toBe('222222');

    expect(cache()->has(MessengerHealth::webhookCacheKey('222222')))->toBeTrue()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('111111')))->toBeFalse();

    expect(messengerSaasHealth($this->shopB)->lastWebhookAt())->not->toBeNull()
        ->and(messengerSaasHealth($this->shopA)->lastWebhookAt())->toBeNull();

    expect(ConversationLink::query()
        ->where('linkable_type', Customer::class)
        ->where('linkable_id', $customerB->id)
        ->exists())->toBeTrue();
});

test('saas gate: unknown page is acknowledged and not ingested', function () {
    seedMessengerSaasCustomer('psid-unknown-page');

    postMessengerSaasPage('999999', 'psid-unknown-page', 'mid-unknown-page', 'Should not land')
        ->assertOk()
        ->assertSee('EVENT_RECEIVED', false);

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and(cache()->has(MessengerHealth::webhookCacheKey('999999')))->toBeFalse()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('111111')))->toBeFalse()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('222222')))->toBeFalse()
        ->and(messengerSaasHealth($this->shopA)->lastWebhookAt())->toBeNull()
        ->and(messengerSaasHealth($this->shopB)->lastWebhookAt())->toBeNull();
});

test('saas gate: mixed payload routes each entry.id independently', function () {
    seedMessengerSaasCustomer('psid-mixed-a');
    seedMessengerSaasCustomer('psid-mixed-b');

    $this->postJson(route('webhooks.communications.meta.messenger'), [
        'object' => 'page',
        'entry' => [
            messengerSaasEntry('999999', 'psid-mixed-unknown', 'mid-mixed-unknown', 'Unknown page'),
            messengerSaasEntry('111111', 'psid-mixed-a', 'mid-mixed-a', 'Mixed → A'),
            messengerSaasEntry('222222', 'psid-mixed-b', 'mid-mixed-b', 'Mixed → B'),
        ],
    ])->assertOk()->assertSee('EVENT_RECEIVED', false);

    $messages = ConversationMessage::query()->orderBy('id')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages->pluck('body')->all())->toBe(['Mixed → A', 'Mixed → B'])
        ->and($messages->pluck('metadata.page_id')->all())->toBe(['111111', '222222']);

    expect(cache()->has(MessengerHealth::webhookCacheKey('111111')))->toBeTrue()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('222222')))->toBeTrue()
        ->and(cache()->has(MessengerHealth::webhookCacheKey('999999')))->toBeFalse();

    expect(messengerSaasHealth($this->shopA)->lastWebhookAt())->not->toBeNull()
        ->and(messengerSaasHealth($this->shopB)->lastWebhookAt())->not->toBeNull();

    $resolver = app(MessengerShopPageResolver::class);

    expect($resolver->resolveByPageId('111111')?->id)->toBe($this->shopA->id)
        ->and($resolver->resolveByPageId('222222')?->id)->toBe($this->shopB->id)
        ->and($resolver->resolveByPageId('999999'))->toBeNull();
});

/**
 * @return array{0: ShopSettings, 1: ShopSettings}
 */
function seedMessengerSaasShops(): array
{
    $shopA = ShopSettings::current();
    $shopA->persistTrusted([
        'shop_name' => 'Shop A',
        'messenger_page_id' => '111111',
        'messenger_page_access_token' => 'page-token-a',
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => '111111',
                'page_name' => 'Shop A Automotive',
            ],
        ],
        'messenger_app_secret' => null,
    ]);

    $shopB = new ShopSettings;
    $shopB->forceFill([
        'shop_name' => 'Shop B',
        'shop_timezone' => 'America/Denver',
    ])->save();
    $shopB->persistTrusted([
        'messenger_page_id' => '222222',
        'messenger_page_access_token' => 'page-token-b',
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => '222222',
                'page_name' => 'Shop B Automotive',
            ],
        ],
        'messenger_app_secret' => null,
    ]);

    ShopSettings::reloadCurrent();

    return [$shopA->fresh(), $shopB->fresh()];
}

function seedMessengerSaasCustomer(string $psid): Customer
{
    return Customer::query()->create([
        'first_name' => 'Saas',
        'last_name' => 'Gate',
        'phone' => null,
        'messenger_psid' => $psid,
        'customer_type' => 'Retail',
    ]);
}

/**
 * @return array<string, mixed>
 */
function messengerSaasEntry(string $pageId, string $psid, string $messageId, string $body): array
{
    return [
        'id' => $pageId,
        'time' => 1710000000,
        'messaging' => [[
            'sender' => ['id' => $psid],
            'recipient' => ['id' => $pageId],
            'timestamp' => 1710000001,
            'message' => [
                'mid' => $messageId,
                'text' => $body,
            ],
        ]],
    ];
}

function postMessengerSaasPage(string $pageId, string $psid, string $messageId, string $body)
{
    return test()->postJson(route('webhooks.communications.meta.messenger'), [
        'object' => 'page',
        'entry' => [messengerSaasEntry($pageId, $psid, $messageId, $body)],
    ]);
}

function messengerSaasHealth(ShopSettings $shop): MessengerHealth
{
    return MessengerChannelConnection::forShopConnection(
        MessengerShopConnection::forShop($shop)
    )->health();
}
