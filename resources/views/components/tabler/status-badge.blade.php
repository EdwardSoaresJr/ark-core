@props(['tone' => 'move'])

@php
    $color = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone($tone);
@endphp
<span {{ $attributes->class(['badge', "bg-{$color}-lt"]) }}>{{ $slot }}</span>
