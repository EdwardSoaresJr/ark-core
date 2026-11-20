<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\CustomerAccountStatementBuilder;
use App\Ark\Operations\Financial\CustomerAccountStatementEmailDelivery;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    $this->advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($this->advisor);
});

test('statement preview isolates the customer account and shows outstanding balance', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill([
        'email' => 'fleet@example.test',
        'address_line_1' => '100 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
    ])->save();

    issueFinalInvoiceFor($repairOrder);
    app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $this->advisor,
        'on_account',
    );

    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Account',
        'email' => 'other@example.test',
        'phone' => '555-9999',
    ]);

    $statement = app(CustomerAccountStatementBuilder::class)->forCustomer($repairOrder->customer->fresh());

    expect($statement['customer']['id'])->toBe($repairOrder->customer_id)
        ->and($statement['customer']['email'])->toBe('fleet@example.test')
        ->and($statement['total_outstanding_cents'])->toBe(15000)
        ->and($statement['outstanding_invoices'])->toHaveCount(1)
        ->and(collect($statement['outstanding_invoices'])->pluck('customer_id')->unique()->all())->toBe([$repairOrder->customer_id]);

    $this->get(route('operations.financials.accounts-receivable.statement.show', $repairOrder->customer_id))
        ->assertOk()
        ->assertSee('Account Statement')
        ->assertSee('INV #')
        ->assertDontSee('other@example.test');

    $this->get(route('operations.financials.accounts-receivable.statement.label', $repairOrder->customer_id))
        ->assertOk()
        ->assertSee('100 Main St')
        ->assertSee($repairOrder->customer->name)
        ->assertDontSee($other->name);

    $this->post(route('operations.financials.accounts-receivable.batch'), [
        'customer_ids' => [$repairOrder->customer_id],
        'action' => 'statements_print',
    ])->assertOk()->assertSee($repairOrder->customer->name);
});

test('statement email refuses a recipient that is not the customer email on file', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $repairOrder->customer->forceFill(['email' => 'fleet@example.test'])->save();
    issueFinalInvoiceFor($repairOrder);
    app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $this->advisor,
        'on_account',
    );

    expect(fn () => app(CustomerAccountStatementEmailDelivery::class)->send(
        $repairOrder->customer->fresh(),
        $this->advisor,
        'attacker@example.test',
    ))->toThrow(ValidationException::class);
});

test('portal statement only exposes the authenticated customer account', function () {
    $repairOrder = financialCloseoutRepairOrder();
    $customer = $repairOrder->customer;
    $customer->forceFill(['email' => 'portal-ar@example.test'])->save();
    issueFinalInvoiceFor($repairOrder);
    app(RepairOrderLifecycleTransition::class)->move(
        $repairOrder->fresh(),
        RepairOrderStatus::Closed,
        $this->advisor,
        'on_account',
    );

    $stranger = Customer::query()->create([
        'first_name' => 'Stranger',
        'last_name' => 'Customer',
        'email' => 'stranger@example.test',
        'phone' => '555-1111',
    ]);

    Auth::guard('portal')->login($customer);

    $this->get(route('portal.account.statement'))
        ->assertOk()
        ->assertSee($customer->name)
        ->assertSee('Account statement')
        ->assertDontSee($stranger->name)
        ->assertDontSee('stranger@example.test');
});
