@php
    $dispositionEnum = \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::fromStored((string) ($concern['disposition'] ?? ''));
    $disposition = $dispositionEnum?->value ?? '';
    $showConcernDisposition = $disposition !== '' && $disposition !== 'draft';
    $intent = \App\Ark\Operations\RepairOrders\RecommendationIntent::fromStored(
        (string) ($concern['recommendation_intent'] ?? ''),
    );
    $customerTitle = trim((string) ($concern['customer_title'] ?? $concern['summary'] ?? ''));
    $findings = trim((string) ($concern['customer_findings'] ?? $concern['verified_findings'] ?? ''));
    $recommendation = trim((string) ($concern['customer_recommendation'] ?? $concern['recommendation'] ?? ''));
    $dtcs = trim((string) ($concern['customer_dtcs'] ?? $concern['dtcs_summary'] ?? ''));
    $statusPills = $concern['customer_status_pills'] ?? [];
    if ($statusPills === [] && $showConcernDisposition) {
        $statusPills = [trim((string) ($concern['disposition_label'] ?? $dispositionEnum?->scopeHeaderLabel() ?? ''))];
    }
    $chargeLines = $concern['charge_lines'] ?? null;
    $intentLabel = $intent->pdfGroupLabel();
    $titleAlreadyStatesIntent = $intentLabel !== '' && str_contains(mb_strtolower($customerTitle), mb_strtolower($intentLabel));
@endphp

<article class="concern concern--intent-{{ $intent->value }}">
    <div class="concern-header">
        <div class="concern-header-grid">
            @unless ($titleAlreadyStatesIntent)
                <p class="concern-priority-badge concern-priority-badge--{{ $intent->value }}">{{ $intentLabel }}</p>
            @endunless
            <h2 class="concern-header-title">{{ $customerTitle }}</h2>
            @if ($statusPills !== [])
                <div class="concern-header-status">
                    @foreach ($statusPills as $pill)
                        <span class="concern-status-pill concern-status-pill--{{ $disposition }}">{{ $pill }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ((($concern['customer_states'] ?? null) && ! $duplicateCustomerStates) || $findings !== '' || $dtcs !== '')
        <div class="narrative-stack">
            @if (($concern['customer_states'] ?? null) && ! $duplicateCustomerStates)
                <div class="narrative-block">
                    <p class="narrative-label">You told us</p>
                    <p>{{ $concern['customer_states'] }}</p>
                </div>
            @endif
            @if ($findings !== '' || $dtcs !== '')
                <div class="findings-panel">
                    <p class="narrative-label">Technician Findings</p>
                    @if ($findings !== '')
                        <p class="findings-body">{{ $findings }}</p>
                    @endif
                    @if ($dtcs !== '')
                        <p class="codes">Codes: {{ $dtcs }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($recommendation !== '')
        <div class="recommendation">
            <p class="narrative-label">Recommendation</p>
            <p>{{ $recommendation }}</p>
        </div>
    @endif

    @if (is_array($chargeLines) && $chargeLines !== [])
        <div class="charge-list">
            @foreach ($chargeLines as $charge)
                @if (($charge['kind'] ?? 'item') === 'heading')
                    <p class="charge-heading">{{ $charge['description'] }}</p>
                    @continue
                @endif
                <div class="charge-row">
                    <div class="charge-desc">
                        <p>
                            {{ $charge['description'] }}
                            @if (filled($charge['quantity_label'] ?? null))
                                <span class="charge-qty">{{ $charge['quantity_label'] }}</span>
                            @endif
                        </p>
                        @if (filled($charge['detail'] ?? null))
                            <p class="charge-detail">{{ $charge['detail'] }}</p>
                        @endif
                    </div>
                    <p class="charge-amount">{{ $charge['amount'] }}</p>
                </div>
            @endforeach
            @if (filled($concern['charge_subtotal'] ?? null) || filled($concern['subtotal'] ?? null))
                <div class="charge-row charge-row--total">
                    <p class="charge-desc">Work subtotal</p>
                    <p class="charge-amount">{{ $concern['charge_subtotal'] ?? $concern['subtotal'] }}</p>
                </div>
            @endif
        </div>
    @else
        @php
            $workGroups = collect($concern['work_groups'] ?? [])->filter(fn (array $group): bool => count($group['lines'] ?? []) > 0);
            $ungroupedLines = collect($concern['lines'] ?? [])
                ->filter(fn (array $line): bool => blank($line['repair_order_work_group_id'] ?? null))
                ->filter(fn (array $line): bool => ($line['customer_narrative'] ?? null) !== 'findings');
            $hasGroupedWork = $workGroups->isNotEmpty();
            $linesToRender = $hasGroupedWork ? $ungroupedLines : collect($concern['lines'] ?? [])
                ->filter(fn (array $line): bool => ($line['customer_narrative'] ?? null) !== 'findings');
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
                                @continue(($line['customer_narrative'] ?? null) === 'findings')
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
    @endif

    @if ((! is_array($chargeLines) || $chargeLines === []) && filled($concern['charge_subtotal'] ?? $concern['subtotal'] ?? null))
        <div class="charge-list">
            <div class="charge-row charge-row--total">
                <p class="charge-desc">Work subtotal</p>
                <p class="charge-amount">{{ $concern['charge_subtotal'] ?? $concern['subtotal'] }}</p>
            </div>
        </div>
    @endif

    @include('operations.documents.partials._concern-customer-approval', [
        'snapshot' => $snapshot,
        'variant' => 'pdf',
        'disposition' => $concern['disposition'] ?? '',
    ])
</article>
