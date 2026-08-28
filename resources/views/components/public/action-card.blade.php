@props([
    'heading' => 'What\'s going on?',
    'subheading' => 'We\'ll take a look and reach out with the next best step.',
    /** hero: inline under hero · rail: sticky sidebar */
    'placement' => 'rail',
    'staged' => false,
])

<div {{ $attributes->class([
    'public-action-card',
    'public-action-card--'.$placement,
    'public-action-card--staged' => $staged,
]) }}>
    <div class="public-action-card__header">
        <h2 class="public-action-card__title">{{ $heading }}</h2>
        @if (filled($subheading))
            <p class="public-action-card__lede">{{ $subheading }}</p>
        @endif
    </div>

    <div class="public-action-card__body">
        {{ $slot }}
    </div>
</div>
