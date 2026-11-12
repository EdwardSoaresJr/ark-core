<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\OperationalSheetPresenter;
use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Printing\KeyTagPrintGate;
use App\Ark\Operations\Printing\ShopPrintingSettings;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

/**
 * Single authoritative Print menu for the RO header and sticky footer.
 *
 * @phpstan-type PrintItem array{
 *     key: string,
 *     label: string,
 *     href: ?string,
 *     opens_in_new_tab: bool,
 *     is_print: bool,
 *     print_document: ?string,
 *     blocked_reason: ?string,
 *     cache_bust_estimate_pdf: bool
 * }
 */
final readonly class RepairOrderPrintMenuProjection
{
    /**
     * @param  array<string, mixed>|null  $financial
     * @return list<PrintItem>
     */
    public static function items(RepairOrder $repairOrder, ?array $financial = null, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can(ArkCapability::RepairOrdersView->value)) {
            return [];
        }

        $items = [];

        if (ShopPrintingSettings::isEnabled()) {
            $keyTagBlocked = KeyTagPrintGate::blockedReason($repairOrder);
            $items[] = self::item(
                key: 'key_tag',
                label: 'Print Key Tag',
                href: route('operations.repair-orders.print-key-tag', $repairOrder),
                isPrint: true,
                printDocument: 'key_tag',
                blockedReason: $keyTagBlocked,
            );
            $items[] = self::item(
                key: 'oil_sticker',
                label: 'Print Oil Sticker',
                href: route('operations.repair-orders.print-oil-change-sticker', $repairOrder),
                isPrint: true,
                printDocument: 'oil_change_sticker',
            );
        }

        $items[] = self::item(
            key: 'check_in_sheet',
            label: 'Check In sheet',
            href: route('operations.repair-orders.sheets.intake.pdf', $repairOrder),
            opensInNewTab: true,
        );

        $techOwners = app(OperationalSheetPresenter::class)->techSheetOwners($repairOrder);
        if ($techOwners->isEmpty()) {
            $items[] = self::item(
                key: 'tech_sheet',
                label: 'Tech sheet',
                href: route('operations.repair-orders.sheets.tech.pdf', $repairOrder),
                opensInNewTab: true,
            );
        } else {
            foreach ($techOwners as $techOwner) {
                $items[] = self::item(
                    key: 'tech_sheet_'.$techOwner->id,
                    label: 'Tech sheet · '.$techOwner->name,
                    href: route('operations.repair-orders.sheets.tech.pdf', [$repairOrder, 'owner' => $techOwner->id]),
                    opensInNewTab: true,
                );
            }
        }

        $estimatePdfUrl = route('operations.repair-orders.estimate.pdf', $repairOrder);
        $items[] = self::item(
            key: 'estimate_pdf',
            label: 'Estimate PDF',
            href: $estimatePdfUrl,
            opensInNewTab: true,
            cacheBustEstimatePdf: true,
        );

        if (InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0) {
            $items[] = self::item(
                key: 'inspection_pdf',
                label: 'Inspection PDF',
                href: route('operations.repair-orders.inspection.pdf', $repairOrder),
                opensInNewTab: true,
            );
        }

        $issuedInvoice = $financial['invoice'] ?? null;
        if ($issuedInvoice instanceof EstimateDocument) {
            $items[] = self::item(
                key: 'final_invoice',
                label: 'Final Invoice',
                href: route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $issuedInvoice]),
                opensInNewTab: true,
            );
        }

        return $items;
    }

    /**
     * @return PrintItem
     */
    private static function item(
        string $key,
        string $label,
        ?string $href,
        bool $opensInNewTab = false,
        bool $isPrint = false,
        ?string $printDocument = null,
        ?string $blockedReason = null,
        bool $cacheBustEstimatePdf = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'opens_in_new_tab' => $opensInNewTab,
            'is_print' => $isPrint,
            'print_document' => $printDocument,
            'blocked_reason' => $blockedReason,
            'cache_bust_estimate_pdf' => $cacheBustEstimatePdf,
        ];
    }
}
