@props([
    /** portal: 7/5 column split · public: 50/50 two-column */
    'variant' => 'portal',
    /** When true, the whole rail (form + supporting panels) sticks while the primary column scrolls. */
    'stickyRail' => true,
])

@php
    $variantClass = $variant === 'public'
        ? 'customer-page-split--public'
        : 'customer-page-split--portal';
@endphp

<div {{ $attributes->class(['customer-page-split', $variantClass, 'md:items-start']) }}>
    <div @class([
        'customer-page-split__primary customer-page-split__primary--scroll min-w-0',
    ])>
        {{ $primary ?? $slot }}
    </div>

    @if (isset($rail))
        <aside @class([
            'customer-page-split__rail min-w-0 space-y-6 md:self-start',
            'customer-page-split__rail--sticky' => $stickyRail,
        ])>
            {{ $rail }}
        </aside>
    @endif
</div>
