@php
    $toneText = fn (?string $tone, string $fallback = 'text-secondary'): string => match ($tone) {
        'good' => 'text-green',
        'warn' => 'text-warning',
        default => $fallback,
    };
@endphp

<section class="card mb-3" data-report-section="margin-health">
    <div class="card-header">
        <div>
            <h3 class="card-title">Margin Health</h3>
            <div class="card-subtitle">
                Closed sales truth vs Demo Auto Repair targets.
                Edit bands in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}">Settings → Owner Targets &amp; Reporting</a>.
                Parts matrix lives in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'financial']) }}">Financial Rules</a>.
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Metric</th>
                    <th scope="col" class="text-end">Actual</th>
                    <th scope="col" class="text-end">Target</th>
                    <th scope="col">Posture</th>
                    <th scope="col">Next action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($marginHealthRows as $row)
                    <tr data-report-tone="{{ $row['tone'] ?? 'neutral' }}">
                        <td class="fw-bold">{{ $row['metric'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['actual'] }}</td>
                        <td class="text-end text-secondary ark-money">{{ $row['target'] }}</td>
                        <td class="small fw-medium {{ $toneText($row['tone'] ?? null) }}">{{ $row['posture'] }}</td>
                        <td class="small text-secondary">{{ $row['action'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if ($breakEvenSummary !== null)
    <section class="mb-3" aria-labelledby="report-break-even" data-report-section="break-even">
        <h3 class="mb-0" id="report-break-even">Break-even pulse</h3>
        <p class="text-secondary small mb-2">
            Closed labor + parts GP vs prorated monthly fixed costs ({{ $breakEvenSummary['range_days'] }} days in range).
            Monthly fixed: {{ $breakEvenSummary['monthly_fixed_label'] }}.
        </p>
        <div class="row row-cards">
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Closed GP</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $breakEvenSummary['gross_profit_label'] }}</div>
                        <div class="small text-secondary mt-1">{{ $breakEvenSummary['margin_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Prorated fixed</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $breakEvenSummary['prorated_fixed_label'] }}</div>
                        <div class="small text-secondary mt-1">Monthly pool spread across range</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Surplus / gap</div>
                        <div class="h3 mb-0 mt-1 ark-money {{ $toneText($breakEvenSummary['tone'], '') }}">{{ $breakEvenSummary['surplus_label'] }}</div>
                        <div class="small fw-medium mt-1 {{ $toneText($breakEvenSummary['tone']) }}">{{ $breakEvenSummary['posture'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Monthly break-even sales</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $breakEvenSummary['monthly_break_even_sales_label'] ?? 'n/a' }}</div>
                        <div class="small text-secondary mt-1">At current closed GP margin</div>
                    </div>
                </div>
            </div>
        </div>
        <p class="small text-secondary mt-2 mb-0">{{ $breakEvenSummary['action'] }}</p>
    </section>
@else
    <div class="alert alert-info mb-3" data-report-section="break-even-setup">
        Add <strong>monthly fixed costs</strong> under
        <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}">Settings → Owner Targets &amp; Reporting</a>
        to see break-even vs closed gross profit. Net profit still lives in your bookkeeper&apos;s P&amp;L.
    </div>
@endif

<p class="small text-secondary mb-0">
    Weekly rhythm:
    <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']) }}">Weekly owner review</a>.
</p>
