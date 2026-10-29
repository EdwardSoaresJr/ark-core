<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot;
use Spatie\Browsershot\Exceptions\RemoteConnectionException;
use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Throwable;

class EstimateDocumentService
{
    private const PDF_APPROVAL_PRESENTATION_KEY = '_pdf_approval_presentation';

    private const PDF_CONTENT_FINGERPRINT_KEY = '_pdf_content_fingerprint';

    private const RENDER_LOCK_SECONDS = 120;

    private const RENDER_LOCK_WAIT_SECONDS = 90;

    public function __construct(
        private readonly EstimateSnapshotBuilder $snapshotBuilder,
        private readonly PdfRenderer $renderer,
        private readonly EstimateDocumentPdfSnapshot $pdfSnapshot,
        private readonly DocumentFooterPresenter $footerPresenter,
        private readonly CustomerFacingEstimateStatus $estimateStatus,
        private readonly EstimatePdfContentFingerprint $contentFingerprint,
    ) {}

    public function createOrRefresh(RepairOrder $repairOrder, ?User $user = null): EstimateDocument
    {
        return DB::transaction(function () use ($repairOrder, $user): EstimateDocument {
            $document = EstimateDocument::query()
                ->where('repair_order_id', $repairOrder->id)
                ->where('document_type', 'estimate')
                ->lockForUpdate()
                ->first();

            $attributes = [
                'snapshot_json' => $this->snapshotBuilder->build($repairOrder, $user),
                'status' => 'draft',
                'needs_pdf_refresh' => true,
                'refreshed_at' => now(),
            ];

            if ($document) {
                if ($this->isLegacyImportFrozen($document)) {
                    return $document;
                }

                $document->forceFill($attributes)->save();

                return $document;
            }

            return EstimateDocument::query()->create([
                'repair_order_id' => $repairOrder->id,
                'document_type' => 'estimate',
                'document_number' => 1,
                'pdf_path' => null,
                'generated_at' => null,
                'pdf_refreshed_at' => null,
                'created_by' => $user?->id,
                ...$attributes,
            ]);
        });
    }

    public function refreshSnapshot(EstimateDocument $document, ?User $user = null): EstimateDocument
    {
        $document->loadMissing('repairOrder');

        $document->forceFill([
            'snapshot_json' => $this->snapshotBuilder->build($document->repairOrder, $user),
            'status' => 'draft',
            'needs_pdf_refresh' => true,
            'refreshed_at' => now(),
        ])->save();

        return $document;
    }

    public function generatePdf(EstimateDocument $document, ?string $contentFingerprint = null): string
    {
        $path = $this->renderer->renderEstimate($document);

        // Issued final invoices are immutable evidence - never rewrite snapshot_json for PDF metadata.
        if ($document->isIssuedInvoice()) {
            $document->forceFill([
                'needs_pdf_refresh' => false,
                'pdf_refreshed_at' => now(),
            ])->save();

            return $path;
        }

        $snapshot = $document->snapshot_json ?? [];
        $snapshot[self::PDF_APPROVAL_PRESENTATION_KEY] = $this->approvalPresentationFingerprint($document);
        if ($contentFingerprint !== null) {
            $snapshot[self::PDF_CONTENT_FINGERPRINT_KEY] = $contentFingerprint;
        } else {
            unset($snapshot[self::PDF_CONTENT_FINGERPRINT_KEY]);
        }

        $document->forceFill([
            'needs_pdf_refresh' => false,
            'pdf_refreshed_at' => now(),
            'snapshot_json' => $snapshot,
        ])->save();

        return $path;
    }

