<?php

use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('desktop financial sidebar token is 360px and stays the flexible estimate companion', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--ops-estimate-rail-width: 360px;')
        ->not->toContain('--ops-estimate-rail-width: 320px;')
        ->not->toContain('--ops-estimate-rail-width: 340px;')
        ->toContain('max-width: min(100%, var(--ops-estimate-rail-width))')
        ->toContain('.ops-review-rail--pinned')
        ->toContain('position: sticky')
        ->toContain('var(--ops-ro-footer-offset');
});

test('uninvoiced estimate keeps financial summary and payment actions in the right rail', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('class="ops-review-rail ops-review-rail--pinned"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('id="financial-payment-panel"', false)
        ->assertSee('Generate Final Invoice');
});

test('invoiced repair order keeps amount and method paired and date and reference full width', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('ops-rail-payment-fields__pair', false)
        ->assertSee('ops-rail-payment-fields__full', false)
        ->assertSee('name="amount"', false)
        ->assertSee('name="payment_method"', false)
        ->assertSee('name="paid_at"', false)
        ->assertSee('name="reference"', false)
        ->assertSee('Record Payment')
        ->assertSee('id="financial-rail"', false);
});

test('partially paid invoice still exposes rail payment controls', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        5000,
        PaymentMethod::Cash,
    );

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('Record Payment')
        ->assertSee('ops-rail-payment-fields__pair', false);
});

test('fully paid invoice keeps totals and balance in the right rail', function () {
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    $this->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('id="estimate-builder-rail"', false)
        ->assertSee('id="estimate-total-panel"', false)
        ->assertSee('id="financial-payment-panel"', false);
});
