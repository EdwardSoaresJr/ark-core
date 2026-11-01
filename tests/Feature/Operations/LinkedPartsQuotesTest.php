<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\LinkedPartsQuotes;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

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

    $script = file_get_contents(base_path('resources/js/ark-linked-parts-quote.js'));
    $app = file_get_contents(base_path('resources/js/app.js'));

    expect($html)->toContain('data-linked-parts-quote')
        ->and($html)->toContain('>Manage</button>')
        ->and($html)->toContain('data-linked-parts-quote-dialog')
        ->and($html)->toContain('modal-dialog')
        ->and($html)->toContain('Refresh quote')
        ->and($html)->toContain('Change linked cart')
        ->and($html)->toContain('Quote history')
        ->and($html)->toContain('Showing the last saved quote. This check did not verify it.')
        ->and($html)->toContain('x-data="arkLinkedPartsQuote(')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('ops-worksheet-procurement-panel')
        ->and($html)->not->toContain('window.arkLinkedPartsQuote')
        ->and($html)->not->toContain('opens the linked quote')
        ->and($html)->not->toContain('>Open PartsTech<')
        ->and($script)->toContain('applyPanel(payload)')
        ->and($script)->not->toContain('this.panel = payload')
        ->and($script)->toContain('No quote linked')
        ->and(strpos($app, "Alpine.data('arkLinkedPartsQuote'"))->toBeInt()
        ->and(strpos($app, "Alpine.data('arkLinkedPartsQuote'"))->toBeLessThan(strpos($app, 'Alpine.start()'));

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.linked-parts-quotes.store', $repairOrder), [
            'external_id' => '100000001',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'linked');

    expect($fake->linked)->toBe(['100000001'])
        ->and(RepairOrderLine::query()->count())->toBe(0);
});

test('the repair order page includes the compact linked quote row', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $repairOrder = linkedQuoteRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);
    $this->app->instance(LinkedPartsQuotes::class, new class implements LinkedPartsQuotes
    {
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
            return ['ok' => false, 'message' => 'No quote is linked.'];
        }

        public function candidates(RepairOrder $repairOrder, User $user): array
        {
            return ['ok' => true, 'message' => '', 'candidates' => []];
        }

        public function link(RepairOrder $repairOrder, User $user, string $externalId): array
        {
            return ['ok' => false, 'message' => 'not used'];
        }

        public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array
        {
            return ['ok' => false, 'message' => 'not used'];
        }
    });

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-linked-parts-quote')
        ->and($html)->toContain('>Manage</button>')
        ->and($html)->toContain('x-data="arkLinkedPartsQuote(')
        ->and($html)->toContain('Example Catalog')
        ->and($html)->not->toContain('window.arkLinkedPartsQuote');
});

test('stock core leaves the linked quote row off the repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $repairOrder = linkedQuoteRepairOrder();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $html = $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('data-linked-parts-quote')
        ->and($html)->not->toContain('arkLinkedPartsQuote(');
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
