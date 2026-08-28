@php
    $showShopPrintActions = App\Ark\Operations\Printing\ShopPrintingSettings::isEnabled();
    $issuedInvoice = ($financial ?? null)['invoice'] ?? null;
    $estimatePdfUrl = route('operations.repair-orders.estimate.pdf', $repairOrder);
    $keyTagUrl = route('operations.repair-orders.print-key-tag', $repairOrder);
    $oilStickerUrl = route('operations.repair-orders.print-oil-change-sticker', $repairOrder);
    $hasInspectionFindings = App\Ark\Operations\Inspections\InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0;
    $inspectionPdfUrl = route('operations.repair-orders.inspection.pdf', $repairOrder);
@endphp

<div
    class="ops-review-print-menu ops-comms-menu"
    x-data="arkReviewPrintMenu()"
    x-ref="printMenuRoot"
    @keydown.escape.window="closeMenu()"
>
    <button
        type="button"
        x-ref="printMenuTrigger"
        class="ops-review-action shrink-0 ops-review-print-menu__trigger"
        @click.stop="toggleMenu()"
        :aria-expanded="menuOpen"
        aria-haspopup="menu"
    >
        <span>PRINT</span>
        <span class="ops-ro-mode-control__caret" aria-hidden="true">▼</span>
    </button>

    <template x-teleport="body">
        <div
            x-show="menuOpen"
            x-cloak
            x-ref="printMenuPanel"
            :style="menuStyle"
            class="ops-comms-menu__panel ops-comms-menu__panel--floating"
            role="menu"
            @click.stop
        >
            @if ($showShopPrintActions)
                <button
                    type="button"
                    role="menuitem"
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu(); window.arkPrintDocument(window.ARK_PRINTERS.keyTag, @js($keyTagUrl), $event.currentTarget, { document: 'key_tag', resolvePrinter: true })"
                >
                    Print Key Tag
                </button>

                <button
                    type="button"
                    role="menuitem"
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu(); window.arkPrintDocument(window.ARK_PRINTERS.oilSticker, @js($oilStickerUrl), $event.currentTarget, { document: 'oil_change_sticker', resolvePrinter: true })"
                >
                    Print Oil Sticker
                </button>
            @endif

            <a
                href="{{ route('operations.repair-orders.sheets.intake.pdf', $repairOrder) }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="ops-comms-menu__item"
                @click.stop="closeMenu()"
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
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu()"
                >
                    Tech sheet · {{ $techOwner->name }}
                </a>
            @empty
                <a
                    href="{{ route('operations.repair-orders.sheets.tech.pdf', $repairOrder) }}"
                    target="_blank"
                    rel="noopener"
                    role="menuitem"
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu()"
                >
                    Tech sheet
                </a>
            @endforelse

            <a
                href="{{ $estimatePdfUrl }}"
                target="_blank"
                rel="noopener"
                role="menuitem"
                class="ops-comms-menu__item"
                @click.stop="closeMenu(); $event.currentTarget.href = @js($estimatePdfUrl) + '?t=' + Date.now()"
            >
                Estimate PDF
            </a>

            @if ($hasInspectionFindings)
                <a
                    href="{{ $inspectionPdfUrl }}"
                    target="_blank"
                    rel="noopener"
                    role="menuitem"
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu()"
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
                    class="ops-comms-menu__item"
                    @click.stop="closeMenu()"
                >
                    Final Invoice
                </a>
            @endif
        </div>
    </template>
</div>
