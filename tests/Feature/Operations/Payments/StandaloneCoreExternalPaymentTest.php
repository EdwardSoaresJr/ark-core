<?php

uses(TestCase::class, RefreshDatabase::class);

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Payments\Capture\InitiatePaymentCaptureAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureContextKind;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Platform\PlatformConnection;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    PlatformConnection::current()->clear();
    config()->set('services.ark_platform.payments_capture', false);
    config()->set('services.square.application_id', null);
    config()->set('services.square.access_token', null);
    config()->set('services.square.location_id', null);
    config()->set('services.square.webhook_signature_key', null);
});

test('standalone Core opens an RO and records an external payment without contacting a provider', function () {
    Http::fake(function (Request $request) {
        expect($request->url())
            ->not->toContain('squareup')
            ->not->toContain('squareupsandbox')
            ->not->toContain('twilio.com')
            ->not->toContain('postmarkapp.com')
            ->not->toContain('partstech.com')
            ->not->toContain('cloud.test')
            ->not->toContain('cloud.arksms.com');

        return Http::response(['ok' => false, 'unexpected' => true], 500);
    });

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk();

    expect(fn () => app(InitiatePaymentCaptureAction::class)->execute($repairOrder->fresh(), $advisor, [
        'amount_cents' => 15000,
        'context_kind' => PaymentCaptureContextKind::Payment,
        'capture_method' => PaymentCaptureMethod::Keyed,
        'source_token' => 'cnon:should-not-contact-square',
    ]))->toThrow(ValidationException::class);

    $this->patch(route('operations.repair-orders.payment.update', $repairOrder), [
        'amount' => '150.00',
        'payment_method' => PaymentMethod::Card->value,
        'reference' => 'Counter card outside ARK',
    ])->assertRedirect();

    $entry = RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->sole();

    expect($entry->amount_cents)->toBe(15000)
        ->and($entry->payment_method)->toBe(PaymentMethod::Card);

    Http::assertNothingSent();
});
