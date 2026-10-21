<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\LinkedPartsQuotes;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

test('the repair order can show a provider neutral linked quote without importing parts', function () {
    $repairOrder = linkedQuoteRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $fake = new class implements LinkedPartsQuotes
    {
        public array $linked = [];

        public function available(): bool
        {
            return true;
        }

        public function present(RepairOrder $repairOrder): array
        {
            return [
                'available' => true,
                'provider_label' => 'Example Catalog',
                'open_note' => 'Opening the catalog does not open this saved quote.',
                'current' => null,
                'history' => [],
            ];
        }

        public function refresh(RepairOrder $repairOrder, User $user): array
        {
            return ['ok' => true, 'message' => 'refreshed'];
        }

        public function candidates(RepairOrder $repairOrder, User $user): array
        {
            return ['ok' => true, 'candidates' => []];
        }

        public function link(RepairOrder $repairOrder, User $user, string $externalId): array
        {
            $this->linked[] = $externalId;

            return ['ok' => true, 'message' => 'linked'];
        }

        public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array
        {
            return ['ok' => true, 'message' => 'current'];
        }
    };
    $this->app->instance(LinkedPartsQuotes::class, $fake);

    $html = view('operations.repair-orders.partials.repair-order-linked-quote', [
        'repairOrder' => $repairOrder,
    ])->render();

    expect($html)->toContain('Refresh quote')
        ->and($html)->toContain('Change linked cart')
        ->and($html)->toContain('Quote history')
        ->and($html)->toContain('Showing the last saved quote. This check did not verify it.')
        ->and($html)->toContain('applyPanel(payload)')
        ->and($html)->not->toContain('this.panel = payload')
        ->and($html)->not->toContain('opens the linked quote')
        ->and($html)->not->toContain('>Open PartsTech<');

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.linked-parts-quotes.store', $repairOrder), [
            'external_id' => '100000001',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'linked');

    expect($fake->linked)->toBe(['100000001'])
        ->and(RepairOrderLine::query()->count())->toBe(0);
});

test('linked quote actions require repair order permission', function () {
    $repairOrder = linkedQuoteRepairOrder();
    $this->actingAs(User::factory()->create())
        ->getJson(route('operations.repair-orders.linked-parts-quotes.show', $repairOrder))
        ->assertForbidden();
});

function linkedQuoteRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Garcia',
        'phone' => '555-0100',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'vin' => '1G4HP54K62U201495',
        'normalized_vin' => '1G4HP54K62U201495',
        'year' => 2002,
        'make' => 'Buick',
        'model' => 'LeSabre',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'repair_order_id' => 1788,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Linked quote',
    ]);
}
