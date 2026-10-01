@php
    $laborAuthority = App\Ark\Operations\Labor\LaborLinePresenter::forLine($line);
@endphp

@if ($laborAuthority)
    @if ($leadingSeparator ?? true)
        <span class="text-slate-300">·</span>
    @endif
    @include('operations.repair-orders.partials.repair-order-labor-category-badge', ['line' => $line])

    @if ($laborAuthority['minimum_message'])
        <span class="text-slate-300">·</span>
        <span class="font-semibold text-amber-800">{{ $laborAuthority['minimum_message'] }}</span>
    @endif

    @if ($laborAuthority['adjustment'] !== 'normal' || $laborAuthority['hours_overridden'])
        <span class="text-slate-300">·</span>
        <span>
            Book {{ $laborAuthority['book_hours'] }} hr
            @if ($laborAuthority['adjustment'] !== 'normal')
                · {{ $laborAuthority['adjustment_label'] }}
                @if ($laborAuthority['reason'])
                    · {{ $laborAuthority['reason'] }}
                @endif
            @endif
            @if ($laborAuthority['hours_overridden'])
                · Override {{ $laborAuthority['final_hours'] }} hr
                @if ($laborAuthority['override_reason'])
                    · {{ $laborAuthority['override_reason'] }}
                @endif
            @endif
        </span>
    @endif
@endif
