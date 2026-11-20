<?php

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

test('advisor can edit mileage through direct inline input', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    foreach ([route('operations.repair-orders.show', $repairOrder), route('operations.repair-orders.show', $repairOrder)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('arkRepairOrderMileage', false)
            ->assertSee('data-mileage-input="in"', false)
            ->assertSee('>In</span>', false)
            ->assertSee('>Out</span>', false);
    }

    $this->patchJson(route('operations.repair-orders.mileage.update', $repairOrder), [
        'mileage_in' => 165604,
        'mileage_out' => 165892,
    ])
        ->assertOk()
        ->assertJson([
            'mileage_in' => 165604,
            'mileage_out' => 165892,
        ]);

    $repairOrder->refresh();

    expect($repairOrder->mileage_in)->toBe(165604)
        ->and($repairOrder->mileage_out)->toBe(165892);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('165,604')
        ->assertSee('165,892');
});

test('mileage out cannot be less than mileage in', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $this->from(route('operations.repair-orders.show', $repairOrder))
        ->patch(route('operations.repair-orders.mileage.update', $repairOrder), [
            'mileage_in' => 120000,
            'mileage_out' => 119000,
        ])
        ->assertSessionHasErrors('mileage_out');

    expect(RepairOrder::query()->findOrFail($repairOrder->id)->mileage_out)->toBeNull();
});

test('advisor can set mileage out on a closed repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();
    $repairOrder->update([
        'status' => RepairOrderStatus::Closed,
        'mileage_in' => 78401,
        'mileage_out' => null,
        'closed_at' => now(),
    ]);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('arkRepairOrderMileage', false)
        ->assertSee('data-mileage-input="in"', false);

    $this->patchJson(route('operations.repair-orders.mileage.update', $repairOrder), [
        'mileage_in' => 78401,
        'mileage_out' => 78405,
    ])
        ->assertOk()
        ->assertJson([
            'mileage_in' => 78401,
            'mileage_out' => 78405,
        ]);

    expect($repairOrder->fresh()->mileage_out)->toBe(78405);
});

test('RO header mileage In and Out are single whole-area buttons with no nested controls (#216)', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    [$repairOrder] = identityHeaderRepairOrderFixture();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach (['in' => 'In', 'out' => 'Out'] as $key => $label) {
        $triggers = $xpath->query('//*[@data-mileage-inline]//button[@data-mileage-trigger="'.$key.'"]');

        expect($triggers->length)->toBe(1);

        $button = $triggers->item(0);

        // The label is inside the button, so the label text, the value and the empty "-" all open the editor.
        expect(trim($button->getElementsByTagName('span')->item(0)->textContent))->toBe($label)
            ->and($button->getElementsByTagName('span')->length)->toBe(2)
            ->and($button->getAttribute('aria-label'))->toBe('Edit mileage '.$key)
            ->and($button->getAttribute('@click'))->toBe('open'.ucfirst($key).'()')
            // No nested interactive controls.
            ->and($xpath->query('.//button|.//input|.//a|.//select', $button)->length)->toBe(0)
            ->and($button->getAttribute('class'))->toContain('ark-mileage-trigger');
    }

    // Opening the editor never saves: save only runs on blur with a changed value.
    expect(file_get_contents(base_path('resources/css/tabler.css')))
        ->toContain('.ark-mileage-trigger')
        ->toContain('min-height: 2.75rem')
        ->toContain('.ark-mileage-trigger:focus-visible');
});
