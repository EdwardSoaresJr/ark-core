@php
    $complaint = trim((string) $concern->customer_states);
    $findings = trim((string) $concern->verified_findings);
    $dtcs = trim((string) $concern->dtcs_summary);
    $recommend = trim((string) $concern->recommendation);
    $displayLines = $concern->lines->filter(fn ($line) => $line->shouldDisplayOnEstimateWorksheet());
    $noteCount = $displayLines->filter(fn ($line) => $line->type->isNote())->count();
    $workCount = $displayLines->reject(fn ($line) => $line->type->isNote())->count();
    $laborCount = $displayLines->filter(fn ($line) => $line->type->isLabor())->count();
    $partCount = $displayLines->filter(fn ($line) => $line->type->isPart())->count();
    $feeCount = $displayLines->filter(fn ($line) => $line->type->value === 'fee')->count();
    $disposition = $concern->disposition;
    $production = $concern->productionStatus();
    $intent = $concern->recommendationIntent();
    $amount = $totals->format($totals->concernSubtotalCents($concern->id));
    $preview = static function (string $text, int $limit = 88): string {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        return \Illuminate\Support\Str::limit($text, $limit, '…');
    };

    $workBits = collect([
        $laborCount > 0 ? $laborCount.' labor' : null,
        $partCount > 0 ? $partCount.' part'.($partCount === 1 ? '' : 's') : null,
        $feeCount > 0 ? $feeCount.' fee'.($feeCount === 1 ? '' : 's') : null,
    ])->filter()->implode(' · ');

    $progressPhrase = match ($production) {
        \App\Ark\Operations\RepairOrders\ScopeProductionStatus::Pending => 'Waiting',
        \App\Ark\Operations\RepairOrders\ScopeProductionStatus::WaitingParts => 'Waiting parts',
        default => $production->label(),
    };

    $authPhrase = match ($disposition->value) {
        'approved' => 'Approved',
        'deferred' => 'Deferred',
        'declined' => 'Declined',
        'recommended' => 'Recommended',
        default => 'Auth needed',
    };

    $stages = [
        'complaint' => [
            'label' => 'Complaint',
            'phrase' => $complaint !== '' ? 'Complaint recorded' : 'Complaint missing',
            'question' => 'What did the customer report?',
            'state' => $complaint !== '' ? 'ready' : 'missing',
            'kind' => 'docs',
            'preview' => $complaint !== '' ? $preview($complaint) : 'Not recorded',
            'copy' => $complaint,
        ],
        'findings' => [
            'label' => 'Findings',
            'phrase' => ($findings !== '' || $dtcs !== '')
                ? 'Findings recorded'
                : 'Findings missing',
            'question' => 'What do we know?',
            'state' => ($findings !== '' || $dtcs !== '')
                ? 'ready'
                : ($noteCount > 0 ? 'attention' : 'missing'),
            'kind' => 'docs',
            'preview' => ($findings !== '' || $dtcs !== '')
                ? $preview(collect([$findings, $dtcs !== '' ? 'DTC '.$dtcs : null])->filter()->implode(' '))
                : ($noteCount > 0
                    ? $noteCount.' note'.($noteCount === 1 ? '' : 's').' on this concern — not recorded as findings'
                    : 'Not recorded'),
            'copy' => collect([$findings, $dtcs !== '' ? 'DTC '.$dtcs : null])->filter()->implode("\n"),
        ],
        'recommend' => [
            'label' => 'Recommend',
            'phrase' => $recommend !== '' ? 'Recommendation recorded' : 'Recommendation missing',
            'question' => 'What are we recommending?',
            'state' => $recommend !== '' ? 'ready' : 'missing',
            'kind' => 'docs',
            'preview' => $recommend !== '' ? $preview($recommend) : 'Not recorded',
            'copy' => $recommend,
        ],
        'work' => [
            'label' => 'Work',
            'phrase' => $workCount > 0 ? 'Work on estimate' : 'No work yet',
            'question' => 'What is on the estimate?',
            'state' => $workCount > 0 ? 'ready' : 'missing',
            'kind' => 'ops',
            'preview' => $workCount > 0 ? $workBits : 'No labor or parts yet',
            'copy' => $workCount > 0 ? $workBits : '',
        ],
        'auth' => [
            'label' => 'Authorization',
            'phrase' => $authPhrase,
            'question' => 'What is approved?',
            'state' => in_array($disposition->value, ['approved', 'deferred', 'declined'], true)
                ? 'ready'
                : ($workCount > 0 ? 'attention' : 'missing'),
            'kind' => 'auth',
            'preview' => $disposition->label(),
            'copy' => $disposition->helpText(),
        ],
        'progress' => [
            'label' => 'Progress',
            'phrase' => $progressPhrase,
            'question' => 'What is happening next?',
            'state' => $production !== \App\Ark\Operations\RepairOrders\ScopeProductionStatus::Pending
                ? 'ready'
                : ($disposition->value === 'approved' ? 'attention' : 'missing'),
            'kind' => 'ops',
            'preview' => $production->label(),
            'copy' => $production->helpText(),
        ],
    ];
    $defaultStage = collect($stages)->search(fn ($stage) => $stage['state'] === 'attention')
        ?: (collect($stages)->search(fn ($stage) => $stage['state'] === 'missing') ?: 'work');

    // Gaps only — authorization stays in the header so Approved ≠ “Needs complaint”.
    $storyStageKeys = collect($stages)
        ->filter(fn (array $stage, string $key): bool => in_array($stage['state'], ['attention', 'missing'], true)
            && ($stage['kind'] ?? '') !== 'auth')
        ->keys()
        ->values();

    $docGapKeys = $storyStageKeys->filter(fn (string $key): bool => ($stages[$key]['kind'] ?? '') === 'docs')->values();
    $opsGapKeys = $storyStageKeys->filter(fn (string $key): bool => ($stages[$key]['kind'] ?? '') === 'ops')->values();

    if ($storyStageKeys->isEmpty()) {
        $opsGapKeys = collect(['progress']);
    }
