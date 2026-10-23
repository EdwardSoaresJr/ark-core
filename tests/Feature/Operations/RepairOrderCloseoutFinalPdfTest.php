<?php

use App\Ark\Operations\Documents\DocumentPdfPath;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateDocumentService;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleController;
use App\Ark\Operations\RepairOrders\RepairOrderLifecycleTransition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function paidRepairOrderReadyToClose(): RepairOrder
{
    test()->seed(ArkAuthorizationSeeder::class);
    test()->seed(RepairOrderStatusCatalogSeeder::class);
    test()->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $repairOrder = financialCloseoutRepairOrder();
    issueFinalInvoiceFor($repairOrder);
    payRepairOrderInFull($repairOrder);

    return $repairOrder->fresh();
}

/**
 * @return array<int, array<string, mixed>>
 */
function closeoutLedgerRows(RepairOrder $repairOrder): array
{
    return RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->orderBy('id')
        ->get()
        ->map(fn (RepairOrderLedgerEntry $entry): array => $entry->getAttributes())
        ->all();
}

function closeoutLifecycleEvents(): int
{
    return OperationalEvent::query()
        ->where('event_name', OperationalEventName::RepairOrderLifecycleChanged->value)
        ->count();
}

function closeoutEstimateDocument(RepairOrder $repairOrder): ?EstimateDocument
{
    return EstimateDocument::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('document_type', 'estimate')
        ->first();
}

test('closing a paid repair order commits when the final pdf fails to render', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $ledgerBefore = closeoutLedgerRows($repairOrder);
    $paymentStatusBefore = $repairOrder->payment_status;
    $paidAtBefore = $repairOrder->paid_at;

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);
    Log::spy();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'RO closed as '.$repairOrder->fresh()->statusDisplayLabel().'. '.RepairOrderLifecycleController::FINAL_PDF_FAILED);

    $closed = $repairOrder->fresh();
    $document = closeoutEstimateDocument($closed);

    expect($closed->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($closed->close_variant_key)->toBe('paid')
        ->and($closed->closed_at)->not->toBeNull()
        ->and($closed->posted_at)->not->toBeNull()
        ->and($closed->payment_status)->toBe($paymentStatusBefore)
        ->and($closed->paid_at?->toIso8601String())->toBe($paidAtBefore?->toIso8601String())
        ->and($closed->balanceDue()->balanceDueCents)->toBe(0)
        ->and($closed->balanceDue()->hasIssuedInvoice)->toBeTrue()
        ->and(closeoutLedgerRows($closed))->toBe($ledgerBefore)
        ->and(closeoutLifecycleEvents())->toBe(1)
        ->and(OperationalEvent::query()->where('event_name', OperationalEventName::RepairOrderPosted->value)->count())->toBe(1)
        ->and($document)->not->toBeNull()
        ->and($document->status)->toBe('failed')
        ->and($document->needs_pdf_refresh)->toBeTrue();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Final PDF could not be generated when the repair order closed.'
            && $context['repair_order_id'] === $closed->id
            && $context['exception'] === ProcessFailedException::class)
        ->once();
});

test('opening the estimate pdf after a failed closeout render generates the final pdf', function () {
    Storage::fake('local');
    CountingCloseoutPdfRenderer::$renders = 0;
    $repairOrder = paidRepairOrderReadyToClose();

    $this->app->bind(PdfRenderer::class, CountingCloseoutPdfRenderer::class);
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();
    $stalePdf = Storage::disk('local')->get(closeoutEstimateDocument($repairOrder)->pdf_path);

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);
    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(closeoutEstimateDocument($repairOrder)->status)->toBe('failed');

    $this->app->bind(PdfRenderer::class, CountingCloseoutPdfRenderer::class);
    $rendersBeforeOpen = CountingCloseoutPdfRenderer::$renders;

    $response = $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();
    $document = closeoutEstimateDocument($repairOrder);

    expect(CountingCloseoutPdfRenderer::$renders)->toBe($rendersBeforeOpen + 1)
        ->and($response->getContent())->not->toBe($stalePdf)
        ->and($document->status)->toBe('generated')
        ->and($document->needs_pdf_refresh)->toBeFalse();

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    expect(CountingCloseoutPdfRenderer::$renders)->toBe($rendersBeforeOpen + 1);
});

test('a failed retry keeps the closed repair order final pdf marked failed', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();

    $this->app->bind(PdfRenderer::class, CountingCloseoutPdfRenderer::class);
    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);
    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect();

    $this->get(route('operations.repair-orders.estimate.pdf', $repairOrder))->assertOk();

    $document = closeoutEstimateDocument($repairOrder);

    expect($document->status)->toBe('failed')
        ->and($document->needs_pdf_refresh)->toBeTrue();
});

