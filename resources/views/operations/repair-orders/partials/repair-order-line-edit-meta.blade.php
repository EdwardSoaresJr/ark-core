@php
    use App\Ark\Operations\RepairOrders\RepairOrderLineItemPresentation;

    $isPartLine = $line->type->isPart();
    $isSubletLine = $line->type->value === 'sublet';
    $profitability = $isPartLine ? RepairOrderLineItemPresentation::profitabilityMeter($line) : null;
    $editPricingSegments = $isPartLine
        ? RepairOrderLineItemPresentation::editPricingSegments($line, $totals)
        : [];
    $editContextLines = RepairOrderLineItemPresentation::editContextLines($line);
    $factPriceLabel = $isPartLine || $isSubletLine ? 'Cost' : 'Price';
    $factPriceValue = $isPartLine || $isSubletLine
        ? ($line->part_cost_cents !== null ? $totals->format($line->part_cost_cents) : $totals->format($line->unit_price_cents))
        : $totals->format($line->unit_price_cents);
    $hasFacts = $isPartLine || $isSubletLine || $editPricingSegments !== [] || $profitability;
@endphp

@if ($hasFacts || $editContextLines !== [])
    <div class="ops-line-card__facts lg:col-span-4 lg:col-start-1">
        @if ($hasFacts)
            <div class="ops-line-card__facts-line">
                <span>Qty {{ $line->quantity }}</span>
                <span class="ops-line-card__facts-sep" aria-hidden="true">•</span>
                <span>{{ $factPriceLabel }} {{ $factPriceValue }}</span>
                @if ($profitability)
                    <span class="ops-line-card__facts-sep" aria-hidden="true">•</span>
                    <span @class([
                        'ops-line-card__margin-label',
                        'ops-line-card__margin-label--'.$profitability['tone'],
                    ])>{{ $profitability['label'] }} {{ $profitability['percent'] }}%</span>
                @endif
                @foreach ($editPricingSegments as $pricingSegment)
                    <span class="ops-line-card__facts-sep" aria-hidden="true">•</span>
                    <span>{{ $pricingSegment }}</span>
                @endforeach
            </div>
        @endif

        @foreach ($editContextLines as $contextLine)
            <p class="ops-line-card__context">{{ $contextLine }}</p>
        @endforeach
    </div>
@endif
