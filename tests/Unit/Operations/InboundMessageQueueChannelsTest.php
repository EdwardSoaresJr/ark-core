<?php

use App\Ark\Operations\Communications\InboundMessageQueueChannels;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Messaging\Messenger\MessengerShopConnection;
use App\Ark\Operations\Settings\ShopSettings;


test('inbound queue channels always include sms and exclude messenger by default', function () {
    $channels = app(InboundMessageQueueChannels::class)->enabled();

    expect($channels)->toBe([OperationalCommunicationChannel::Sms]);
});

test('inbound queue channels include messenger when enabled in shop settings', function () {
    ShopSettings::current()->persistTrusted([
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
            ],
        ],
    ]);

    $channels = app(InboundMessageQueueChannels::class)->enabled();

    expect($channels)->toBe([
        OperationalCommunicationChannel::Sms,
        OperationalCommunicationChannel::Messenger,
    ]);
});

test('messenger shop connection is never configured in core', function () {
    ShopSettings::current()->persistTrusted([
        'communications_channels' => [
            'messenger' => [
                'enabled' => true,
                'page_id' => 'page-99',
            ],
        ],
        'messenger_page_id' => 'page-99',
    ]);

    $connection = MessengerShopConnection::forShop(ShopSettings::current()->fresh());

    expect($connection->isConfigured())->toBeFalse()
        ->and($connection->pageId())->toBe('page-99');
});
