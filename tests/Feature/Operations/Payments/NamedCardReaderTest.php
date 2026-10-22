<?php

use App\Ark\Install\InstallationIdentity;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    enableHostedPlatformPayments();
    PaymentCaptureReadinessProjection::resetMemo();
    $this->captures = [];
    $this->devices = [
        ['device_ref' => '549CS149C4000527', 'label' => '549CS149C4000527', 'ready' => true],
        ['device_ref' => '549CS149C4000999', 'label' => '549CS149C4000999', 'ready' => true],
        ['device_ref' => 'OFFLINE-READER', 'label' => 'OFFLINE-READER', 'ready' => false],
    ];
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/api/v1/services/payments/readiness')) {
            return Http::response(platformPaymentReadinessPayload(['available_devices' => $this->devices]), 200);
        }
        if (str_ends_with($request->url(), '/api/v1/services/payments/captures')) {
            $this->captures[] = $request->data();

            return Http::response([
                'ok' => true,
                'status' => 'pending',
                'capture_id' => 'capture-1',
                'provider' => 'processor',
                'provider_refs' => ['terminal_checkout_id' => 'checkout-1'],
            ], 200);
        }

        return Http::response([], 404);
    });

    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($this->repairOrder);
});

function nameReaders(array $names): void
{
    ShopSettings::current()->persistTrusted(['payment_reader_names' => $names]);
    ShopSettings::forgetCurrent();
    PaymentCaptureReadinessProjection::resetMemo();
}

function startReaderPayment($test, string $deviceRef)
{
    $amount = $test->repairOrder->fresh()->balanceDue()->balanceDueCents;

    return $test->actingAs($test->advisor)->postJson(route('operations.repair-orders.payment-capture.store', $test->repairOrder), [
        'amount' => number_format($amount / 100, 2, '.', ''),
        'context_kind' => 'payment',
        'capture_method' => 'terminal',
        'device_ref' => $deviceRef,
    ]);
}

test('take payment lists readers by their shop names and falls back to the device id', function () {
    nameReaders(['549CS149C4000527' => 'Front Counter']);

    $this->actingAs($this->advisor)
        ->get(route('operations.repair-orders.show', $this->repairOrder->fresh()))
        ->assertOk()
        ->assertSee('<option value="549CS149C4000527" data-payment-reader-label="Front Counter">Front Counter</option>', false)
        ->assertSee('<option value="549CS149C4000999" data-payment-reader-label="549CS149C4000999">549CS149C4000999</option>', false)
        ->assertSee('<option value="OFFLINE-READER" data-payment-reader-label="OFFLINE-READER" disabled>OFFLINE-READER (offline)</option>', false)
        ->assertSee('Choose a reader', false);
});

test('the reader name and device id share one control with no separate device line', function () {
    nameReaders(['549CS149C4000527' => 'Front Counter']);

    $page = $this->actingAs($this->advisor)->get(route('operations.repair-orders.show', $this->repairOrder->fresh()))->assertOk();

    expect(substr_count($page->getContent(), 'data-payment-reader-control'))->toBe(1)
        ->and($page->getContent())->toContain('x-text="readerDisplay().primary"')
        ->and($page->getContent())->toContain('x-text="readerDisplay().secondary"')
        ->and($page->getContent())->not->toContain('selectedReader()?.device_ref');
});

test('a reader that goes offline after the modal opened is refused at submission', function () {
    nameReaders(['549CS149C4000527' => 'Front Counter']);
    $this->actingAs($this->advisor)
        ->get(route('operations.repair-orders.show', $this->repairOrder->fresh()))
        ->assertOk()
        ->assertSee('data-payment-reader-label="Front Counter">Front Counter</option>', false);

    $this->devices[0]['ready'] = false;

    startReaderPayment($this, '549CS149C4000527')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_ref');

    expect($this->captures)->toBe([])
        ->and(PaymentCaptureAttempt::query()->count())->toBe(0);

    $this->devices[0]['ready'] = true;

    startReaderPayment($this, '549CS149C4000527')->assertOk();
    expect($this->captures[0]['device_ref'])->toBe('549CS149C4000527');
});

