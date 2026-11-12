@php
    $billingMinimumExplanation = app(\App\Ark\Operations\EstimatePricing\ApplyBillingClassMinimumsAction::class)
        ->concernExplanation($repairOrder, $concern);
@endphp
@if ($billingMinimumExplanation)
    <div
        class="alert alert-info py-2 px-3 mb-2 small"
        role="status"
        data-billing-minimum-explanation
        data-billing-minimum-applied="{{ $billingMinimumExplanation['applied'] ? '1' : '0' }}"
    >
        {{ $billingMinimumExplanation['label'] }}
        {{ $billingMinimumExplanation['mode'] === 'per_line' ? 'line' : 'RO' }} minimum
        · {{ $billingMinimumExplanation['required_hours'] }} hr required
        · {{ $billingMinimumExplanation['eligible_hours'] }} hr eligible
        @if ($billingMinimumExplanation['applied'])
            · +{{ $billingMinimumExplanation['shortfall_hours'] }} hr
            / ${{ number_format($billingMinimumExplanation['shortfall_cents'] / 100, 2) }} applied
        @else
            · no adjustment currently applied
        @endif
    </div>
@endif
