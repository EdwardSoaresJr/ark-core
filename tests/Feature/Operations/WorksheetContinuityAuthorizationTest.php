<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialPositionProjection;
use App\Ark\Operations\RepairOrders\ApprovedWorkScope;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorksheetContinuity;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('approving one concern declares authorization and workflow without authorizing the other', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $pads = continuityConcern($repairOrder, 'Continuity pads', 1);
    $rotors = continuityConcern($repairOrder, 'Continuity rotors', 2);
    $padLine = continuityLabor($repairOrder, $pads, 'Continuity pad labor', 16500);
    continuityLabor($repairOrder, $rotors, 'Continuity rotor labor', 9900);

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $pads]), [
            'disposition' => RepairOrderConcernDisposition::Approved->value,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::declare(
            WorksheetContinuity::AUTHORIZATION,
            WorksheetContinuity::WORKFLOW,
            WorksheetContinuity::LINES,
            WorksheetContinuity::SETTLEMENT,
        ).'"', false)
        ->assertSee('data-continuity-concern-id="'.$pads->id.'"', false)
        ->assertSee('id="authorized-money"', false)
        ->assertSee('id="authorized-lines"', false)
        ->assertSee('id="financial-position"', false)
        ->assertSee('id="invoice-posture"', false)
        ->assertSee('id="repair-order-workflow"', false)
        ->assertSee('id="worksheet-line-editor"', false)
        ->assertSee('id="authorization-rail"', false)
        ->assertSee('data-continuity-region="authorization-rail"', false);

    expect($pads->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and($rotors->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and(authorizationRegionText($response->getContent(), 'concern-authorization-'.$pads->id))->toContain('Approved')
        ->and(authorizationRegionText($response->getContent(), 'concern-authorization-'.$rotors->id))->toContain('Pending')
        ->and(authorizationRegionText($response->getContent(), 'concern-disposition-'.$rotors->id))->toContain('Recommended');

    $scope = ApprovedWorkScope::query()->where('repair_order_concern_id', $pads->id)->sole();

    expect($scope->lineIds())->toBe([$padLine->id])
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $rotors->id)->exists())->toBeFalse();

    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns']);
    $approved = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder);
    $position = FinancialPositionProjection::for($repairOrder);

    expect($approved->lines->pluck('id')->all())->toBe([$padLine->id])
        ->and(authorizationRegionText($response->getContent(), 'authorized-money'))->toContain($approved->format($approved->totalCents()))
        ->and(authorizationRegionText($response->getContent(), 'financial-position'))->toContain($position->projectedBalanceLabel())
        ->and(authorizationRegionText($response->getContent(), 'authorized-lines'))->not->toContain('Continuity rotor labor');

    assertAuthorizationRegionsDoNotNest($response->getContent());
});

test('deferring approved work drops it from authorized dollars and can retreat status', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $repairOrder->update(['status' => RepairOrderStatus::Approved->value]);
    $pads = continuityConcern($repairOrder, 'Continuity pads', 1);
    $pads->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    $rotors = continuityConcern($repairOrder, 'Continuity rotors', 2);
    continuityLabor($repairOrder, $pads, 'Continuity pad labor', 16500);
    continuityLabor($repairOrder, $rotors, 'Continuity rotor labor', 9900);

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.concerns.disposition', [$repairOrder, $pads]), [
            'disposition' => RepairOrderConcernDisposition::Deferred->value,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::declare(
            WorksheetContinuity::AUTHORIZATION,
            WorksheetContinuity::WORKFLOW,
            WorksheetContinuity::LINES,
            WorksheetContinuity::SETTLEMENT,
        ).'"', false);

    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns']);
    $approved = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder);
    $position = FinancialPositionProjection::for($repairOrder);

    expect($pads->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Deferred)
        ->and($rotors->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($repairOrder->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and($approved->totalCents())->toBe(0)
        ->and(authorizationRegionText($response->getContent(), 'concern-authorization-'.$pads->id))->toContain('Deferred')
        ->and(authorizationRegionText($response->getContent(), 'concern-authorization-'.$rotors->id))->toContain('Pending')
        ->and(authorizationRegionText($response->getContent(), 'authorized-money'))->toContain($approved->format(0))
        ->and($position->customerOwesTodayCents)->toBe(0)
        ->and(authorizationRegionText($response->getContent(), 'financial-position'))->not->toContain('Owe today')
        ->and(authorizationSelectedStatus($response->getContent()))->toBe(RepairOrderStatus::WaitingApproval->value);
});

test('recording authorization leaves later recommended work unauthorized', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = repairOrderForEstimateWorkspace();
    $pads = continuityConcern($repairOrder, 'Continuity pads', 1);
    $rotors = continuityConcern($repairOrder, 'Continuity rotors', 2);
    $pads->update(['disposition' => RepairOrderConcernDisposition::Approved]);
    continuityLabor($repairOrder, $pads, 'Continuity pad labor', 16500);
    $rotorLine = continuityLabor($repairOrder, $rotors, 'Continuity rotor labor', 9900);

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->post(route('operations.repair-orders.authorization.store', $repairOrder), [
            'source' => ApprovalSource::InPerson->value,
            'approved_by' => 'Rosa Garcia',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::declare(
            WorksheetContinuity::AUTHORIZATION,
            WorksheetContinuity::WORKFLOW,
            WorksheetContinuity::SETTLEMENT,
        ).'"', false);

    expect($rotors->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Recommended)
        ->and($pads->fresh()->disposition)->toBe(RepairOrderConcernDisposition::Approved)
        ->and(ApprovedWorkScope::query()->where('repair_order_concern_id', $rotors->id)->exists())->toBeFalse()
        ->and(app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder->fresh(['lines.concern']))->lines->pluck('id')->all())
        ->not->toContain($rotorLine->id)
        ->and(authorizationRegionText($response->getContent(), 'concern-authorization-'.$rotors->id))->toContain('Pending');
});

function continuityConcern($repairOrder, string $summary, int $position): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => $summary,
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => $position,
    ]);
}

function continuityLabor($repairOrder, RepairOrderConcern $concern, string $description, int $cents): RepairOrderLine
{
    return RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => $description,
        'quantity' => '1.00',
        'unit_price_cents' => $cents,
    ]);
}

function authorizationRegionText(string $html, string $id): string
{
    $region = authorizationContinuityXPath($html)->query('//*[@id="'.$id.'"]')->item(0);

    expect($region)->toBeInstanceOf(DOMElement::class);

    return trim(preg_replace('/\s+/', ' ', $region->textContent) ?? '');
}

function authorizationSelectedStatus(string $html): string
{
    $option = authorizationContinuityXPath($html)
        ->query('//*[@id="repair-order-workflow"]//option[@selected]')
        ->item(0);

    expect($option)->toBeInstanceOf(DOMElement::class);

    return $option->getAttribute('value');
}

function assertAuthorizationRegionsDoNotNest(string $html): void
{
    $xpath = authorizationContinuityXPath($html);
    $regions = $xpath->query('//*[@data-continuity-region]');

    expect($regions->length)->toBeGreaterThan(0);

    foreach ($regions as $region) {
        expect($xpath->query('.//*[@data-continuity-region]', $region)->length)->toBe(0);
    }
}

function authorizationContinuityXPath(string $html): DOMXPath
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}
