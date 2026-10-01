<?php

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeEntryKind;
use App\Ark\Operations\RepairOrders\WorksheetContinuity;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('a labor line save declares the lines scope and renders non-nested continuity regions', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Front brakes grinding',
            'observed_summary' => 'Front brakes grinding',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->latest('id')
        ->firstOrFail();

    foreach (['Replace front pads', 'Machine rotors'] as $description) {
        $this->actingAs($advisor)
            ->post(route('operations.repair-orders.lines.store', $repairOrder), [
                'repair_order_concern_id' => $concern->id,
                'type' => RepairOrderLineType::Labor->value,
                'description' => $description,
                'quantity' => '1.00',
                'labor_entered_hours' => '1.00',
                'unit_price' => '165.00',
            ])
            ->assertRedirect();
    }

    $saved = RepairOrderLine::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('description', 'Machine rotors')
        ->firstOrFail();

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $saved]), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Machine rotors',
            'quantity' => '2.00',
            'labor_entered_hours' => '2.00',
            'unit_price' => '165.00',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
        ->assertSee('data-ark-line-id="'.$saved->id.'"', false)
        ->assertSee('id="line-'.$saved->id.'"', false)
        ->assertSee('data-continuity-region="line"', false)
        ->assertSee('id="estimate-totals-region"', false)
        ->assertSee('data-continuity-region="composer"', false)
        ->assertSee('id="concern-money-'.$concern->id.'"', false)
        ->assertSee('id="worksheet-count"', false)
        ->assertSee('data-continuity-region="worksheet-count"', false);

    expect((float) $saved->fresh()->quantity)->toBe(2.0);

    assertContinuityRegionsDoNotNest($response->getContent());
    assertPaymentFormIsOutsideTotalsRegion($response->getContent());
    assertOweTodayIsOutsideTotalsRegion($response->getContent());
    assertFinancialPositionIsIndependent($response->getContent());
    $response->assertSee('id="concern-lines-'.$concern->id.'"', false)
        ->assertSee('id="worksheet-line-editor"', false)
        ->assertSee('id="financial-position"', false);
});

test('adding editing and removing part sublet and fee lines declare the lines scope', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Continuity lines',
            'observed_summary' => 'Continuity lines',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Continuity anchor labor',
            'quantity' => '1.00',
            'labor_entered_hours' => '1.00',
            'unit_price' => '165.00',
        ])
        ->assertRedirect();

    $payloads = [
        [
            'type' => RepairOrderLineType::Part->value,
            'description' => 'Continuity brake pads',
            'quantity' => '1.00',
            'unit_price' => '68.00',
            'pricing_mode' => 'manual',
            'revised' => 'Continuity brake pads revised',
        ],
        [
            'type' => RepairOrderLineType::Sublet->value,
            'description' => 'Continuity machine rotors',
            'quantity' => '1.00',
            'part_cost' => '40.00',
            'unit_price' => '75.00',
            'revised' => 'Continuity machine rotors revised',
        ],
        [
            'type' => RepairOrderLineType::Fee->value,
            'description' => 'Continuity shop supplies',
            'quantity' => '1.00',
            'unit_price' => '12.00',
            'revised' => 'Continuity shop supplies revised',
        ],
    ];

    foreach ($payloads as $payload) {
        $revised = $payload['revised'];
        unset($payload['revised']);

        $stored = $this->actingAs($advisor)
            ->followingRedirects()
            ->post(route('operations.repair-orders.lines.store', $repairOrder), [
                'repair_order_concern_id' => $concern->id,
                ...$payload,
            ]);

        $line = RepairOrderLine::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('description', $payload['description'])
            ->firstOrFail();

        $stored->assertOk()
            ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
            ->assertSee('data-ark-line-id="'.$line->id.'"', false)
            ->assertSee('id="line-'.$line->id.'"', false);

        assertOweTodayIsOutsideTotalsRegion($stored->getContent());

        $edited = $this->actingAs($advisor)
            ->followingRedirects()
            ->patch(route('operations.repair-orders.lines.update', [$repairOrder, $line]), [
                'repair_order_concern_id' => $concern->id,
                ...$payload,
                'description' => $revised,
            ]);

        $edited->assertOk()
            ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
            ->assertSee('data-ark-line-id="'.$line->id.'"', false)
            ->assertSee($revised, false);

        $openEditor = $this->actingAs($advisor)
            ->get(route('operations.repair-orders.show', [
                'repairOrder' => $repairOrder,
                'editing_line' => $line->id,
            ]));

        $openEditor->assertOk()
            ->assertSee('id="line-editor-'.$line->id.'"', false);

        $editorIds = continuityXPath($openEditor->getContent())
            ->query('//*[@id="line-'.$line->id.'"]');

        expect($editorIds->length)->toBe(1);

        $removed = $this->actingAs($advisor)
            ->followingRedirects()
            ->delete(route('operations.repair-orders.lines.destroy', [$repairOrder, $line]));

        $removed->assertOk()
            ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
            ->assertDontSee('id="line-'.$line->id.'"', false)
            ->assertDontSee('data-ark-line-id="'.$line->id.'"', false);

        expect(RepairOrderLine::query()->whereKey($line->id)->exists())->toBeFalse();
    }
});