    /**
     * Renders the final PDF for a closed repair order. A rendering failure leaves the
     * document failed so the next open renders it again; any other error is rethrown.
     */
    public function generateFinalPdf(EstimateDocument $document): void
    {
        try {
            $this->generatePdf($document);
        } catch (Throwable $exception) {
            if (! $this->isPdfRenderingFailure($exception)) {
                throw $exception;
            }

            Log::warning('Final PDF could not be generated when the repair order closed.', [
                'document_id' => $document->id,
                'repair_order_id' => $document->repair_order_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $document->forceFill([
                'status' => 'failed',
                'needs_pdf_refresh' => true,
            ])->save();
        }
    }

    public function hasFailedPdfForRepairOrder(RepairOrder $repairOrder): bool
    {
        return EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->where('status', 'failed')
            ->exists();
    }

    private function isPdfRenderingFailure(Throwable $exception): bool
    {
        return $exception instanceof ProcessRuntimeException
            || $exception instanceof PdfRuntimeUnavailable
            || $exception instanceof CouldNotTakeBrowsershot
            || $exception instanceof RemoteConnectionException
            || $exception instanceof UnsuccessfulResponse;
    }

    public function markDirtyForRepairOrder(RepairOrder $repairOrder): int
    {
        if ($repairOrder->estimateDocumentIsFrozen()) {
            return 0;
        }

        $documents = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->whereIn('status', ['draft', 'generating', 'generated', 'failed'])
            ->get();

        foreach ($documents as $document) {
            if ($this->isLegacyImportFrozen($document)) {
                continue;
            }

            $this->refreshSnapshot($document);
        }

        return $documents->count();
    }

    /**
     * Living estimates stay aligned with repair order and shop settings until closed.
     */
    public function resolveForRepairOrder(RepairOrder $repairOrder, ?User $user = null): EstimateDocument
    {
        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->first();

        if ($document === null) {
            $document = $this->createOrRefresh($repairOrder, $user);
        }

        return $this->ensureCurrent($document, $user);
    }

    public function hasViewablePdf(EstimateDocument $document): bool
    {
        if ($this->isFrozenInvoice($document)) {
            return filled($document->pdf_path) && Storage::disk('local')->exists($document->pdf_path);
        }

        if (! $this->pdfExists($document)) {
            return false;
        }

        if ($this->isLivingEstimate($document) && $document->needs_pdf_refresh) {
            return false;
        }

        return true;
    }

    public function ensureCurrent(EstimateDocument $document, ?User $user = null): EstimateDocument
    {
        $document->loadMissing('repairOrder');

        if ($this->isLegacyImportFrozen($document)) {
            return $this->ensurePdfGenerated($document);
        }

        if ($document->isInvoice()) {
            if ($this->isFrozenInvoice($document)) {
                return $document;
            }

            return $this->whileRendering($document, fn (): EstimateDocument => $this->ensurePdfGenerated($document->refresh()));
        }

        if ($document->repairOrder->estimateDocumentIsFrozen()) {
            return $this->whileRendering($document, fn (): EstimateDocument => $this->ensurePdfGenerated($document->refresh()));
        }

        // A living estimate whose stored PDF was rendered from the same content is served as it is:
        // no snapshot rewrite, no Chromium. Any change (lines, approvals, shop settings, templates) shows up in the fingerprint.
        $fingerprint = $this->contentFingerprint->for($document);
        if ($this->livingPdfMatches($document, $fingerprint)) {
            return $document;
        }

        return $this->whileRendering($document, function () use ($document, $user): EstimateDocument {
            $document->refresh();
            $fingerprint = $this->contentFingerprint->for($document);
            if ($this->livingPdfMatches($document, $fingerprint)) {
                return $document;
            }

            $document = $this->createOrRefresh($document->repairOrder, $user);

            return $this->ensurePdfGenerated($document, $fingerprint);
        });
    }

    private function livingPdfMatches(EstimateDocument $document, string $fingerprint): bool
    {
        return $document->status === 'generated'
            && ! $document->needs_pdf_refresh
            && hash_equals((string) data_get($document->snapshot_json, self::PDF_CONTENT_FINGERPRINT_KEY, ''), $fingerprint)
            && $this->pdfExists($document);
    }

    /**
     * One render per document at a time: a concurrent request waits, then finds the PDF current.
     *
     * @param  callable(): EstimateDocument  $render
     */
    private function whileRendering(EstimateDocument $document, callable $render): EstimateDocument
    {
        $key = 'estimate-document-pdf:'.$document->getConnection()->getDatabaseName().':'.$document->id;

        try {
            return Cache::lock($key, self::RENDER_LOCK_SECONDS)->block(self::RENDER_LOCK_WAIT_SECONDS, $render);
        } catch (LockTimeoutException) {
            return $render();
        }
    }

    public function attachablePdfForRepairOrder(RepairOrder $repairOrder): ?EstimateDocument
    {
        $document = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->first();

        if ($document === null || ! $this->pdfExists($document) || $this->finalPdfFailed($document)) {
            return null;
        }

        return $document;
    }

    /** The file on disk predates the close when the closed repair order's final PDF failed to render. */
    public function finalPdfFailed(EstimateDocument $document): bool
    {
        $document->loadMissing('repairOrder');

        return ! $document->isInvoice()
            && $document->status === 'failed'
            && $document->repairOrder?->estimateDocumentIsFrozen() === true;
    }

    /**
     * An invoice on a closed repair order is the record the customer was given: its stored PDF is
     * served as it is and never re-rendered when someone opens it.
     */
    public function isFrozenInvoice(EstimateDocument $document): bool
    {
        $document->loadMissing('repairOrder');

        return $document->isInvoice() && $document->repairOrder?->estimateDocumentIsFrozen() === true;
    }

    private function ensurePdfGenerated(EstimateDocument $document, ?string $contentFingerprint = null): EstimateDocument
    {
        $document->loadMissing('repairOrder');

        if ($this->isFrozenInvoice($document)) {
            return $document;
        }

        $isLivingEstimate = $this->isLivingEstimate($document);
        $retryingFailedFinalPdf = false;

        if ($isLivingEstimate) {
            $shouldGenerate = true;
        } elseif (! $document->isInvoice() && $document->repairOrder->estimateDocumentIsFrozen()) {
            $retryingFailedFinalPdf = $document->status === 'failed';
            $shouldGenerate = $retryingFailedFinalPdf
                || ! $this->pdfExists($document)
                || $this->frozenEstimateApprovalPresentationDrifted($document);
        } else {
            $shouldGenerate = $document->needs_pdf_refresh
                || ! $this->pdfExists($document);
        }

        if ($document->isInvoice() && ! DocumentPdfPath::matches($document)) {
            $shouldGenerate = true;
        }

        if ($shouldGenerate) {
            try {
                $this->generatePdf($document, $isLivingEstimate ? $contentFingerprint : null);
            } catch (Throwable $exception) {
                Log::warning('Estimate PDF could not be refreshed for repair order view.', [
                    'document_id' => $document->id,
                    'repair_order_id' => $document->repair_order_id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $attributes = [
                    'needs_pdf_refresh' => true,
                    'status' => 'failed',
                ];

                if ($isLivingEstimate && $this->pdfExists($document)) {
                    Storage::disk('local')->delete($document->pdf_path);
                    $attributes['pdf_path'] = null;
                } elseif ($this->pdfExists($document) && ! $document->isInvoice() && ! $retryingFailedFinalPdf) {
                    $attributes['status'] = 'generated';
                }

                $document->forceFill($attributes)->save();
            }
        }

        return $document->refresh();
    }

    private function isLivingEstimate(EstimateDocument $document): bool
    {
        if ($document->isInvoice() || $this->isLegacyImportFrozen($document)) {
            return false;
        }

        $document->loadMissing('repairOrder');

        return $document->repairOrder?->estimateDocumentIsFrozen() === false;
    }

    public function refreshOpenDocumentsForShopSettingsChange(): int
    {
        $refreshed = 0;

        EstimateDocument::query()
            ->where('document_type', 'estimate')
            ->whereHas('repairOrder', fn ($query) => $query->where('status', '!=', RepairOrderStatus::Closed->value))
            ->with('repairOrder')
            ->get()
            ->each(function (EstimateDocument $document) use (&$refreshed): void {
                if ($this->isLegacyImportFrozen($document)) {
                    return;
                }

                $this->refreshSnapshot($document);
                $refreshed++;
            });

        return $refreshed;
    }

    private function pdfExists(EstimateDocument $document): bool
    {
        return DocumentPdfPath::matches($document)
            && Storage::disk('local')->exists($document->pdf_path);
    }

    private function isLegacyImportFrozen(EstimateDocument $document): bool
    {
        if (data_get($document->snapshot_json, 'schema_version') === 'legacy_import') {
            return true;
        }

        $document->loadMissing('repairOrder');

        return $document->repairOrder?->estimateDocumentIsFrozen() === true;
    }

    private function frozenEstimateApprovalPresentationDrifted(EstimateDocument $document): bool
    {
        $stored = data_get($document->snapshot_json, self::PDF_APPROVAL_PRESENTATION_KEY);

        if (! is_string($stored) || $stored === '') {
            return true;
        }

        return $stored !== $this->approvalPresentationFingerprint($document);
    }

    private function approvalPresentationFingerprint(EstimateDocument $document): string
    {
        $snapshot = $this->pdfSnapshot->resolve($document);
        $footer = $this->footerPresenter->present($snapshot);

        return implode('|', [
            (string) ($footer['approval']['status'] ?? 'none'),
            (string) ($footer['approval']['status_label'] ?? 'none'),
            $this->estimateStatus->labelForSnapshot($snapshot),
        ]);
    }
}
