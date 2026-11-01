@props(['tone' => 'move'])

@php
    $color = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone($tone);
@endphp
{{-- bg-dark-lt forces near-black text, unreadable in the dark theme, so the slate tone is an outline badge. --}}
<span {{ $attributes->class(['badge', "bg-{$color}-lt" => $color !== 'dark', 'badge-outline text-secondary' => $color === 'dark']) }}>{{ $slot }}</span>
