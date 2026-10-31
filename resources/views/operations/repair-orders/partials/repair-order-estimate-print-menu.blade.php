@php
    $showShopPrintActions = App\Ark\Operations\Printing\ShopPrintingSettings::isEnabled();
    $issuedInvoice = ($financial ?? null)['invoice'] ?? null;
    $estimatePdfUrl = route('operations.repair-orders.estimate.pdf', $repairOrder);
    $keyTagUrl = route('operations.repair-orders.print-key-tag', $repairOrder);
    $keyTagBlocked = \App\Ark\Operations\Printing\KeyTagPrintGate::blockedReason($repairOrder);
    $oilStickerUrl = route('operations.repair-orders.print-oil-change-sticker', $repairOrder);
    $hasInspectionFindings = App\Ark\Operations\Inspections\InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0;
    $inspectionPdfUrl = route('operations.repair-orders.inspection.pdf', $repairOrder);
@endphp

<div class="dropdown" data-ro-print-menu>
    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        Print
    </button>
    <div class="dropdown-menu dropdown-menu-end" role="menu">
        @if ($showShopPrintActions)
            @if (filled($keyTagBlocked))
                <button
                    type="button"
                    role="menuitem"
                    class="dropdown-item"
                    @click="window.alert(@js($keyTagBlocked))"
                >
                    Print Key Tag
                    <span class="d-block small text-secondary">{{ $keyTagBlocked }}</span>
                </button>
            @else
                <button
                    type="button"
                    role="menuitem"
                    class="dropdown-item"
                    @click="window.arkPrintDocument(window.ARK_PRINTERS.keyTag, @js($keyTagUrl), $event.currentTarget, { document: 'key_tag', resolvePrinter: true })"
                >
                    Print Key Tag
                </button>
            @endif

            <button
                type="button"
                role="menuitem"
                class="dropdown-item"
                @click="window.arkPrintDocument(window.ARK_PRINTERS.oilSticker, @js($oilStickerUrl), $event.currentTarget, { document: 'oil_change_sticker', resolvePrinter: true })"
            >
                Print Oil Sticker
            </button>
        @endif

        <a
            href="{{ route('operations.repair-orders.sheets.intake.pdf', $repairOrder) }}"
            target="_blank"
            rel="noopener"
            role="menuitem"
            class="dropdown-item"
        >
            Check In sheet
        </a>

        @php
            $techSheetOwners = app(\App\Ark\Operations\Documents\OperationalSheetPresenter::class)
                ->techSheetOwners($repairOrder);
        @endphp
        @forelse ($techSheetOwners as $techOwner)
            <a
                href="{{ route('operations.repair-orders.sheets.tech.pdf', [$repairOrder, 'owner' => $techOwner->id]) }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="dropdown-item"
            >
                Tech sheet · {{ $techOwner->name }}
            </a>
        @empty
            <a
                href="{{ route('operations.repair-orders.sheets.tech.pdf', $repairOrder) }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="dropdown-item"
            >
                Tech sheet
            </a>
        @endforelse

        <a
            href="{{ $estimatePdfUrl }}"
            target="_blank"
            rel="noopener"
            role="menuitem"
            class="dropdown-item"
            @click="$event.currentTarget.href = @js($estimatePdfUrl) + '?t=' + Date.now()"
        >
            Estimate PDF
        </a>

        @if ($hasInspectionFindings)
            <a
                href="{{ $inspectionPdfUrl }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="dropdown-item"
            >
                Inspection PDF
            </a>
        @endif

        @if ($issuedInvoice instanceof App\Ark\Operations\Documents\EstimateDocument)
            <a
                href="{{ route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $issuedInvoice]) }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="dropdown-item"
            >
                Final Invoice
            </a>
        @endif
    </div>
</div>
