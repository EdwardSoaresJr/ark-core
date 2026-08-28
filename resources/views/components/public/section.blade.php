@props([
    /** white | muted | trust | accent */
    'tone' => 'white',
    'title' => null,
    'lede' => null,
    'id' => null,
])

<section
    @if (filled($id)) id="{{ $id }}" @endif
    {{ $attributes->class([
        'public-page-section',
        'public-page-section--'.$tone,
    ]) }}
>
    <div class="public-page-section__inner customer-page-inset">
        @if (filled($title) || filled($lede))
            <header class="public-page-section__header">
                @if (filled($title))
                    <h2 class="public-page-section__title">{{ $title }}</h2>
                @endif
                @if (filled($lede))
                    <p class="public-page-section__lede">{{ $lede }}</p>
                @endif
            </header>
        @endif

        {{ $slot }}
    </div>
</section>