test('closing a paid repair order with a working renderer is unchanged', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $ledgerBefore = closeoutLedgerRows($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'RO closed as '.$repairOrder->fresh()->statusDisplayLabel().'.');

    $closed = $repairOrder->fresh();
    $document = closeoutEstimateDocument($closed);

    expect($closed->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($closed->close_variant_key)->toBe('paid')
        ->and($closed->posted_at)->not->toBeNull()
        ->and(closeoutLedgerRows($closed))->toBe($ledgerBefore)
        ->and(closeoutLifecycleEvents())->toBe(1)
        ->and($document->status)->toBe('generated')
        ->and($document->needs_pdf_refresh)->toBeFalse()
        ->and(Storage::disk('local')->exists($document->pdf_path))->toBeTrue();
});

test('a non rendering error from the final pdf still fails the close and rolls it back', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $before = $repairOrder->only(['status', 'close_variant_key', 'closed_at', 'posted_at', 'payment_status', 'paid_at']);
    $ledgerBefore = closeoutLedgerRows($repairOrder);

    $this->app->bind(PdfRenderer::class, LogicFailingCloseoutPdfRenderer::class);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:paid',
    ])->assertServerError();

    $after = $repairOrder->fresh();

    expect($after->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue()
        ->and($after->only(['status', 'close_variant_key', 'closed_at', 'posted_at', 'payment_status', 'paid_at']))->toEqual($before)
        ->and(closeoutLedgerRows($after))->toBe($ledgerBefore)
        ->and(closeoutLifecycleEvents())->toBe(0);
});

test('a close outside a request lock commits all of its state or none of it', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $before = $repairOrder->only(['status', 'close_variant_key', 'closed_at', 'posted_at', 'payment_status', 'paid_at']);
    $ledgerBefore = closeoutLedgerRows($repairOrder);

    $this->app->bind(OperationalEventRecorder::class, fn () => new class extends OperationalEventRecorder
    {
        public function record(
            OperationalEventName|string $eventName,
            Model|string $aggregate,
            ?int $aggregateId = null,
            ?User $actor = null,
            array $payload = [],
        ): OperationalEvent {
            throw new LogicException('Event store unavailable.');
        }
    });

    expect(fn () => app(RepairOrderLifecycleTransition::class)->move($repairOrder, RepairOrderStatus::Closed, auth()->user(), 'paid'))
        ->toThrow(LogicException::class, 'Event store unavailable.');

    $after = $repairOrder->fresh();

    expect($after->status->is(RepairOrderStatus::ReadyPickup))->toBeTrue()
        ->and($after->only(['status', 'close_variant_key', 'closed_at', 'posted_at', 'payment_status', 'paid_at']))->toEqual($before)
        ->and(closeoutLedgerRows($after))->toBe($ledgerBefore)
        ->and(closeoutEstimateDocument($after))->toBeNull();
});

test('a close outside a request lock still commits when the final pdf fails to render', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);

    $closed = app(RepairOrderLifecycleTransition::class)->move($repairOrder, RepairOrderStatus::Closed, auth()->user(), 'paid');

    expect($closed->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($closed->posted_at)->not->toBeNull()
        ->and(app(EstimateDocumentService::class)->hasFailedPdfForRepairOrder($closed))->toBeTrue();
});

test('issuing an estimate pdf that fails to render keeps its existing message', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $repairOrder->forceFill(['status' => RepairOrderStatus::Estimate])->save();

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);

    $this->post(route('operations.repair-orders.estimate-documents.store', $repairOrder))
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('status', 'Estimate snapshot saved, but PDF generation failed. Check Chromium runtime support.');

    expect(closeoutEstimateDocument($repairOrder)->needs_pdf_refresh)->toBeTrue();
});

test('closing a paid repair order on mobile returns the workspace and a warning when the final pdf fails', function () {
    Storage::fake('local');
    $repairOrder = paidRepairOrderReadyToClose();
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->app->bind(PdfRenderer::class, ProcessFailingCloseoutPdfRenderer::class);

    $this->withToken($advisor->createToken('test')->plainTextToken)
        ->patchJson('/api/mobile/repair-orders/'.$repairOrder->repair_order_id.'/status', [
            'status' => 'closed:paid',
        ])
        ->assertOk()
        ->assertJsonPath('warning', 'The final estimate PDF could not be generated. Open the estimate PDF to try again.')
        ->assertJsonStructure(['workspace']);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->fresh()->posted_at)->not->toBeNull();
});

class ProcessFailingCloseoutPdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $process = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "Chromium crashed"); exit(1);']);
        $process->run();

        throw new ProcessFailedException($process);
    }
}

class LogicFailingCloseoutPdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        throw new LogicException('Document template is broken.');
    }
}

class CountingCloseoutPdfRenderer implements PdfRenderer
{
    public static int $renders = 0;

    public function renderEstimate(EstimateDocument $document): string
    {
        self::$renders++;

        $path = DocumentPdfPath::for($document);
        Storage::disk('local')->put($path, '%PDF-1.4 render '.self::$renders);
        $document->forceFill([
            'pdf_path' => $path,
            'generated_at' => $document->generated_at ?? now(),
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
            'status' => $document->isInvoice() ? $document->status : 'generated',
        ])->save();

        return $path;
    }
}
