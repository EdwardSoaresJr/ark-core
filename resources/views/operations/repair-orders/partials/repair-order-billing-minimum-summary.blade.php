@php
    $roWideMinimums = app(\App\Ark\Operations\EstimatePricing\ApplyBillingClassMinimumsAction::class)
        ->repairOrderWideExplanations($repairOrder);
@endphp
@foreach ($roWideMinimums as $notice)
    <div
        @class([
            'small mb-2',
            'text-secondary' => ! $notice['applied'],
        ])
        role="status"
        data-billing-minimum-ro-summary
        data-billing-minimum-applied="{{ $notice['applied'] ? '1' : '0' }}"
    >
        @if ($notice['applied'])
            <span class="fw-medium">{{ $notice['label'] }} RO minimum applied</span>
            <span class="text-secondary">
                · {{ $notice['eligible_hours'] }} hr eligible of {{ $notice['required_hours'] }} hr
                · +{{ $notice['shortfall_hours'] }} hr
                / ${{ number_format($notice['shortfall_cents'] / 100, 2) }}
            </span>
        @else
            {{ $notice['label'] }} RO minimum met
            ({{ $notice['eligible_hours'] }} / {{ $notice['required_hours'] }} hr)
        @endif
    </div>
@endforeach
