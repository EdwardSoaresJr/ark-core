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
    class="mb-3 flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2"
    x-data="arkLinkedPartsQuote(@js($linkedQuoteConfig))"
>
    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.2 11h11.3l2-7H7"></path>
        <circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"></circle>
        <circle cx="18" cy="20" r="1.4" fill="currentColor" stroke="none"></circle>
    </svg>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900" x-text="summaryTitle()"></p>
        <p class="truncate text-xs text-slate-500" x-text="summaryDetail()"></p>
    </div>
    <button
        type="button"
        class="shrink-0 text-sm font-semibold text-slate-800"
        @click="openManage($event)"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="dialog"
    >Manage</button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="ops-workspace-modal"
            role="dialog"
            aria-modal="true"
            data-linked-parts-quote-dialog
            aria-labelledby="linked-parts-quote-title"
            @keydown.escape.window="onEscape($event)"
            @keydown.tab="trapFocus($event)"
        >
            <button type="button" class="ops-workspace-modal__backdrop" aria-label="Close" tabindex="-1" @click="close()"></button>
            <div
                class="ops-workspace-modal__dialog ops-workspace-modal__dialog--exception"
                x-ref="dialog"
                tabindex="-1"
                @click.stop
            >
                <header class="ops-workspace-modal__header">
                    <div class="ops-workspace-modal__heading min-w-0">
                        <h2 id="linked-parts-quote-title" class="ops-workspace-modal__title" x-text="summaryTitle()"></h2>
                        <p class="ops-workspace-modal__helper" x-text="summaryDetail()"></p>
                    </div>
                    <button type="button" class="ops-workspace-modal__close" @click="close()">Close</button>
                </header>

                <div class="ops-workspace-modal__body">
                    <p class="text-xs text-slate-500" x-show="panel?.open_note" x-cloak x-text="panel.open_note"></p>
                    <p class="mt-2 text-xs text-slate-700" x-show="notice" x-cloak x-text="notice" role="status"></p>
                    <p class="mt-2 text-xs text-amber-800" x-show="cached && panel?.current" x-cloak>Showing the last saved quote. This check did not verify it.</p>
                    <p class="mt-2 text-xs text-slate-500" x-show="!cached && panel?.current?.confirmed" x-cloak>Verified with the catalog.</p>
                    <p class="mt-2 text-xs text-rose-700" x-show="panel?.current?.message" x-cloak x-text="panel.current.message" role="alert"></p>

                    <div class="mt-3 divide-y divide-slate-100" x-show="panel?.current?.lines?.length" x-cloak>
                        <template x-for="line in (panel?.current?.lines || [])" :key="(line.part_number || '') + line.description">
                            <div class="flex items-center justify-between gap-4 py-2 text-sm">
                                <span class="text-slate-800" x-text="quantity(line.quantity) + ' × ' + line.description"></span>
                                <span class="font-medium text-slate-600" x-text="line.part_number"></span>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800" :disabled="loading || !panel?.current" @click="refresh()">Refresh quote</button>
                        <button type="button" class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-800" :disabled="loading" @click="choose()">Change linked cart</button>
                    </div>

                    <div class="mt-4" x-show="choosing" x-cloak>
                        <p class="text-xs font-semibold text-slate-700">Choose a quote. This does not import its parts.</p>
                        <template x-for="candidate in candidates" :key="candidate.external_id">
                            <button type="button" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-xs" :disabled="loading" @click="link(candidate.external_id)">
                                <span class="font-semibold" x-text="(candidate.supplier || 'Quote') + (statusLabel(candidate.status) ? (' · ' + statusLabel(candidate.status)) : '')"></span>
                                <span class="mt-0.5 block text-slate-500" x-text="quoteMeta(candidate)"></span>
                            </button>
                        </template>
                        <p class="mt-2 text-xs text-slate-500" x-show="candidates.length === 0 && !loading" x-cloak>No matching quotes were returned for this repair order.</p>
                    </div>

                    <div class="mt-4 border-t border-slate-200 pt-3">
                        <p class="text-xs font-semibold text-slate-700">Quote history</p>
                        <template x-for="row in (panel?.history || [])" :key="row.id">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-xs">
                                <div>
                                    <p class="font-semibold text-slate-800" x-text="(row.supplier || 'Quote') + (statusLabel(row.status) ? (' · ' + statusLabel(row.status)) : '')"></p>
                                    <p class="text-slate-500" x-text="quoteMeta(row)"></p>
                                </div>
                                <button type="button" class="font-semibold text-slate-700 underline" x-show="!row.is_current" :disabled="loading" @click="makeCurrent(row.id)">Make current</button>
                                <span class="font-semibold text-emerald-700" x-show="row.is_current" x-cloak>Current</span>
                            </div>
                        </template>
                        <p class="mt-2 text-xs text-slate-500" x-show="!(panel?.history || []).length">No earlier quotes are saved for this repair order.</p>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
