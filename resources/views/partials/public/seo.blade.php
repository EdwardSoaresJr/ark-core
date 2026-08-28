@php
    /** @var array{title?: string, description?: string, canonical?: string, robots?: string|null, og?: array<string, mixed>, twitter?: array<string, mixed>, json_ld?: list<array<string, mixed>>} $seo */
@endphp

@if (! empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
@endif

@if (! empty($seo['canonical']))
    <link rel="canonical" href="{{ $seo['canonical'] }}">
@endif

@if (filled($seo['robots'] ?? null))
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif

@if (! empty($seo['og']))
    <meta property="og:title" content="{{ $seo['og']['title'] ?? '' }}">
    <meta property="og:description" content="{{ $seo['og']['description'] ?? '' }}">
    <meta property="og:url" content="{{ $seo['og']['url'] ?? '' }}">
    <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}">
    @if (! empty($seo['og']['site_name']))
        <meta property="og:site_name" content="{{ $seo['og']['site_name'] }}">
    @endif
    @if (filled($seo['og']['image'] ?? null))
        <meta property="og:image" content="{{ $seo['og']['image'] }}">
    @endif
@endif

@if (! empty($seo['twitter']))
    <meta name="twitter:card" content="{{ $seo['twitter']['card'] ?? 'summary_large_image' }}">
    <meta name="twitter:title" content="{{ $seo['twitter']['title'] ?? '' }}">
    <meta name="twitter:description" content="{{ $seo['twitter']['description'] ?? '' }}">
    @if (filled($seo['twitter']['image'] ?? null))
        <meta name="twitter:image" content="{{ $seo['twitter']['image'] }}">
    @endif
@endif

@foreach ($seo['json_ld'] ?? [] as $schema)
    <script type="application/ld+json">@json($schema)</script>
@endforeach
