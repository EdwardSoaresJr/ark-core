@if (! empty($featuredMedia['url']))
    @php
        $gallery = $featuredMediaGallery ?? [];
        $galleryIndex = (int) ($featuredMedia['gallery_index'] ?? 0);
        $hasGallery = count($gallery) > 1;
    @endphp

    @if ($hasGallery)
        <figure
            @class(['public-featured-media', $class ?? 'mt-6'])
            x-data="arkFeaturedMediaGallery(@js($gallery), {{ $galleryIndex }})"
            @keydown.window="onKeydown($event)"
        >
            <button
                type="button"
                class="public-featured-media__trigger"
                @click="openAt({{ $galleryIndex }})"
                aria-label="View repair photos for this page"
            >
                @include('partials.public.common-problem-featured-media-hero-image', ['featuredMedia' => $featuredMedia])
            </button>

            @if (filled($featuredMedia['caption'] ?? null))
                <figcaption class="public-featured-media__caption">{{ $featuredMedia['caption'] }}</figcaption>
            @endif

            <div
                x-cloak
                x-show="open"
                x-transition.opacity.duration.150ms
                class="public-featured-media-lightbox"
                role="dialog"
                aria-modal="true"
                :aria-label="current()?.alt || 'Repair photo gallery'"
            >
                <button
                    type="button"
                    class="public-featured-media-lightbox__backdrop"
                    @click="close()"
                    aria-label="Close gallery"
                ></button>

                <div
                    class="public-featured-media-lightbox__frame"
                    @touchstart.passive="onTouchStart($event)"
                    @touchend.passive="onTouchEnd($event)"
                >
                    <div class="public-featured-media-lightbox__toolbar">
                        <p class="public-featured-media-lightbox__counter" x-text="counterLabel()"></p>
                        <button type="button" class="public-featured-media-lightbox__close" @click="close()">Close</button>
                    </div>

                    <div class="public-featured-media-lightbox__stage">
                        <button
                            type="button"
                            class="public-featured-media-lightbox__nav public-featured-media-lightbox__nav--prev"
                            @click="previous()"
                            aria-label="Previous photo"
                        >
                            ‹
                        </button>

                        <img
                            :src="current()?.url_large || current()?.url || ''"
                            :alt="current()?.alt || ''"
                            class="public-featured-media-lightbox__image"
                            loading="lazy"
                            decoding="async"
                            @click.stop
                        >

                        <button
                            type="button"
                            class="public-featured-media-lightbox__nav public-featured-media-lightbox__nav--next"
                            @click="next()"
                            aria-label="Next photo"
                        >
                            ›
                        </button>
                    </div>

                    <p
                        class="public-featured-media-lightbox__caption"
                        x-show="current()?.caption"
                        x-text="current()?.caption || ''"
                    ></p>
                </div>
            </div>
        </figure>
    @else
        <figure @class(['public-featured-media', $class ?? 'mt-6'])>
            @include('partials.public.common-problem-featured-media-hero-image', ['featuredMedia' => $featuredMedia])

            @if (filled($featuredMedia['caption'] ?? null))
                <figcaption class="public-featured-media__caption">{{ $featuredMedia['caption'] }}</figcaption>
            @endif
        </figure>
    @endif
@endif
