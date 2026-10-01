<?php

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\FinancialPositionProjection;
use App\Ark\Operations\Financial\FinancialSubmissionIntentGate;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RepairOrderFinancialPresenter;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorksheetContinuity;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config(['broadcasting.default' => 'log']);
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

test('a manual deposit declares settlement and shows the ledger without a second row on replay', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $key = (string) str()->uuid();
    $payload = [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Continuity cash',
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="'.WorksheetContinuity::declare(WorksheetContinuity::SETTLEMENT).'"', false)
        ->assertDontSee('data-continuity-scope="settlement workflow"', false)
        ->assertSee('id="settlement-history"', false)
        ->assertSee('id="settlement-balance"', false)
        ->assertSee('id="financial-position"', false)
        ->assertSee('id="payment-posture"', false)
        ->assertSee('id="line-'.$line->id.'"', false);

    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns', 'customer']);
    $position = FinancialPositionProjection::for($repairOrder);
    $html = $response->getContent();

    expect($repairOrder->status->is(RepairOrderStatus::Approved))->toBeTrue()
        ->and(settlementLedgerCents($repairOrder->id, LedgerEntryType::Deposit))->toBe(1000)
        ->and(settlementLedgerCount($repairOrder->id, LedgerEntryType::Deposit))->toBe(1)
        ->and(settlementRegionText($html, 'financial-position'))->toContain($position->projectedBalanceLabel())
        ->and(settlementRegionText($html, 'settlement-balance'))->toContain($position->formatCents($position->depositsCents))
        ->and(settlementRegionText($html, 'settlement-history'))->toContain('$10.00')
        ->and(settlementRegionText($html, 'settlement-history-closeout'))->toContain('Continuity cash')
        ->and(settlementRegionText($html, 'line-'.$line->id))->toContain('Brake service');

    assertSettlementRegionsDoNotNest($html);
    assertSettlementRegionsExcludeCapture($html);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder), $payload)
        ->assertRedirect();

    expect(settlementLedgerCount($repairOrder->id, LedgerEntryType::Deposit))->toBe(1)
        ->and(settlementLedgerCents($repairOrder->id, LedgerEntryType::Deposit))->toBe(1000);
});

test('a committed deposit is returned when the financial broadcast throws', function (): void {
    $reported = false;
    Log::listen(function ($log) use (&$reported): void {
        if ($log->level === 'warning'
            && str_contains((string) $log->message, 'Financial broadcast failed after the ledger committed.')
            && str_contains((string) ($log->context['message'] ?? ''), 'Reverb is down.')) {
            $reported = true;
        }
    });

    Broadcast::shouldReceive('queue')
        ->once()
        ->andThrow(new BroadcastException('Reverb is down.'));

    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $key = (string) str()->uuid();
    $payload = [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Broadcast down',
        'deposit_confirmed' => '1',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.deposit.update', $repairOrder), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns', 'customer']);
    $position = FinancialPositionProjection::for($repairOrder);
    $html = $response->getContent();

    $response->assertOk()
        ->assertSee('data-continuity-scope="settlement"', false)
        ->assertDontSee('Reverb is down.', false);

    expect($reported)->toBeTrue()
        ->and(settlementLedgerCount($repairOrder->id, LedgerEntryType::Deposit))->toBe(1)
        ->and(settlementLedgerCents($repairOrder->id, LedgerEntryType::Deposit))->toBe(1000)
        ->and($position->depositsCents)->toBe(1000)
        ->and(settlementRegionText($html, 'financial-position'))->toContain($position->projectedBalanceLabel())
        ->and(settlementRegionText($html, 'settlement-balance'))->toContain($position->formatCents($position->depositsCents))
        ->and(settlementRegionText($html, 'settlement-history-closeout'))->toContain('Broadcast down');
});

