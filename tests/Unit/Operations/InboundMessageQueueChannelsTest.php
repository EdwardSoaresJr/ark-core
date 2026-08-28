<?php

use App\Ark\Operations\Communications\InboundMessageQueueChannels;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('inbound queue channels always include sms and exclude messenger by default', function () {
    $channels = app(InboundMessageQueueChannels::class)->enabled();

    expect($channels)->toBe([OperationalCommunicationChannel::Sms]);
});

test('inbound queue channels include messenger when enabled in shop settings', function () {
    ShopSettings::current()->persistTrusted([
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => '1234567890',
            ],
        ],
        'messenger_page_id' => '1234567890',
        'messenger_page_access_token' => 'token-stub',
    ]);

    $channels = app(InboundMessageQueueChannels::class)->enabled();

    expect($channels)->toBe([
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationChannel::Messenger,
    ]);
});

test('messenger shop connection reads encrypted page token not json plaintext', function () {
    ShopSettings::current()->persistTrusted([
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => 'page-99',
                'page_access_token' => 'should-not-win',
            ],
        ],
        'messenger_page_id' => 'page-99',
        'messenger_page_access_token' => 'secret-token',
    ]);

    $connection = MessengerShopConnection::forShop(ShopSettings::current()->fresh());

    expect($connection->isConfigured())->toBeTrue()
        ->and($connection->pageId())->toBe('page-99')
        ->and($connection->pageAccessToken())->toBe('secret-token');
});
