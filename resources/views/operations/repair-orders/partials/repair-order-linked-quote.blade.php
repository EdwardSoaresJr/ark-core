@php
    $linkedQuoteConfig = [
        'showUrl' => route('operations.repair-orders.linked-parts-quotes.show', $repairOrder),
        'refreshUrl' => route('operations.repair-orders.linked-parts-quotes.refresh', $repairOrder),
        'candidatesUrl' => route('operations.repair-orders.linked-parts-quotes.candidates', $repairOrder),
        'linkUrl' => route('operations.repair-orders.linked-parts-quotes.store', $repairOrder),
        'currentUrl' => route('operations.repair-orders.linked-parts-quotes.current', $repairOrder),
        'csrf' => csrf_token(),
    ];
@endphp

<script>
    window.arkLinkedPartsQuote = window.arkLinkedPartsQuote || function (config) {
        return {
            config,
            panel: null,
            candidates: [],
            mode: 'quote',
            notice: '',
            loading: false,
            async load() {
                await this.send(this.config.showUrl, 'GET');
            },
            async refresh() {
                await this.send(this.config.refreshUrl, 'POST');
                this.mode = 'quote';
            },
            async choose() {
                this.mode = 'choose';
                this.loading = true;
                try {
                    const response = await fetch(this.config.candidatesUrl, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    const payload = await response.json();
                    this.panel = payload;
                    this.candidates = Array.isArray(payload.candidates) ? payload.candidates : [];
                    this.notice = payload.message || '';
                } catch (error) {
                    this.notice = 'The linked quote could not be loaded.';
                } finally {
                    this.loading = false;
                }
            },
            async link(externalId) {
                await this.send(this.config.linkUrl, 'POST', { external_id: externalId });
                this.mode = 'quote';
                this.candidates = [];
            },
            async makeCurrent(linkId) {
                await this.send(this.config.currentUrl, 'POST', { link_id: linkId });
                this.mode = 'quote';
            },
            async send(url, method, body) {
                this.loading = true;
                try {
                    const response = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.config.csrf,
                        },
                        body: method === 'GET' ? undefined : JSON.stringify(body || {}),
                    });
                    const payload = await response.json();
                    this.panel = payload;
                    this.notice = payload.message || '';
                } catch (error) {
                    this.notice = 'The linked quote could not be loaded.';
                } finally {
                    this.loading = false;
                }
            },
            quantity(value) {
                const text = String(value ?? '');
                return text.endsWith('.00') ? text.slice(0, -3) : text;
            },
        };
    };
</script>

<div
    class="ops-worksheet-procurement-panel mb-3"
    x-data="arkLinkedPartsQuote(@js($linkedQuoteConfig))"
    x-init="load()"
>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500" x-text="(panel?.provider_label || 'Catalog') + ' · linked quote'"></p>
            <p class="mt-1 text-sm font-semibold text-slate-900" x-text="panel?.current?.supplier || 'No quote linked'"></p>
            <p class="mt-0.5 text-xs text-slate-600" x-show="panel?.current">
                <span x-text="panel.current.purchase_order ? ('PO ' + panel.current.purchase_order) : 'No purchase order'"></span>
                ·
                <span x-text="panel.current.item_count + ' item' + (panel.current.item_count === 1 ? '' : 's')"></span>
                ·
                <span x-text="panel.current.status || 'Unknown'"></span>
                ·
                <span x-text="'···' + panel.current.reference_tail"></span>
            </p>
        </div>
        <span
            x-show="panel?.current?.is_current"
            x-cloak
            class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-emerald-700"
        >Current</span>
    </div>

    <div class="divide-y divide-slate-100" x-show="panel?.current?.lines?.length" x-cloak>
        <template x-for="line in (panel?.current?.lines || [])" :key="(line.part_number || '') + line.description">
            <div class="flex items-center justify-between gap-4 px-4 py-2 text-sm">
                <span class="text-slate-800" x-text="quantity(line.quantity) + ' × ' + line.description"></span>
                <span class="font-medium text-slate-600" x-text="line.part_number"></span>
            </div>
        </template>
    </div>

    <p class="px-4 py-2 text-xs text-slate-600" x-show="notice" x-cloak x-text="notice"></p>
    <p class="px-4 pt-3 text-xs text-slate-500" x-text="panel?.open_note || ''"></p>

    <div class="grid grid-cols-2 gap-2 px-4 py-3">
        <button type="button" class="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800" :disabled="loading || !panel?.current" @click="refresh()">Refresh quote</button>
        <button type="button" class="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800" :disabled="loading" @click="mode === 'history' ? mode = 'quote' : mode = 'history'">Quote history</button>
        <button type="button" class="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800" :disabled="loading" @click="choose()">Change linked cart</button>
    </div>

    <div class="border-t border-slate-200 px-4 py-3" x-show="mode === 'choose'" x-cloak>
        <p class="text-xs font-semibold text-slate-700">Choose a quote. This does not import its parts.</p>
        <template x-for="candidate in candidates" :key="candidate.external_id">
            <button type="button" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-xs" @click="link(candidate.external_id)">
                <span class="font-semibold" x-text="(candidate.supplier || 'Quote') + ' · ' + (candidate.status || '')"></span>
                <span class="mt-0.5 block text-slate-500" x-text="(candidate.purchase_order ? ('PO ' + candidate.purchase_order + ' · ') : '') + candidate.item_count + ' items · ···' + candidate.reference_tail"></span>
            </button>
        </template>
        <p class="mt-2 text-xs text-slate-500" x-show="candidates.length === 0">No matching quotes were returned for this repair order.</p>
    </div>

    <div class="border-t border-slate-200 px-4 py-3" x-show="mode === 'history'" x-cloak>
        <template x-for="row in (panel?.history || [])" :key="row.id">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-xs">
                <div>
                    <p class="font-semibold text-slate-800" x-text="(row.supplier || 'Quote') + ' · ' + row.status"></p>
                    <p class="text-slate-500" x-text="(row.purchase_order ? ('PO ' + row.purchase_order + ' · ') : '') + '···' + row.reference_tail"></p>
                </div>
                <button type="button" class="font-semibold text-slate-700 underline" @click="makeCurrent(row.id)">Make current</button>
            </div>
        </template>
        <p class="text-xs text-slate-500" x-show="!(panel?.history || []).length">No earlier quotes are saved for this repair order.</p>
    </div>
</div>
