<?php

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\Payments\FakeSquarePaymentsClient;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\InvoiceCustomerMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);

    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');
    config()->set('services.square.webhook_signature_key', 'test-signature-key');

    ShopSettings::current()->update([
        'square_enabled' => true,
        'square_terminal_device_id' => 'device-123',
        'square_terminal_enabled' => true,
        'square_keyed_enabled' => true,
        'square_portal_pay_enabled' => true,
        'square_email_pay_enabled' => true,
    ]);

    $this->fakeSquare = new FakeSquarePaymentsClient;
    $this->app->instance(FakeSquarePaymentsClient::class, $this->fakeSquare);
    $this->app->bind(\App\Ark\Operations\Payments\Contracts\SquarePaymentsClient::class, fn () => $this->fakeSquare);
});

test('square terminal payment records ledger entry after poll completes', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ]);

    $response->assertOk();

    $attemptId = $response->json('attempt.id');

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->exists())->toBeTrue()
        ->and($repairOrder->fresh()->isPaid())->toBeTrue()
        ->and($repairOrder->fresh()->payment_status)->toBe(RepairOrderPaymentStatus::Paid);
});

test('square terminal poll waits when checkout completes before payment is completed', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk();

    $attemptId = $response->json('attempt.id');
    $paymentId = 'fake-payment-'.$attemptId;

    $this->fakeSquare->setPaymentStatus($paymentId, 'APPROVED', $response->json('attempt.amount_cents'));

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Pending->value);

    $this->fakeSquare->setPaymentStatus($paymentId, 'COMPLETED', $response->json('attempt.amount_cents'));

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);
});

test('square terminal payment updates financial posture on repair order page', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $response = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
    ])->assertOk();

    $attemptId = $response->json('attempt.id');

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Paid / ready to close')
        ->assertSee('Ready to release')
        ->assertSee('$0.00')
        ->assertDontSee('Collect balance before release');
});

test('square keyed payment records ledger entry', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $initiate = $this->postJson(route('operations.repair-orders.square-payments.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Keyed->value,
    ])->assertOk();

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('operations.repair-orders.square-payments.complete', [$repairOrder, $attemptId]), [
        'source_id' => 'cnon:test-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(PaymentGatewayAttempt::query()->find($attemptId)?->ledger_entry_id)->not->toBeNull();
});

test('portal pay link completes square payment', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $token = app(\App\Ark\Operations\Payments\CreateCustomerPayTokenAction::class)
        ->execute($repairOrder->fresh(), $repairOrder->fresh()->estimateDocuments()->latest('id')->first());

    $initiate = $this->postJson(route('portal.invoice-pay.attempts.store', ['token' => $token->plainToken]))
        ->assertOk();

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('portal.invoice-pay.attempts.complete', [
        'token' => $token->plainToken,
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:portal-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect($repairOrder->fresh()->isPaid())->toBeTrue();
});

test('invoice email includes pay link when square email pay is enabled', function () {
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['email' => 'customer@example.com'])->save();
    issueFinalInvoiceFor($repairOrder);

    $invoice = $repairOrder->fresh()->estimateDocuments()->latest('id')->first();
    $invoice->forceFill([
        'pdf_path' => 'estimates/test-invoice.pdf',
        'needs_pdf_refresh' => false,
    ])->save();

    Storage::disk('local')->put('estimates/test-invoice.pdf', '%PDF-1.4 test');

    $this->post(route('operations.repair-orders.invoice.email', $repairOrder), [
        'email' => 'customer@example.com',
    ])->assertRedirect();

    Mail::assertSent(InvoiceCustomerMail::class, function (InvoiceCustomerMail $mail): bool {
        return $mail->payUrl !== null
            && str_contains($mail->payUrl, '/portal/pay/');
    });

    expect(CustomerDocumentAccessToken::query()->where('repair_order_id', $repairOrder->id)->exists())->toBeTrue();
});

test('hosted invoice email uses platform mail', function () {
    enableHostedPlatformMail();
    fakeHostedPlatformMail();
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['email' => 'customer@example.com'])->save();
    issueFinalInvoiceFor($repairOrder);

    $invoice = $repairOrder->fresh()->estimateDocuments()->latest('id')->first();
    $invoice->forceFill([
        'pdf_path' => 'estimates/test-invoice.pdf',
        'needs_pdf_refresh' => false,
    ])->save();

    Storage::disk('local')->put('estimates/test-invoice.pdf', '%PDF-1.4 test');

    $this->post(route('operations.repair-orders.invoice.email', $repairOrder), [
        'email' => 'customer@example.com',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Invoice emailed to customer@example.com.');

    Mail::assertNothingSent();
    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://cloud.test/api/v1/services/mail/messages/transactional'
            && ($body['operation'] ?? null) === 'invoice.send'
            && ($body['to'] ?? null) === 'customer@example.com'
            && ($body['attachments'][0]['mime'] ?? null) === 'application/pdf'
            && filled($body['attachments'][0]['content_base64'] ?? null);
    });
});

test('square webhook completion is idempotent', function () {
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $attempt = PaymentGatewayAttempt::query()->create([
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'financial_document_id' => $repairOrder->estimateDocuments()->latest('id')->value('id'),
        'gateway' => 'square',
        'capture_surface' => PaymentCaptureSurface::Keyed->value,
        'amount_cents' => $repairOrder->fresh()->balanceDue()->balanceDueCents,
        'currency' => 'USD',
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        'status' => PaymentGatewayAttemptStatus::Pending,
        'initiated_at' => now(),
    ]);

    $payload = [
        'type' => 'payment.updated',
        'data' => [
            'object' => [
                'payment' => [
                    'id' => 'sq-payment-123',
                    'status' => 'COMPLETED',
                    'reference_id' => $attempt->referenceId(),
                    'amount_money' => ['amount' => $attempt->amount_cents],
                ],
            ],
        ],
    ];

    $url = route('webhooks.square');
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $url.$body, 'test-signature-key', true));

    $this->postJson($url, $payload, [
        'x-square-hmacsha256-signature' => $signature,
    ])->assertOk();

    $this->postJson($url, $payload, [
        'x-square-hmacsha256-signature' => $signature,
    ])->assertOk();

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Payment)
        ->count())->toBe(1);
});

test('financial rail exposes square charge affordance when configured', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Charge card on reader')
        ->assertSee('Email invoice');
});

test('square terminal deposit records ledger entry before invoice is issued', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();

    $response = $this->postJson(route('operations.repair-orders.square-deposits.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Terminal->value,
        'amount' => 50.00,
    ]);

    $response->assertOk();

    $attemptId = $response->json('attempt.id');

    $this->getJson(route('operations.repair-orders.square-payments.show', [$repairOrder, $attemptId]))
        ->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->exists())->toBeTrue()
        ->and(PaymentGatewayAttempt::query()->find($attemptId)?->ledger_entry_id)->not->toBeNull();
});

test('square keyed deposit records ledger entry before invoice is issued', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();

    $initiate = $this->postJson(route('operations.repair-orders.square-deposits.store', $repairOrder), [
        'capture_surface' => PaymentCaptureSurface::Keyed->value,
        'amount' => 75.00,
    ])->assertOk();

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('operations.repair-orders.square-payments.complete', [$repairOrder, $attemptId]), [
        'source_id' => 'cnon:deposit-token',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->exists())->toBeTrue();
});

test('financial rail exposes square deposit affordance before invoice is issued', function () {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Collect deposit on reader')
        ->assertSee('Record deposit in ledger');
});
