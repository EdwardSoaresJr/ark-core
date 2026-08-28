<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\ProposeConcernsFromVisitReason;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('visit reason proposals suggest front and rear brake concerns without writing visit_reason', function () {
    $proposals = app(ProposeConcernsFromVisitReason::class)->propose(
        "My brakes make noise on hard stops.\nI think I need front and rear brakes.",
    );

    expect($proposals)->toHaveCount(2)
        ->and(collect($proposals)->pluck('summary')->all())->toBe([
            'Front Brake Inspection',
            'Rear Brake Inspection',
        ]);
});

test('advisor can accept visit reason proposals into concerns without changing visit reason', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Kyle',
        'last_name' => 'Kight',
        'phone' => '555-1645',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Kia',
        'model' => 'Forte',
    ]);

    $reason = "My brakes make noise on hard stops.\nI think I need front and rear brakes.";

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'visit_reason' => $reason,
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Reason for Visit')
        ->assertSee('Suggested concerns')
        ->assertSee('Front Brake Inspection')
        ->assertSee('Rear Brake Inspection')
        ->assertSee("'visit-reason'", false);

    $this->post(route('operations.repair-orders.visit-reason.concerns.accept', $repairOrder), [
        'opened_estimate_version' => $repairOrder->estimate_version,
        'proposals' => [
            [
                'summary' => 'Front Brake Inspection',
                'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
            ],
            [
                'summary' => 'Rear Brake Inspection',
                'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
            ],
        ],
    ])->assertRedirect();

    $repairOrder->refresh();

    expect($repairOrder->visit_reason)->toBe($reason)
        ->and($repairOrder->concerns)->toHaveCount(2)
        ->and($repairOrder->concerns->pluck('summary')->all())->toBe([
            'Front Brake Inspection',
            'Rear Brake Inspection',
        ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee($reason, false)
        ->assertDontSee('Suggested concerns');
});

test('advisor can edit suggested concern title before accept', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $customer = Customer::query()->create([
        'first_name' => 'Kyle',
        'last_name' => 'Kight',
        'phone' => '555-1646',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Kia',
        'model' => 'Forte',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Draft,
        'visit_reason' => "My brakes make noise on hard stops.\nI think I need front and rear brakes.",
        'concern_summary' => '',
        'opened_at' => now(),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('visit-reason-proposal-0', false)
        ->assertSee('x-model="proposals[0].summary"', false);

    $this->post(route('operations.repair-orders.visit-reason.concerns.accept', $repairOrder), [
        'opened_estimate_version' => $repairOrder->estimate_version,
        'proposals' => [
            [
                'summary' => 'Front brakes — noise on hard stops',
                'scope_entry_kind' => ScopeEntryKind::CustomerConcern->value,
            ],
        ],
    ])->assertRedirect();

    $repairOrder->refresh();

    expect($repairOrder->concerns)->toHaveCount(1)
        ->and($repairOrder->concerns->first()->summary)->toBe('Front brakes — noise on hard stops');
});
