<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    enableHostedPlatformPayments();
});

test('hosted repair order offers the reader in take payment without core square secrets', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    Http::fake([
        'https://cloud.test/api/v1/services/payments/readiness' => Http::response(platformPaymentReadinessPayload(), 200),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Take payment', false)
        ->assertSee('>Reader</button>', false)
        ->assertSee('Present to reader', false)
        ->assertDontSee('square_access_token', false)
        ->assertDontSee('squareup', false);
});
