@php
    /** @var array|null $approvalForecast */
    $forecast = $approvalForecast ?? null;
    $showForecast = is_array($forecast) && ($forecast['visible'] ?? false);
    $variant = $variant ?? 'staff';
    $isCustomer = in_array($variant, ['customer', 'pdf'], true);
    $pendingCount = (int) ($forecast['pending_concern_count'] ?? 0);
    $pendingCountLabel = $pendingCount === 1 ? '1 item' : $pendingCount.' items';
    $projectedAllLabel = $pendingCount === 1
        ? 'If This Recommendation Is Approved'
        : 'If All Recommendations Are Approved';
    $projectedStaffLabel = $pendingCount === 1
        ? 'If Approved'
        : 'If All Approved';
    // Added or raised work on approved scopes is not approved until the customer authorizes it.
    $needsAuthorizationCents = (int) ($forecast['needs_authorization_cents'] ?? 0);
    $hasRecommendations = (int) ($forecast['pending_cents'] ?? 0) > 0 || $pendingCount > 0;
    if (! $hasRecommendations) {
        $projectedAllLabel = 'If These Changes Are Approved';
        $projectedStaffLabel = 'If Approved';
    }

    // Shared projection; presentation differs by audience (Projection Rule #1).
    if ($variant === 'pdf') {
        $title = null;
        $approvedLabel = 'Approved Work';
        $pendingLabel = $pendingCount > 0
            ? 'Additional Recommendations · '.$pendingCountLabel
            : 'Additional Recommendations';
        $projectedLabel = $projectedAllLabel;
        $footnote = null;
        $pendingPrefix = '';
        $needsAuthorizationLabel = 'Changes Awaiting Your Approval';
    } elseif ($variant === 'customer') {
        $title = null;
        $approvedLabel = 'Approved Work';
        $pendingLabel = $pendingCount > 0
            ? 'Additional Recommendations · '.$pendingCountLabel
            : 'Additional Recommendations';
        $projectedLabel = $projectedAllLabel;
        $footnote = $hasRecommendations
            ? 'Only work you approve will be performed. Additional recommendations are not authorized yet.'
            : 'Only work you approve will be performed. The changes are not authorized yet.';
        $pendingPrefix = '';
        $needsAuthorizationLabel = 'Changes Awaiting Your Approval';
    } else {
        $title = 'Approval Forecast';
        $approvedLabel = 'Approved';
        $pendingLabel = 'Needs Approval';
        $projectedLabel = $projectedStaffLabel;
        $footnote = 'Conversation prep - not invoice authority.';
        $pendingPrefix = '+';
        $needsAuthorizationLabel = 'Needs Authorization';
    }
@endphp

@if ($showForecast && $variant === 'staff')
    <div class="card-body border-bottom" id="approval-forecast" data-approval-forecast>
        <h4 class="subheader mb-2">{{ $title }}</h4>
        <dl class="mb-0">
            <div class="d-flex justify-content-between align-items-baseline gap-3">
                <dt class="fw-normal text-secondary">{{ $approvedLabel }}</dt>
                <dd class="mb-0 fw-semibold ark-money">{{ $forecast['approved_label'] }}</dd>
            </div>
            @if ($needsAuthorizationCents > 0)
                <div class="d-flex justify-content-between align-items-baseline gap-3 mt-1" data-forecast-needs-authorization>
                    <dt class="fw-normal text-secondary">{{ $needsAuthorizationLabel }}</dt>
                    <dd class="mb-0 fw-semibold text-orange ark-money">{{ $pendingPrefix }}{{ $forecast['needs_authorization_label'] }}</dd>
                </div>
            @endif
            @if ($hasRecommendations)
            <div class="d-flex justify-content-between align-items-baseline gap-3 mt-1">
                <dt class="fw-normal text-secondary">
                    {{ $pendingLabel }}
                    @if ($pendingCount > 0)
                        <span class="d-block small">{{ $pendingCountLabel }}</span>
                    @endif
                </dt>
                <dd class="mb-0 fw-semibold text-orange ark-money">{{ $pendingPrefix }}{{ $forecast['pending_label'] }}</dd>
            </div>
            @endif
            <div class="d-flex justify-content-between align-items-baseline gap-3 border-top pt-2 mt-2">
                <dt>{{ $projectedLabel }}</dt>
                <dd class="mb-0 fw-bold ark-money">{{ $forecast['projected_label'] }}</dd>
            </div>
        </dl>
        <p class="small text-secondary mt-2 mb-0">{{ $footnote }}</p>
    </div>
