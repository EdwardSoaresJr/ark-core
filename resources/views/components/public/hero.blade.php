@props([
    'eyebrow' => 'Demo City independent repair',
    'title',
    'lede' => null,
])

<div {{ $attributes->class(['public-hero public-hero--page']) }}>
    @if (filled($eyebrow))
        <p class="public-hero__eyebrow">{{ $eyebrow }}</p>
    @endif

    <h1 class="public-hero__title">{{ $title }}</h1>

    @if (filled($lede))
        <p class="public-hero__lede">{{ $lede }}</p>
    @endif

    @if (isset($actions))
        <div class="public-hero__actions">
            {{ $actions }}
        </div>
    @endif
</div>
