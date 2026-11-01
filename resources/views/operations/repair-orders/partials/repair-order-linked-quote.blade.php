@php
    $linkedQuotePanel = app(\App\Ark\Operations\Parts\LinkedPartsQuotes::class)->present($repairOrder);
    $linkedQuoteConfig = [
        'showUrl' => route('operations.repair-orders.linked-parts-quotes.show', $repairOrder),
        'refreshUrl' => route('operations.repair-orders.linked-parts-quotes.refresh', $repairOrder),
        'candidatesUrl' => route('operations.repair-orders.linked-parts-quotes.candidates', $repairOrder),
        'linkUrl' => route('operations.repair-orders.linked-parts-quotes.store', $repairOrder),
        'currentUrl' => route('operations.repair-orders.linked-parts-quotes.current', $repairOrder),
        'csrf' => csrf_token(),
        'panel' => $linkedQuotePanel,
    ];
@endphp

<div
    data-linked-parts-quote
    class="card card-sm mb-3"
    x-data="arkLinkedPartsQuote(@js($linkedQuoteConfig))"
>
    <div class="card-body d-flex align-items-center gap-3 py-2">
        <svg class="icon text-secondary flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.2 11h11.3l2-7H7"></path>
            <circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"></circle>
            <circle cx="18" cy="20" r="1.4" fill="currentColor" stroke="none"></circle>
        </svg>
        <div class="flex-fill" style="min-width: 0">
            <div class="fw-medium text-truncate" x-text="summaryTitle()"></div>
            <div class="small text-secondary text-truncate" x-text="summaryDetail()"></div>
        </div>
        <button
            type="button"
            class="btn btn-sm flex-shrink-0"
            @click="openManage($event)"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="dialog"
        >Manage</button>
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            data-linked-parts-quote-dialog
            @keydown.escape.window="onEscape($event)"
            @keydown.tab="trapFocus($event)"
        >
            <div class="modal-backdrop show"></div>
            <div
                class="modal d-block"
                role="dialog"
                aria-modal="true"
                aria-labelledby="linked-parts-quote-title"
                @click.self="close()"
            >
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" @click.self="close()">
                    <div class="modal-content" x-ref="dialog" tabindex="-1">
                        <div class="modal-header">
                            <div class="overflow-hidden">
                                <h2 id="linked-parts-quote-title" class="modal-title" x-text="summaryTitle()"></h2>
                                <div class="small text-secondary" x-text="summaryDetail()"></div>
                            </div>
                            <button type="button" class="btn-close" aria-label="Close" @click="close()"></button>
                        </div>

                        <div class="modal-body">
                            <p class="small text-secondary" x-show="panel?.open_note" x-cloak x-text="panel.open_note"></p>
                            <p class="small" x-show="notice" x-cloak x-text="notice" role="status"></p>
                            <p class="small text-orange" x-show="cached && panel?.current" x-cloak>Showing the last saved quote. This check did not verify it.</p>
                            <p class="small text-secondary" x-show="!cached && panel?.current?.confirmed" x-cloak>Verified with the catalog.</p>
                            <p class="small text-danger" x-show="panel?.current?.message" x-cloak x-text="panel.current.message" role="alert"></p>

                            <div class="list-group mb-3" x-show="panel?.current?.lines?.length" x-cloak>
                                <template x-for="line in (panel?.current?.lines || [])" :key="(line.part_number || '') + line.description">
                                    <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
                                        <span x-text="quantity(line.quantity) + ' × ' + line.description"></span>
                                        <span class="fw-medium text-secondary" x-text="line.part_number"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="btn-list">
                                <button type="button" class="btn btn-sm" :disabled="loading || !panel?.current" @click="refresh()">Refresh quote</button>
                                <button type="button" class="btn btn-sm" :disabled="loading" @click="choose()">Change linked cart</button>
                            </div>

                            <div class="mt-3" x-show="choosing" x-cloak>
                                <p class="small fw-medium mb-2">Choose a quote. This does not import its parts.</p>
                                <div class="list-group">
                                    <template x-for="candidate in candidates" :key="candidate.external_id">
                                        <button type="button" class="list-group-item list-group-item-action small" :disabled="loading" @click="link(candidate.external_id)">
                                            <span class="d-block fw-bold" x-text="(candidate.supplier || 'Quote') + (statusLabel(candidate.status) ? (' · ' + statusLabel(candidate.status)) : '')"></span>
                                            <span class="d-block text-secondary" x-text="quoteMeta(candidate)"></span>
                                        </button>
                                    </template>
                                </div>
                                <p class="small text-secondary mt-2" x-show="candidates.length === 0 && !loading" x-cloak>No matching quotes were returned for this repair order.</p>
                            </div>

                            <div class="border-top pt-3 mt-3">
                                <div class="subheader mb-2">Quote history</div>
                                <div class="list-group">
                                    <template x-for="row in (panel?.history || [])" :key="row.id">
                                        <div class="list-group-item d-flex align-items-center justify-content-between gap-3 small">
                                            <div>
                                                <div class="fw-bold" x-text="(row.supplier || 'Quote') + (statusLabel(row.status) ? (' · ' + statusLabel(row.status)) : '')"></div>
                                                <div class="text-secondary" x-text="quoteMeta(row)"></div>
                                            </div>
                                            <button type="button" class="btn btn-sm" x-show="!row.is_current" :disabled="loading" @click="makeCurrent(row.id)">Make current</button>
                                            <span class="badge bg-green-lt" x-show="row.is_current" x-cloak>Current</span>
                                        </div>
                                    </template>
                                </div>
                                <p class="small text-secondary mt-2 mb-0" x-show="!(panel?.history || []).length">No earlier quotes are saved for this repair order.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