test('a manual payment declares settlement and moves the issued balance without a second row on replay', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    $repairOrder = $repairOrder->fresh();
    $line = RepairOrderLine::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $key = (string) str()->uuid();
    $payload = [
        'amount' => '10.00',
        'payment_method' => PaymentMethod::Cash->value,
        'reference' => 'Continuity cash',
        RepairOrderConcurrency::FIELD => $repairOrder->estimate_version,
        FinancialSubmissionIntentGate::FIELD => $key,
    ];

    $response = $this->actingAs($advisor)
        ->followingRedirects()
        ->patch(route('operations.repair-orders.payment.update', $repairOrder), $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ]);

    $response->assertOk()
        ->assertSee('data-continuity-scope="settlement"', false)
        ->assertDontSee('data-continuity-scope="settlement workflow"', false);

    $repairOrder = $repairOrder->fresh(['lines.concern', 'concerns', 'customer']);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $financial = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);
    $html = $response->getContent();

    expect(settlementLedgerCount($repairOrder->id, LedgerEntryType::Payment))->toBe(1)
        ->and(settlementLedgerCents($repairOrder->id, LedgerEntryType::Payment))->toBe(1000)
        ->and(settlementRegionText($html, 'settlement-balance'))->toContain($financial['settlementBalanceDue'])
        ->and(settlementRegionText($html, 'settlement-history'))->toContain('$10.00')
        ->and(settlementRegionText($html, 'settlement-history-closeout'))->toContain('Continuity cash')
        ->and(settlementRegionText($html, 'line-'.$line->id))->toContain('Brake service')
        ->and($financial)->not->toHaveKey('paymentCaptureReadiness');

    assertSettlementRegionsDoNotNest($html);
    assertSettlementRegionsExcludeCapture($html);

    $this->actingAs($advisor)
        ->patch(route('operations.repair-orders.payment.update', $repairOrder), $payload)
        ->assertRedirect();

    expect(settlementLedgerCount($repairOrder->id, LedgerEntryType::Payment))->toBe(1)
        ->and(settlementLedgerCents($repairOrder->id, LedgerEntryType::Payment))->toBe(1000);
});

test('settlement presentation does not carry card capture readiness', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::Approved);
    $totals = app(EstimateTotalsCalculator::class)->totalsFor($repairOrder);
    $financial = app(RepairOrderFinancialPresenter::class)->for($repairOrder, $totals);

    expect($financial)->not->toHaveKey('paymentCaptureReadiness')
        ->and(is_bool($financial['canTakePaymentCapture']))->toBeTrue();
});

function settlementLedgerCount(int $repairOrderId, LedgerEntryType $type): int
{
    return RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', $type)
        ->count();
}

function settlementLedgerCents(int $repairOrderId, LedgerEntryType $type): int
{
    return (int) RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrderId)
        ->where('entry_type', $type)
        ->sum('amount_cents');
}

function settlementRegionText(string $html, string $id): string
{
    $region = settlementContinuityXPath($html)->query('//*[@id="'.$id.'"]')->item(0);

    expect($region)->toBeInstanceOf(DOMElement::class);

    return trim(preg_replace('/\s+/', ' ', $region->textContent) ?? '');
}

function assertSettlementRegionsDoNotNest(string $html): void
{
    $xpath = settlementContinuityXPath($html);
    $regions = $xpath->query('//*[@data-continuity-region]');

    expect($regions->length)->toBeGreaterThan(0);

    foreach ($regions as $region) {
        expect($xpath->query('.//*[@data-continuity-region]', $region)->length)->toBe(0);
    }
}

function assertSettlementRegionsExcludeCapture(string $html): void
{
    $xpath = settlementContinuityXPath($html);

    expect($xpath->query('//*[@data-continuity-region="settlement"][contains(., "Take payment")]')->length)->toBe(0)
        ->and($xpath->query('//*[contains(text(), "Take payment")]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('//*[@data-continuity-region="settlement"][contains(., "Platform is not connected.")]')->length)->toBe(0);
}

function settlementContinuityXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}