@elseif ($showForecast)
    <div
        @class([
            'portal-approval-forecast mt-3 mb-1' => $variant === 'customer',
            'approval-forecast-pdf' => $variant === 'pdf',
        ])
        @if ($variant !== 'pdf')
            id="approval-forecast"
            data-approval-forecast
        @endif
    >
        @if ($title)
            <p @class([
                'ops-eyebrow text-slate-600' => $variant === 'staff',
            ])>{{ $title }}</p>
        @endif
        <dl @class([
            'mt-1.5 space-y-1 text-sm' => $variant === 'staff',
            'space-y-1.5 text-sm' => $variant === 'customer',
            'approval-forecast-pdf__rows' => $variant === 'pdf',
        ])>
            <div @class([
                'ops-total-row py-0.5' => $variant === 'staff',
                'flex items-baseline justify-between gap-3' => $variant === 'customer',
                'approval-forecast-pdf__row' => $variant === 'pdf',
            ])>
                <dt @class([
                    'text-slate-500' => $variant !== 'pdf',
                    'approval-forecast-pdf__label' => $variant === 'pdf',
                ])>{{ $approvedLabel }}</dt>
                <dd @class([
                    'font-semibold tabular-nums text-slate-950' => $variant !== 'pdf',
                    'approval-forecast-pdf__value' => $variant === 'pdf',
                ])>{{ $forecast['approved_label'] }}</dd>
            </div>
            @if ($needsAuthorizationCents > 0)
                <div @class([
                    'ops-total-row py-0.5' => $variant === 'staff',
                    'flex items-baseline justify-between gap-3' => $variant === 'customer',
                    'approval-forecast-pdf__row' => $variant === 'pdf',
                ]) data-forecast-needs-authorization>
                    <dt @class([
                        'text-slate-500' => $variant !== 'pdf',
                        'approval-forecast-pdf__label' => $variant === 'pdf',
                    ])>{{ $needsAuthorizationLabel }}</dt>
                    <dd @class([
                        'font-semibold tabular-nums text-amber-800' => $variant !== 'pdf',
                        'approval-forecast-pdf__value approval-forecast-pdf__value--pending' => $variant === 'pdf',
                    ])>{{ $pendingPrefix }}{{ $forecast['needs_authorization_label'] }}</dd>
                </div>
            @endif
            @if ($hasRecommendations)
            <div @class([
                'ops-total-row py-0.5' => $variant === 'staff',
                'flex items-baseline justify-between gap-3' => $variant === 'customer',
                'approval-forecast-pdf__row' => $variant === 'pdf',
            ])>
                <dt @class([
                    'text-slate-500' => $variant !== 'pdf',
                    'approval-forecast-pdf__label' => $variant === 'pdf',
                ])>
                    {{ $pendingLabel }}
                    @if ($variant === 'staff' && $pendingCount > 0)
                        <span class="mt-0.5 block text-[10px] font-normal normal-case tracking-normal text-slate-400">
                            {{ $pendingCountLabel }}
                        </span>
                    @endif
                </dt>
                <dd @class([
                    'font-semibold tabular-nums text-amber-800' => $variant !== 'pdf',
                    'approval-forecast-pdf__value approval-forecast-pdf__value--pending' => $variant === 'pdf',
                ])>{{ $pendingPrefix }}{{ $forecast['pending_label'] }}</dd>
            </div>
            @endif
            <div @class([
                'ops-total-row ops-total-row--final border-t border-slate-200/80 pt-1.5 mt-1' => $variant === 'staff',
                'flex items-baseline justify-between gap-3 border-t border-slate-200/80 pt-2 mt-1' => $variant === 'customer',
                'approval-forecast-pdf__row approval-forecast-pdf__row--projected' => $variant === 'pdf',
            ])>
                <dt @class([
                    'text-slate-700' => $variant === 'staff',
                    'font-semibold text-slate-800' => $variant === 'customer',
                    'approval-forecast-pdf__label approval-forecast-pdf__label--projected' => $variant === 'pdf',
                ])>{{ $projectedLabel }}</dt>
                <dd @class([
                    'font-bold tabular-nums text-slate-950' => $variant !== 'pdf',
                    'approval-forecast-pdf__value approval-forecast-pdf__value--projected' => $variant === 'pdf',
                ])>{{ $forecast['projected_label'] }}</dd>
            </div>
        </dl>
        @if ($footnote)
            <p @class([
                'mt-1.5 text-[10px] leading-snug text-slate-400' => $variant === 'staff',
                'mt-2 text-[11px] leading-snug text-slate-500' => $variant === 'customer',
            ])>{{ $footnote }}</p>
        @endif
    </div>
@endif
