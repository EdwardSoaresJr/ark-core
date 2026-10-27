<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Financial\InvoiceStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

function issuedInvoiceForView(): array
{
    $repairOrder = financialCloseoutRepairOrder();
    test()->post(route('operations.repair-orders.invoice.store', $repairOrder))->assertRedirect();

    $invoice = $repairOrder->fresh()->estimateDocuments()
        ->where('document_type', FinancialDocumentType::Invoice->value)
        ->firstOrFail();

    return [$repairOrder, $invoice];
}

test('an issued invoice opens as html with execution defaults its snapshot does not carry', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceForView();

    expect($invoice->status)->toBe(InvoiceStatus::Issued->value)
        ->and($invoice->snapshot_json['staff']['execution'])->not->toHaveKey('posture');

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertOk()
        ->assertSee($invoice->snapshot_json['staff']['execution']['technician_name'])
        ->assertSee('Execution posture not recorded')
        ->assertSee('Review repair order status');
});

test('legacy invoice snapshots missing optional staff fields open without changing the issued invoice', function (?array $staff) {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceForView();

    $snapshot = $invoice->snapshot_json;
    if ($staff === null) {
        unset($snapshot['staff']);
    } else {
        $snapshot['staff'] = $staff;
    }
    EstimateDocument::query()->whereKey($invoice->id)->update(['snapshot_json' => json_encode($snapshot)]);
    $stored = fn (): array => (array) EstimateDocument::query()->toBase()->where('id', $invoice->id)->first(['snapshot_json', 'status', 'document_number', 'pdf_path', 'updated_at']);
    $before = $stored();

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertOk()
        ->assertSee('Execution posture not recorded');

    expect($stored())->toBe($before);
})->with([
    'no staff block' => [null],
    'empty execution' => [['execution' => []]],
    'technician only' => [['execution' => ['technician_name' => 'Legacy Tech']]],
    'null fields' => [['execution' => ['technician_name' => null, 'posture' => null, 'next_action' => null]]],
]);

test('a snapshot that records execution posture still shows its own values (the technician name stays live)', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceForView();

    $snapshot = $invoice->snapshot_json;
    $snapshot['staff']['execution'] = ['technician_name' => 'Recorded Tech', 'posture' => 'Waiting on parts', 'next_action' => 'Call the parts desk'];
    EstimateDocument::query()->whereKey($invoice->id)->update(['snapshot_json' => json_encode($snapshot)]);

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertOk()
        ->assertSee('Waiting on parts')
        ->assertSee('Call the parts desk')
        ->assertDontSee('Execution posture not recorded');
});

test('invoice html stays behind staff sign-in', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));
    [$repairOrder, $invoice] = issuedInvoiceForView();

    auth()->logout();

    $this->get(route('operations.repair-orders.estimate-documents.show', [$repairOrder, $invoice]))
        ->assertRedirect(route('login'));
});
