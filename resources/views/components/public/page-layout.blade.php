@props([
    /** editorial: full-width stacked sections · content-rail: article + sticky action card */
    'variant' => 'editorial',
])

@if ($variant === 'content-rail')
    <div {{ $attributes->class(['public-page-layout public-page-layout--content-rail']) }}>
        <div class="public-page-layout__main">
            {{ $main ?? $slot }}
        </div>
        @if (isset($rail))
            <aside class="public-page-layout__rail">
                {{ $rail }}
            </aside>
        @endif
    </div>
@else
    <div {{ $attributes->class(['public-page-layout public-page-layout--editorial']) }}>
        {{ $slot }}
    </div>
@endif
