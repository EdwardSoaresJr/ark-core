@props(['tone' => 'move', 'actions' => []])

@php
    $color = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone($tone);
@endphp
<article {{ $attributes->class(['card card-sm h-100']) }}>
    <div class="card-status-start bg-{{ $color }}"></div>
    <div class="card-body">
        {{ $slot }}
    </div>
    @if ($actions !== [])
        <div class="card-footer py-2">
            @include('operations.partials.tabler-index-card-actions', ['indexActions' => $actions])
        </div>
    @endif
</article>