test('the browser remembers readers under a key scoped to this shop', function () {
    $page = $this->actingAs($this->advisor)->get(route('operations.repair-orders.show', $this->repairOrder->fresh()))->assertOk();

    expect($page->getContent())->toContain('ark:payment-reader:'.InstallationIdentity::read());
});

test('a single available reader is offered on its own so the browser can preselect it', function () {
    $this->devices = [['device_ref' => '549CS149C4000527', 'label' => '549CS149C4000527', 'ready' => true]];
    nameReaders(['549CS149C4000527' => 'Front Counter']);

    $readers = collect(app(PaymentCaptureReadinessProjection::class)->current()['devices'])->where('ready', true)->values();

    expect($readers->all())->toBe([['device_ref' => '549CS149C4000527', 'label' => 'Front Counter', 'ready' => true]]);
});

test('admins can name readers, rename them, and clear a name back to the device id', function () {
    $save = fn (array $names) => $this->actingAs($this->admin)->patch(route('operations.settings.shop.payments.update'), [
        'square_terminal_enabled' => '1',
        'payment_reader_names' => $names,
    ])->assertRedirect();

    $save(['549CS149C4000527' => '  Front Counter  ', '549CS149C4000999' => 'Bay 1']);
    expect(ShopSettings::current()->fresh()->paymentReaderNames())->toBe(['549CS149C4000527' => 'Front Counter', '549CS149C4000999' => 'Bay 1']);

    $save(['549CS149C4000527' => 'Reception', '549CS149C4000999' => '']);
    expect(ShopSettings::current()->fresh()->paymentReaderNames())->toBe(['549CS149C4000527' => 'Reception']);

    $this->actingAs($this->admin)->patch(route('operations.settings.shop.payments.update'), ['square_terminal_enabled' => '1'])->assertRedirect();
    expect(ShopSettings::current()->fresh()->paymentReaderNames())->toBe(['549CS149C4000527' => 'Reception']);
});

test('reader names are rejected when too long and cannot be saved by advisors', function () {
    $this->actingAs($this->admin)->patch(route('operations.settings.shop.payments.update'), [
        'payment_reader_names' => ['549CS149C4000527' => str_repeat('x', 61)],
    ])->assertSessionHasErrors('payment_reader_names.549CS149C4000527');

    $this->actingAs($this->advisor)->patch(route('operations.settings.shop.payments.update'), [
        'payment_reader_names' => ['549CS149C4000527' => 'Front Counter'],
    ])->assertRedirect()->assertSessionHasErrors();

    expect(ShopSettings::current()->fresh()->paymentReaderNames())->toBe([]);
});

test('the payments settings page shows each reader with its device id', function () {
    nameReaders(['549CS149C4000527' => 'Front Counter']);

    $this->actingAs($this->admin)->get(route('operations.settings.shop.edit', ['section' => 'payments']))
        ->assertOk()
        ->assertSee('name="payment_reader_names[549CS149C4000527]"', false)
        ->assertSee('value="Front Counter"', false)
        ->assertSee('name="payment_reader_names[549CS149C4000999]"', false);
});

test('a reader payment goes to exactly the chosen device', function () {
    nameReaders(['549CS149C4000527' => 'Front Counter', '549CS149C4000999' => 'Bay 1']);

    startReaderPayment($this, '549CS149C4000999')->assertOk();

    expect($this->captures)->toHaveCount(1)
        ->and($this->captures[0]['device_ref'])->toBe('549CS149C4000999')
        ->and(PaymentCaptureAttempt::query()->sole()->device_ref)->toBe('549CS149C4000999');
});

test('a missing, unknown, or unavailable reader is refused before any capture starts', function (string $deviceRef) {
    startReaderPayment($this, $deviceRef)->assertUnprocessable();

    expect($this->captures)->toBe([])
        ->and(PaymentCaptureAttempt::query()->count())->toBe(0);
})->with([
    'none chosen' => [''],
    'not this shop' => ['ANOTHER-SHOP-READER'],
    'not ready' => ['OFFLINE-READER'],
]);
