<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RO #{{ $sheet['repair_order_id'] }} Check In Sheet</title>
    @include('operations.documents.sheets._layout-styles')
</head>
<body>
    <div class="sheet">
        @include('operations.documents.partials._pdf-document-header', [
            'shop' => $sheet['shop'],
            'identity' => $sheet['identity'],
        ])

        <div class="sheet-context">
            <div>
                <p class="eyebrow">{{ $sheet['title'] }}</p>
                <h2 class="sheet-context-title">{{ $sheet['heading'] }}</h2>
            </div>
            <p class="sheet-context-meta">{{ $sheet['printed_at'] }}</p>
        </div>

        @include('operations.documents.sheets._sheet-staff-capture')

        @include('operations.documents.sheets._sheet-checkin-capture', ['heading' => 'Advisor Check In'])

        @include('operations.documents.sheets._sheet-mileage-capture')

        @if (! empty($sheet['intake_flags']))
            <div class="flags">
                @foreach ($sheet['intake_flags'] as $flag)
                    <span class="flag">{{ $flag }}</span>
                @endforeach
            </div>
        @endif

        <section class="section">
            <p class="sheet-section-heading">Customer Concerns</p>

            @foreach ($sheet['concerns'] as $concern)
                @php
                    $presentation = \App\Ark\Operations\RepairOrders\RecommendationPresentation::forSnapshot($concern);
                @endphp
                <article class="concern {{ $presentation->pdfScopeClass() }}">
                    <div class="concern-header">
                        <div class="concern-header-grid">
                            <h2 class="concern-header-title">{{ $concern['summary'] }}</h2>
                            @if ($presentation->staffLabel())
                                <p class="concern-header-meta {{ $presentation->badgeClass() }}">{{ $presentation->staffLabel() }}</p>
                            @endif
                        </div>
                    </div>

                    @if (! empty($concern['customer_states']))
                        <div class="narrative-grid">
                            <div>
                                <p class="narrative-label">Customer states</p>
                                <p>{{ $concern['customer_states'] }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="worksheet-capture">Technician initial inspection notes</div>
                </article>
            @endforeach
        </section>
    </div>
</body>
</html>
