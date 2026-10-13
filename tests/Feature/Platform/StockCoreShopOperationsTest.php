<?php

use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Messaging\NotConfiguredOutboundSmsTransport;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;

test('stock core runs the shop without saas, twilio, or platform', function () {
    Http::fake();

    expect(class_exists('Ark\\Saas\\SaasServiceProvider'))->toBeFalse()
        ->and(is_file(base_path('app/Ark/Texting/PlatformOutboundSmsTransport.php')))->toBeFalse()
        ->and(is_file(base_path('app/Ark/Texting/ArkTextingClient.php')))->toBeFalse()
        ->and(env('TWILIO_ACCOUNT_SID'))->toBeNull()
        ->and(env('TWILIO_AUTH_TOKEN'))->toBeNull();

    $transport = app(OutboundSmsTransport::class);
    expect($transport)->toBeInstanceOf(NotConfiguredOutboundSmsTransport::class)
        ->and($transport->isConfigured())->toBeFalse();
    expect(fn () => $transport->send('+17195550100', 'Hello'))
        ->toThrow(RuntimeException::class, 'Outbound SMS is not configured.');

    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customerId = $this->post(route('operations.customers.store'), [
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '7195550100',
        'email' => 'rosa@example.com',
        'customer_type' => 'Retail',
    ])->assertRedirect()->headers->get('Location');

    $customer = \App\Ark\Operations\Customers\Customer::query()->where('phone', '7195550100')->firstOrFail();
    expect($customerId)->toContain((string) $customer->id);

    $this->post(route('operations.customers.vehicles.store', $customer), [
        'vin' => '1HGCM82633A004352',
        'plate' => 'ARK123',
        'year' => 2018,
        'make' => 'Honda',
        'model' => 'Accord',
        'plate_state' => 'CO',
    ])->assertRedirect();

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->where('plate', 'ARK123')->firstOrFail();

    $this->post(route('operations.customers.repair-orders.drafts.store', $customer), [
        'vehicle_id' => $vehicle->id,
        'visit_reason' => 'Customer states the brakes grind.',
    ])->assertRedirect();

    $repairOrder = RepairOrder::query()->where('customer_id', $customer->id)->firstOrFail();

    $this->post(route('operations.repair-orders.concerns.store', $repairOrder), [
        'scope_entry_kind' => 'customer_concern',
        'summary' => 'Brakes grind',
        'notes' => 'Worse on the first stop of the morning.',
    ])->assertRedirect();

    $concernId = RepairOrderConcern::query()->where('repair_order_id', $repairOrder->id)->value('id');

    $this->post(route('operations.repair-orders.lines.store', $repairOrder), [
        'repair_order_concern_id' => $concernId,
        'type' => RepairOrderLineType::Labor->value,
        'description' => 'Inspect front brakes',
        'quantity' => '1.00',
    ])->assertRedirect();

    expect(RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->count())->toBe(1);

    $inspection = Inspection::query()->create([
        'repair_order_id' => $repairOrder->id,
        'notes' => 'Front pads are low.',
        'started_at' => now(),
    ]);
    expect($inspection->fresh()->repair_order_id)->toBe($repairOrder->id);

    $closeout = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($closeout);
    $this->patch(route('operations.repair-orders.payment.update', $closeout->fresh()), [
        'amount' => '150.00',
        'payment_method' => 'cash',
        'reference' => 'Counter cash',
        FinancialSubmissionIntentGate::FIELD => financialSubmissionKey(),
    ])->assertRedirect();

    expect($closeout->fresh()->isPaid())->toBeTrue();

    Http::assertNothingSent();
});
