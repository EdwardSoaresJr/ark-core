<?php

use App\Ark\Platform\PlatformConnection;
use App\Ark\Platform\StarterClient;
use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    InstallationIdentity::write((string) Str::uuid());
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Casey Auto Repair',
        'cloud_status' => 'connected',
        'cloud_base_url' => 'https://cloud.example.test',
        'cloud_shop_public_id' => (string) Str::uuid(),
        'cloud_credential' => 'test-credential-32-characters-min!!',
        'ark_mail_status' => 'connected',
    ]);
});

test('repair orders receive an immutable public_id', function () {
    $ro = repairOrderForCommunication(RepairOrderStatus::Estimate);

    expect($ro->fresh()->public_id)->not->toBeEmpty()
        ->and(Str::isUuid((string) $ro->fresh()->public_id))->toBeTrue();
});

test('starter estimate ready posts facts only to cloud', function () {
    Http::fake([
        'https://cloud.example.test/api/v1/services/starter/repair-orders/estimate-ready' => Http::response([
            'ok' => true,
            'status' => 'sent',
            'correlation_id' => (string) Str::uuid(),
            'grant_public_id' => (string) Str::uuid(),
            'usage' => ['used' => 1, 'limit' => 20, 'period_key' => '2026-09'],
        ], 200),
    ]);

    $ro = repairOrderForCommunication(RepairOrderStatus::Estimate);

    $result = app(StarterClient::class)->sendEstimateReady(
        $ro,
        'owner@example.test',
        'https://demo.example.test/portal/estimates/token',
        'idem-1',
    );

    expect($result->ok())->toBeTrue();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_ends_with($request->url(), '/api/v1/services/starter/repair-orders/estimate-ready')
            && ! array_key_exists('html_body', $data)
            && ! array_key_exists('subject', $data)
            && ($data['customer_email'] ?? null) === 'owner@example.test'
            && filled($data['repair_order_public_id'] ?? null);
    });
});

test('starter allowance exhausted does not pretend core is blocked', function () {
    Http::fake([
        'https://cloud.example.test/api/v1/services/starter/repair-orders/estimate-ready' => Http::response([
            'ok' => false,
            'reason_code' => 'allowance_exhausted',
            'message' => 'Monthly Cloud Starter repair order allowance is exhausted.',
        ], 429),
    ]);

    $ro = repairOrderForCommunication(RepairOrderStatus::Estimate);

    $result = app(StarterClient::class)->sendEstimateReady(
        $ro,
        'owner@example.test',
        'https://demo.example.test/portal/estimates/token',
        'idem-2',
    );

    expect($result->ok())->toBeFalse()
        ->and($result->reasonCode)->toBe('allowance_exhausted')
        ->and($result->operatorMessage())->toContain('Core still works');
});

test('disconnected core does not report starter available', function () {
    PlatformConnection::current()->clear();

    expect(app(StarterClient::class)->isAvailable())->toBeFalse();
});
