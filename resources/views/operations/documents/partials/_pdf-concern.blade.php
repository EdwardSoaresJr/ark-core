@php
    $dispositionEnum = \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));
    $disposition = $dispositionEnum?->value ?? '';
    $showConcernDisposition = $disposition !== '' && $disposition !== 'draft';
    $decisionMark = $dispositionEnum?->decisionMark() ?? '';
    $intent = \App\Ark\Operations\RepairOrders\RecommendationIntent::fromStored(
        (string) ($concern['recommendation_intent'] ?? ''),
    );
@endphp

<article class="concern concern--intent-{{ $intent->value }}">
    <div class="concern-header">
        <div class="concern-header-grid">
            <p class="concern-priority-badge concern-priority-badge--{{ $intent->value }}">{{ $intent->pdfGroupLabel() }}</p>
            <h2 class="concern-header-title">{{ $concern['summary'] }}</h2>
            <p class="concern-header-total">{{ $concern['subtotal'] }}</p>
            @if ($showConcernDisposition)
                <div class="concern-header-status">
                    <span class="concern-header-decision concern-header-decision--{{ $disposition }}">
                        @if ($decisionMark !== '')
                            <span class="concern-header-decision-mark">{{ $decisionMark }}</span>
                        @endif
                        {{ $concern['disposition_label'] }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    @if ((($concern['customer_states'] ?? null) && ! $duplicateCustomerStates) || ($concern['verified_findings'] ?? null) || ($concern['dtcs_summary'] ?? null))
        <div class="narrative-grid">
            @if (($concern['customer_states'] ?? null) && ! $duplicateCustomerStates)
                <div>
                    <p class="narrative-label">Customer states</p>
                    <p>{{ $concern['customer_states'] }}</p>
                </div>
            @endif
            @if ($concern['verified_findings'] ?? null || $concern['dtcs_summary'] ?? null)
                <div>
                    <p class="narrative-label">Verified findings</p>
                    @if ($concern['verified_findings'] ?? null)
                        <p>{{ $concern['verified_findings'] }}</p>
                    @endif
                    @if ($concern['dtcs_summary'] ?? null)
                        <p class="codes">Codes: {{ $concern['dtcs_summary'] }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($concern['recommendation'] ?? null)
        <div class="recommendation">
            <p class="narrative-label">Recommendation</p>
            <p>{{ $concern['recommendation'] }}</p>
        </div>
    @endif

    @php
        $workGroups = collect($concern['work_groups'] ?? [])->filter(fn (array $group): bool => count($group['lines'] ?? []) > 0);
        $ungroupedLines = collect($concern['lines'] ?? [])
            ->filter(fn (array $line): bool => blank($line['repair_order_work_group_id'] ?? null));
        $hasGroupedWork = $workGroups->isNotEmpty();
        $linesToRender = $hasGroupedWork ? $ungroupedLines : collect($concern['lines'] ?? []);
    @endphp

    @if ($hasGroupedWork || $linesToRender->isNotEmpty())
        <div class="line-list">
            @foreach ($workGroups as $workGroup)
                @php
                    $repairHeading = \App\Ark\Operations\Documents\CustomerRepairActionIncludes::groupHeading((string) ($workGroup['title'] ?? ''));
                @endphp
                <div class="repair-action-group">
                    @if ($loop->first)
                        @include('operations.documents.partials._pdf-line-column-heads', [
                            'lineHeadLabel' => $repairHeading,
                        ])
                    @else
                        <div class="repair-action-header">
                            <p class="repair-action-title">{{ $repairHeading }}</p>
                        </div>
                    @endif
                    <div class="repair-action-lines">
                        @foreach ($workGroup['lines'] as $line)
                            @include('operations.documents.partials._pdf-line-item', [
                                'line' => $line,
                                'grouped' => true,
                                'workGroupTitle' => $workGroup['title'],
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($linesToRender->isNotEmpty())
                @unless ($hasGroupedWork)
                    @include('operations.documents.partials._pdf-line-column-heads')
                @endunless
                @foreach ($linesToRender as $line)
                    @include('operations.documents.partials._pdf-line-item', ['line' => $line])
                @endforeach
            @endif
        </div>
    @endif

    @include('operations.documents.partials._concern-customer-approval', [
        'snapshot' => $snapshot,
        'variant' => 'pdf',
        'disposition' => $concern['disposition'] ?? '',
    ])
</article>
