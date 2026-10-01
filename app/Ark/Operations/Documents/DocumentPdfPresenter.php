<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Portal\RepairPortalAdvertisementProjection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use Brick\Money\Money;

final class DocumentPdfPresenter
{
    public function __construct(
        private readonly EstimateSnapshotBuilder $snapshotBuilder,
        private readonly DocumentDisclaimerComposer $disclaimerComposer,
        private readonly DocumentFooterPresenter $footerPresenter,
        private readonly CustomerFacingDocumentBoundary $customerFacingBoundary,
        private readonly RepairPortalAdvertisementProjection $repairPortalAdvertisement,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function prepare(array $snapshot): array
    {
        $snapshot = $this->withPresentationContext($snapshot);
        $snapshot = $this->withShopAndSettings($snapshot);
        $snapshot = $this->withDocumentDisclaimers($snapshot);
        $snapshot = $this->withDocumentFooter($snapshot);
        $snapshot = $this->withRepairPortalAdvertisement($snapshot);
        $snapshot = $this->withFormattedTotals($snapshot);
        $snapshot = $this->withFormattedConcerns($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function prepareForCustomer(array $snapshot): array
    {
        return $this->customerFacingBoundary->sanitize($this->prepare($snapshot));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withPresentationContext(array $snapshot): array
    {
        $documentType = FinancialDocumentType::tryFrom((string) data_get($snapshot, 'document_type', 'estimate'))
            ?? FinancialDocumentType::Estimate;

        $snapshot['document_type'] = $documentType->value;
        $snapshot['pdf_document_label'] = $documentType->label();
        $snapshot['pdf_work_column_label'] = $documentType === FinancialDocumentType::Invoice
            ? 'Approved Work'
            : 'Estimated Work';

        if (isset($snapshot['repair_order']) && is_array($snapshot['repair_order'])) {
            unset(
                $snapshot['repair_order']['future_work_count'],
                $snapshot['repair_order']['future_work_subtotal'],
                $snapshot['repair_order']['future_work_subtotal_cents'],
                $snapshot['repair_order']['future_work_summary'],
                $snapshot['repair_order']['future_work_next_action'],
            );
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withShopAndSettings(array $snapshot): array
    {
        $layers = $this->snapshotBuilder->presentationLayers();

        $snapshot['shop'] = array_replace($layers['shop'], $snapshot['shop'] ?? []);

        if (! isset($snapshot['settings']) || $snapshot['settings'] === []) {
            $snapshot['settings'] = $layers['settings'];
        }

        if (! isset($snapshot['intake']['concern_summary']) && isset($snapshot['repair_order']['concern_summary'])) {
            $snapshot['intake'] = [
                'concern_summary' => $snapshot['repair_order']['concern_summary'],
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withDocumentDisclaimers(array $snapshot): array
    {
        $snapshot['documents'] = $this->disclaimerComposer->resolveFromSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withDocumentFooter(array $snapshot): array
    {
        $snapshot['document_footer'] = $this->footerPresenter->present($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withRepairPortalAdvertisement(array $snapshot): array
    {
        $repairOrderId = (int) data_get($snapshot, 'repair_order.id', 0);
        if ($repairOrderId <= 0) {
            return $snapshot;
        }

        $repairOrder = RepairOrder::query()->find($repairOrderId);
        if ($repairOrder === null) {
            return $snapshot;
        }

        $snapshot['repair_portal'] = $this->repairPortalAdvertisement->forRepairOrder($repairOrder);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withFormattedTotals(array $snapshot): array
    {
        $totals = $snapshot['totals'] ?? [];

        if (! is_array($totals)) {
            return $snapshot;
        }

        $snapshot['totals'] = array_replace([
            'labor' => $this->formatCents((int) ($totals['labor_cents'] ?? 0)),
            'parts' => $this->formatCents((int) ($totals['parts_cents'] ?? 0)),
            'fees' => $this->formatCents((int) ($totals['fees_cents'] ?? 0)),
            'tax' => $this->formatCents((int) ($totals['tax_cents'] ?? 0)),
            'total' => $this->formatCents((int) ($totals['total_cents'] ?? 0)),
        ], $totals);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withFormattedConcerns(array $snapshot): array
    {
        $concerns = $snapshot['concerns'] ?? [];

        if (! is_array($concerns)) {
            return $snapshot;
        }

        foreach ($concerns as $index => $concern) {
            if (! is_array($concern)) {
                continue;
            }

            $disposition = RepairOrderConcernDisposition::tryFrom((string) ($concern['disposition'] ?? ''));
            $concerns[$index]['disposition_label'] = $disposition?->scopeHeaderLabel()
                ?? $concern['disposition_label']
                ?? 'Pending';

            if (! isset($concern['subtotal']) && isset($concern['subtotal_cents'])) {
                $concerns[$index]['subtotal'] = $this->formatCents((int) $concern['subtotal_cents']);
            }

            $lines = $concern['lines'] ?? [];

            if (! is_array($lines)) {
                continue;
            }

            foreach ($lines as $lineIndex => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $lines[$lineIndex] = $this->withFormattedLine($line);
            }

            $concerns[$index]['lines'] = $lines;
        }

        $snapshot['concerns'] = $concerns;

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function withFormattedLine(array $line): array
    {
        $type = RepairOrderLineType::tryFrom((string) ($line['type'] ?? ''));

        $line['type_label'] = $line['type_label'] ?? $type?->documentLabel() ?? strtoupper((string) ($line['type'] ?? 'line'));
        $line['unit_price'] = $line['unit_price'] ?? $this->formatCents((int) ($line['unit_price_cents'] ?? 0));
        $line['subtotal'] = $line['subtotal'] ?? $this->formatCents((int) ($line['subtotal_cents'] ?? 0));
        $line['tax'] = $line['tax'] ?? $this->formatCents((int) ($line['tax_cents'] ?? 0));
        $line['shop_fee'] = $line['shop_fee'] ?? $this->formatCents((int) ($line['shop_fee_cents'] ?? 0));
        $line['total'] = $line['total'] ?? $this->formatCents((int) ($line['total_cents'] ?? 0));

        return $line;
    }

    private function formatCents(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')
            ->getAmount()
            ->toScale(2)
            ->__toString();
    }
}
