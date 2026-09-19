<?php

use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');
    config()->set('services.postmark.token', 'pm-token');
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

test('estimate send projection allows sms via platform without core twilio', function () {
    config()->set('services.twilio.auth_token', null);
    config()->set('services.twilio.account_sid', null);
    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_send', true);

    ShopSettings::current()->persistTrusted([
        'twilio_account_sid' => null,
        'twilio_auth_token' => null,
        'telephony_inbound_number' => null,
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['estimate'];

    expect($projection['can_sms'])->toBeTrue()
        ->and($projection['sms_block_reason'])->toBeNull();
});

test('estimate send projection allows email via platform without core postmark', function () {
    config()->set('services.twilio.auth_token', 'test-token');
    config()->set('services.twilio.account_sid', 'ACtestaccount');

    enableHostedPlatformMail();
    config()->set('services.ark_platform.payments_capture', false);
    config()->set('services.postmark.token', null);
    ShopSettings::current()->persistTrusted([
        'postmark_token' => null,
        'telephony_inbound_number' => '7195559999',
    ]);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['estimate'];

    expect($projection['can_email'])->toBeTrue()
        ->and($projection['email_block_reason'])->toBeNull()
        ->and($projection['send_block_reason'])->toBeNull();
});

test('payment send projection allows email via platform without core postmark', function () {
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    enableHostedPlatformMail();
    config()->set('services.ark_platform.payments_capture', false);
    config()->set('services.postmark.token', null);
    ShopSettings::current()->persistTrusted([
        'postmark_token' => null,
        'telephony_inbound_number' => '7195559999',
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['email' => 'customer@example.test'])->save();
    issueFinalInvoiceFor($repairOrder);

    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder->fresh(), actingAsLearnCurrentAdvisor())['payment'];

    expect($projection['can_email'])->toBeTrue()
        ->and($projection['email_block_reason'])->toBeNull();
});

test('deposit send projection allows email via platform without core postmark', function () {
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');

    enableHostedPlatformMail();
    config()->set('services.ark_platform.payments_capture', false);
    config()->set('services.postmark.token', null);
    ShopSettings::current()->persistTrusted([
        'postmark_token' => null,
        'telephony_inbound_number' => '7195559999',
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['email' => 'customer@example.test'])->save();

    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder->fresh(), actingAsLearnCurrentAdvisor())['deposit'];

    expect($projection['can_email'])->toBeTrue()
        ->and($projection['email_block_reason'])->toBeNull();
});

test('estimate send projection blocks closed repair orders', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Closed);
    $projection = app(RepairOrderConversationSendProjection::class)
        ->forRepairOrder($repairOrder, actingAsLearnCurrentAdvisor())['estimate'];

    expect($projection['send_block_reason'])->toBe('Closed repair orders cannot send estimate links.');
});
