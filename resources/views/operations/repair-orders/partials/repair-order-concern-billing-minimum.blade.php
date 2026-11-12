@php
    $billingMinimumExplanation = app(\App\Ark\Operations\EstimatePricing\ApplyBillingClassMinimumsAction::class)
        ->concernExplanation($repairOrder, $concern);
@endphp
@if ($billingMinimumExplanation)
    @if ($billingMinimumExplanation['applied'])
        <p
            class="small mb-2"
            role="status"
            data-billing-minimum-explanation
            data-billing-minimum-applied="1"
        >
            <span class="fw-medium">{{ $billingMinimumExplanation['label'] }} minimum applied</span>
            <span class="text-secondary">
                · {{ $billingMinimumExplanation['eligible_hours'] }} hr eligible
                · +{{ $billingMinimumExplanation['shortfall_hours'] }} hr
                / ${{ number_format($billingMinimumExplanation['shortfall_cents'] / 100, 2) }}
            </span>
        </p>
    @else
        <p
            class="small text-secondary mb-2"
            role="status"
            data-billing-minimum-explanation
            data-billing-minimum-applied="0"
        >
            {{ $billingMinimumExplanation['label'] }} minimum met
            ({{ $billingMinimumExplanation['eligible_hours'] }} / {{ $billingMinimumExplanation['required_hours'] }} hr)
        </p>
    @endif
@endif
