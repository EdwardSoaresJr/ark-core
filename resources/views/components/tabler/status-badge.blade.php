@props(['tone' => 'move'])

@php
    $color = \App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor::tablerColorForChipTone($tone);
    $isDark = $color === 'dark';
    $isBuilding = $color === 'building';
@endphp
{{--
  dark: outline secondary (gray) — bg-dark-lt forces near-black text in the dark theme.
  building: theme-aware white (dark UI) / dark-neutral (light UI) via shared .bg-building-lt.
--}}
<span {{ $attributes->class([
    'badge',
    "bg-{$color}-lt" => ! $isDark && ! $isBuilding,
    'badge-outline text-secondary' => $isDark,
    'bg-building-lt text-building' => $isBuilding,
]) }}>{{ $slot }}</span>
