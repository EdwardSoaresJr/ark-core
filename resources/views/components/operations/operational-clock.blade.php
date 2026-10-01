@props(['clock', 'variant' => 'panel'])

@php
    /** @var \App\Ark\Operations\Diagnostics\OperationalClockProjection $clock */
    $title = collect([
        'Server (PHP): '.($clock->phpIsUtc ? 'UTC' : $clock->phpDefaultTimezone),
        $clock->dbUtc !== null
            ? 'MySQL NOW(): '.($clock->dbMatchesUtc ? 'UTC (matches UTC_TIMESTAMP)' : 'differs from UTC_TIMESTAMP')
            : 'MySQL clock unavailable',
        'Shop display: '.$clock->shopTimezone,
    ])->join(' · ');

    $rootClass = $variant === 'topbar' ? 'ops-topbar-clock' : 'ops-runtime-health-clock';
@endphp

<div
    {{ $attributes->class([$rootClass]) }}
    title="{{ $title }}"
    x-data="arkOperationalClock(@js($clock->toArray()))"
    x-init="init()"
    aria-label="Operational clocks"
>
    <span class="{{ $rootClass }}__segment" :class="{ '{{ $rootClass }}__segment--warn': !phpIsUtc }">
        <span class="{{ $rootClass }}__label">UTC</span>
        <span class="{{ $rootClass }}__value" x-text="serverUtc"></span>
    </span>
    <span class="{{ $rootClass }}__sep" aria-hidden="true">·</span>
    <span
        class="{{ $rootClass }}__segment"
        :class="{ '{{ $rootClass }}__segment--warn': dbAvailable && !dbMatchesUtc }"
    >
        <span class="{{ $rootClass }}__label">DB</span>
        <span class="{{ $rootClass }}__value" x-text="dbUtc"></span>
    </span>
    <span class="{{ $rootClass }}__sep" aria-hidden="true">·</span>
    <span class="{{ $rootClass }}__segment {{ $rootClass }}__segment--shop">
        <span class="{{ $rootClass }}__label">{{ $clock->shopShortLabel }}</span>
        <span class="{{ $rootClass }}__value" x-text="shopLocal"></span>
        <span class="{{ $rootClass }}__abbr" x-text="shopAbbr"></span>
    </span>
</div>
