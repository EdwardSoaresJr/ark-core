@php
    $approvedCount = ($approvedConcerns ?? collect())->count();
    $statusLabel = $repairOrder->statusDisplayLabel();
    $oweToday = ($financial['oweToday'] ?? null) ?: ($financial['projectedBalance'] ?? null);
    $positionAmountLabel = $financial['positionAmountLabel']
        ?? (($financial['hasIssuedInvoice'] ?? false) ? 'Owe today' : 'Estimate balance');
@endphp

<div
    class="ark-tabler-ro__side"
    x-data="{
        focus: null,
        editNarrative() {
            if (!this.focus?.id) return;
            window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
                detail: { task: 'concern-narrative', context: { concernId: this.focus.id }, invokeEl: $el },
            }));
        },
        showNotes() {
            if (!this.focus?.id) return;
            document.querySelector('#concern-' + this.focus.id + ' .ark-tabler-ro__line--note')?.scrollIntoView({ block: 'center' });
        },
    }"
    @ark-tabler-concern-focus.window="focus = $event.detail"
>
    <div class="card mb-3">
        <div class="card-body">
            <div class="subheader">Estimate total</div>
            <div class="display-6 fw-bold text-yellow mb-2">{{ $totals->format($totals->totalCents()) }}</div>
            <div class="datagrid">
                <div class="datagrid-item">
                    <div class="datagrid-title">Labor</div>
                    <div class="datagrid-content">{{ $totals->format($totals->laborCents()) }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Parts</div>
                    <div class="datagrid-content">{{ $totals->format($totals->partsCents()) }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Fees</div>
                    <div class="datagrid-content">{{ $totals->format($totals->feesCents()) }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Tax</div>
                    <div class="datagrid-content">{{ $totals->format($totals->taxCents()) }}</div>
                </div>
            </div>
            @if ($oweToday)
                <div class="mt-3 pt-3 border-top">
                    <div class="subheader">{{ $positionAmountLabel }}</div>
                    <div class="h2 mb-0 text-yellow">{{ $oweToday }}</div>
                </div>
            @endif
            <div class="mt-3">
                <a href="#ark-tabler-payment" class="btn btn-primary w-100">
                    Payment &amp; deposits
                </a>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="subheader">Next</div>
            <div class="h3 mb-1">{{ $nextAction ?? 'Review the estimate' }}</div>
            <div class="text-secondary">
                {{ $statusLabel }}
                · {{ $approvalPosture ?? 'No authorization yet' }}
                · {{ $approvedCount }} approved
            </div>
        </div>
    </div>

    <div class="card mb-3" x-show="focus" x-cloak>
        <div class="card-body">
            <div class="subheader" x-text="focus?.question || 'Selected concern'"></div>
            <div class="fw-bold mb-1" x-text="focus?.title"></div>
            <div class="mb-2">
                <span class="badge" :class="{
                    'bg-azure-lt text-azure-lt-fg': focus?.state === 'ready',
                    'bg-yellow-lt text-yellow-lt-fg': focus?.state === 'attention',
                    'bg-secondary-lt text-secondary-lt-fg': focus?.state === 'missing',
                }" x-text="
                    focus?.state === 'ready'
                        ? 'Recorded'
                        : (['complaint','findings','recommend'].includes(focus?.stage)
                            ? (focus?.state === 'attention' ? 'Docs incomplete' : 'Docs missing')
                            : (focus?.state === 'attention' ? 'Needs attention' : 'Missing'))
                "></span>
                <span class="text-secondary ms-1" x-text="focus?.stageLabel"></span>
            </div>
            <p class="text-secondary mb-2" x-text="focus?.preview"></p>
            <div class="text-secondary small mb-2">
                <span x-text="focus?.disposition"></span>
                <span aria-hidden="true"> · </span>
                <span x-text="focus?.production"></span>
                <span class="text-yellow ms-1 fw-bold" x-text="focus?.amount"></span>
            </div>
            <div class="btn-list">
                <button
                    type="button"
                    class="btn btn-sm"
                    x-show="['complaint','findings','recommend'].includes(focus?.stage)"
                    @click="editNarrative()"
                >{{ $isTerminal ?? false ? 'View' : 'Edit' }}</button>
                <button
                    type="button"
                    class="btn btn-sm"
                    x-show="focus?.stage === 'findings' && Number(focus?.noteCount) > 0"
                    @click="showNotes()"
                >Show notes</button>
            </div>
        </div>
    </div>

    <div class="card mb-3" x-show="!focus" x-cloak>
        <div class="card-body text-secondary">
            Select a concern to inspect complaint, findings, recommendation, work, authorization, and progress.
        </div>
    </div>
</div>