@endphp

<section
    id="concern-{{ $concern->id }}"
    class="card mb-3 ops-worksheet-concern ark-tabler-ro__concern {{ $intent->worksheetScopeClass() }} ops-worksheet-concern--{{ $disposition->value }} scroll-mt-24"
    @if (($stages[$defaultStage]['state'] ?? null) === 'attention')
        data-attention
    @endif
    x-data="{
        stage: @js($defaultStage),
        selected: false,
        stages: @js($stages),
        select(key) {
            this.stage = key;
            this.emit();
        },
        emit() {
            this.selected = true;
            const current = this.stages[this.stage] || {};
            window.dispatchEvent(new CustomEvent('ark-tabler-concern-focus', {
                detail: {
                    id: {{ $concern->id }},
                    title: @js($concern->summary),
                    amount: @js($amount),
                    disposition: @js($disposition->label()),
                    production: @js($production->label()),
                    noteCount: {{ $noteCount }},
                    stage: this.stage,
                    stageLabel: current.label || '',
                    stageKind: current.kind || '',
                    question: current.question || '',
                    state: current.state || 'missing',
                    preview: current.preview || '',
                },
            }));
        },
    }"
    :class="{ 'border-primary': selected }"
    @ark-tabler-concern-focus.window="selected = Number($event.detail?.id) === {{ $concern->id }}"
    x-init="$nextTick(() => {
        if ($el === document.querySelector('.ark-tabler-ro__concern[data-attention]')) emit();
    })"
