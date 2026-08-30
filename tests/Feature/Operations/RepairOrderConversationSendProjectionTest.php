<?php

use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    // Local/CI mailer — official production path is ARK Mail only.
    config()->set('mail.default', 'array');
});

test('payment send projection explains missing issued invoice', function () {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);

    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Invoiced);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['payment'];

    expect($projection['can_sms'])->toBeFalse()
        ->and($projection['can_email'])->toBeFalse()
        ->and($projection['send_block_reason'])->toBe('Generate the final invoice before sending a payment link.');
});

test('estimate send projection allows sms when customer and twilio are ready', function () {
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['estimate'];

    expect($projection['can_sms'])->toBeTrue()
        ->and($projection['can_email'])->toBeTrue()
        ->and($projection['send_block_reason'])->toBeNull();
});

test('estimate send projection blocks closed repair orders', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Closed);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['estimate'];

    expect($projection['send_block_reason'])->toBe('Closed repair orders cannot send estimate links.');
});