test('creating a concern declares the lines scope and the scope count', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'Rear brake noise',
            'observed_summary' => 'Rear brake noise',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
        ->assertDontSee('data-continuity-scope="lines workflow"', false)
        ->assertSee('id="worksheet-count"', false)
        ->assertSee('Rear brake noise', false)
        ->assertSee('id="worksheet-concerns"', false);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Draft))->toBeTrue();
});

test('the first line declares workflow when draft becomes estimate', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.concerns.store', $repairOrder), [
            'scope_entry_kind' => ScopeEntryKind::CustomerRequested->value,
            'summary' => 'First line workflow',
            'observed_summary' => 'First line workflow',
        ])
        ->assertRedirect();

    $concern = RepairOrderConcern::query()
        ->where('repair_order_id', $repairOrder->id)
        ->latest('id')
        ->firstOrFail();

    $first = $this->actingAs($advisor)
        ->followingRedirects()
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'First continuity labor',
            'quantity' => '1.00',
            'labor_entered_hours' => '1.00',
            'unit_price' => '165.00',
        ]);

    $first->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::declare(
            WorksheetContinuity::LINES,
            WorksheetContinuity::WORKFLOW,
        ).'"', false)
        ->assertSee('id="worksheet-count"', false)
        ->assertSee('id="repair-order-workflow"', false);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Estimate))->toBeTrue();

    $second = $this->actingAs($advisor)
        ->followingRedirects()
        ->post(route('operations.repair-orders.lines.store', $repairOrder), [
            'repair_order_concern_id' => $concern->id,
            'type' => RepairOrderLineType::Labor->value,
            'description' => 'Second continuity labor',
            'quantity' => '1.00',
            'labor_entered_hours' => '1.00',
            'unit_price' => '165.00',
        ]);

    $second->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::LINES.'"', false)
        ->assertDontSee('data-continuity-scope="lines workflow"', false);
});

test('lines continuity contract keeps dirty work and shows saved labor', function (): void {
    $result = Process::run([
        'node',
        '--test',
        base_path('resources/js/ark-worksheet-continuity-scope.test.js'),
    ]);

    if (! $result->successful()) {
        $this->fail($result->errorOutput()."\n".$result->output());
    }

    expect($result->exitCode())->toBe(0);
});

function assertContinuityRegionsDoNotNest(string $html): void
{
    $xpath = continuityXPath($html);
    $regions = $xpath->query('//*[@data-continuity-region]');

    expect($regions->length)->toBeGreaterThan(0);

    foreach ($regions as $region) {
        expect($region)->toBeInstanceOf(DOMElement::class);
        $nested = $xpath->query('.//*[@data-continuity-region]', $region);
        expect($nested->length)->toBe(0);
    }
}

function assertPaymentFormIsOutsideTotalsRegion(string $html): void
{
    $xpath = continuityXPath($html);
    $totals = $xpath->query('//*[@id="estimate-totals-region"]')->item(0);

    expect($totals)->toBeInstanceOf(DOMElement::class);

    $paymentForms = $xpath->query('.//form[contains(@action, "/payment")]', $totals);

    expect($paymentForms->length)->toBe(0);
}

function assertOweTodayIsOutsideTotalsRegion(string $html): void
{
    $xpath = continuityXPath($html);
    $totals = $xpath->query('//*[@id="estimate-totals-region"]')->item(0);

    expect($totals)->toBeInstanceOf(DOMElement::class);

    $oweToday = $xpath->query('.//*[contains(text(), "Owe today")]', $totals);

    expect($oweToday->length)->toBe(0);
}

function assertFinancialPositionIsIndependent(string $html): void
{
    $xpath = continuityXPath($html);
    $position = $xpath->query('//*[@id="financial-position"]')->item(0);
    $totals = $xpath->query('//*[@id="estimate-totals-region"]')->item(0);

    expect($position)->toBeInstanceOf(DOMElement::class)
        ->and($totals)->toBeInstanceOf(DOMElement::class)
        ->and($xpath->query('.//*[@id="financial-position"]', $totals)->length)->toBe(0)
        ->and($xpath->query('.//form[contains(@action, "/payment")]', $position)->length)->toBe(0);

    $oweToday = $xpath->query('//*[contains(text(), "Owe today")]');

    foreach ($oweToday as $node) {
        $insidePosition = false;
        $current = $node instanceof DOMElement ? $node : $node->parentNode;

        while ($current instanceof DOMElement) {
            if ($current->getAttribute('data-continuity-region') === 'financial-position') {
                $insidePosition = true;

                break;
            }

            $current = $current->parentNode;
        }

        expect($insidePosition)->toBeTrue();
    }
}

function continuityXPath(string $html): DOMXPath
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}