>
    <div class="card-header" @click.stop="emit()">
        <div>
            <div class="subheader mb-1">{{ $intent->staffLabel() }}</div>
            <h3 class="card-title mb-0">{{ $concern->summary }}</h3>
        </div>
        <div class="card-actions d-flex flex-wrap align-items-center gap-2">
            @include('operations.repair-orders.partials.repair-order-concern-disposition-decision', [
                'concern' => $concern,
            ])
            <span class="badge bg-secondary-lt text-secondary-lt-fg">{{ $production->label() }}</span>
            <strong class="text-yellow">{{ $amount }}</strong>
        </div>
    </div>

    <div class="card-body py-2">
        @unless ($isTerminal)
            <div class="btn-list mb-2">
                @include('operations.repair-orders.partials.repair-order-concern-disposition-control', [
                    'repairOrder' => $repairOrder,
                    'concern' => $concern,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                    'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                ])
                @include('operations.repair-orders.partials.repair-order-concern-production-status-control', [
                    'repairOrder' => $repairOrder,
                    'concern' => $concern,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                    'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                ])
                @include('operations.repair-orders.partials.repair-order-concern-scope-settings', [
                    'repairOrder' => $repairOrder,
                    'concern' => $concern,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                    'concernDefaultLaborRate' => $concernDefaultLaborRate,
                    'canMoveScopeToNewRo' => ! ($financial['hasIssuedInvoice'] ?? false),
                    'partsCatalogs' => $partsCatalogs,
                    'partsCatalogDefault' => $partsCatalogDefault,
                    'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                ])
            </div>
        @endunless

        @if ($docGapKeys->isNotEmpty() || $opsGapKeys->isNotEmpty())
            <div class="ark-tabler-ro__story-stack">
                @if ($docGapKeys->isNotEmpty())
                    <p class="ark-tabler-ro__story mb-0" role="tablist" aria-label="Documentation still needed">
                        <span class="ark-tabler-ro__story-label">Docs</span>
                        @foreach ($docGapKeys as $key)
                            @php $stage = $stages[$key]; @endphp
                            <span class="ark-tabler-ro__story-sep text-secondary" aria-hidden="true">·</span>
                            <button
                                type="button"
                                role="tab"
                                class="btn btn-link p-0 ark-tabler-ro__story-seg is-{{ $stage['state'] }}"
                                :class="{ 'is-active': stage === '{{ $key }}' }"
                                :aria-selected="stage === '{{ $key }}'"
                                title="{{ $stage['question'] }} {{ $stage['preview'] }}"
                                @click.stop="select('{{ $key }}')"
                            >{{ $stage['phrase'] }}</button>
                        @endforeach
                    </p>
                @endif
                @if ($opsGapKeys->isNotEmpty())
                    <p class="ark-tabler-ro__story mb-0" role="tablist" aria-label="Work still needed">
                        <span class="ark-tabler-ro__story-label">Next</span>
                        @foreach ($opsGapKeys as $key)
                            @php $stage = $stages[$key]; @endphp
                            <span class="ark-tabler-ro__story-sep text-secondary" aria-hidden="true">·</span>
                            <button
                                type="button"
                                role="tab"
                                class="btn btn-link p-0 ark-tabler-ro__story-seg is-{{ $stage['state'] }}"
                                :class="{ 'is-active': stage === '{{ $key }}' }"
                                :aria-selected="stage === '{{ $key }}'"
                                title="{{ $stage['question'] }} {{ $stage['preview'] }}"
                                @click.stop="select('{{ $key }}')"
                            >{{ $stage['phrase'] }}</button>
                        @endforeach
                    </p>
                @endif
            </div>
        @endif
    </div>

    @include('operations.repair-orders.partials.repair-order-concern-work-section', [
        'repairOrder' => $repairOrder,
        'concern' => $concern,
        'isTerminal' => $isTerminal,
        'editingLineId' => $editingLineId,
        'totals' => $totals,
        'taxLabel' => $taxLabel,
        'estimateVersion' => $estimateVersion,
        'concernDefaultLaborRate' => $concernDefaultLaborRate,
        'concernDefaultLaborCategoryKey' => $concernDefaultLaborCategoryKey,
        'concernPartsMatrixKey' => $concernPartsMatrixKey,
        'partsMatrices' => $partsMatrices,
        'laborCategories' => $laborCategories,
        'defaultLaborRate' => $defaultLaborRate,
        'defaultNotesPrivate' => $defaultNotesPrivate,
        'technicians' => $technicians ?? collect(),
    ])
</section>
