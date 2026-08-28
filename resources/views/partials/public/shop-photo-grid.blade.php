@props([
    /** @var list<array{url: string, alt: string}> $photos */
    'photos' => [],
    'class' => 'mt-8 grid grid-cols-2 gap-2 sm:grid-cols-4',
])

@if ($photos !== [])
    <div {{ $attributes->class([$class]) }}>
        @foreach ($photos as $photo)
            <button
                type="button"
                class="public-shop-photo"
                data-ops-lightbox="{{ $photo['url'] }}"
                data-ops-lightbox-alt="{{ $photo['alt'] }}"
                aria-label="View larger photo: {{ $photo['alt'] }}"
            >
                <img
                    src="{{ $photo['url'] }}"
                    alt="{{ $photo['alt'] }}"
                    class="public-shop-photo__image"
                    loading="lazy"
                >
            </button>
        @endforeach
    </div>
@endif
