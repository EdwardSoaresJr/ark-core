@php
    $closeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
@endphp

{{--
    Book is a route (/book) but presents as a viewport modal over the public homepage.
    Critical inline CSS + JS re-parent to <body> so this cannot fall into document flow
    under the page (stale CSS cache / containing-block bugs).
--}}
<x-public.lead-intake :seo="$seo" publicSurfacePage="book" :editorial-sections="true">
    <style>
        /* Critical: keep /book as a real popup even if hashed CSS is stale/cached. */
        .public-book-experience[data-public-book-overlay] {
            position: fixed !important;
            inset: 0 !important;
            z-index: 400 !important;
            display: flex !important;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
            margin: 0;
            min-height: 0 !important;
            max-height: none !important;
            background: transparent !important;
        }
        @media (min-width: 768px) {
            .public-book-experience[data-public-book-overlay] {
                align-items: center;
                padding: 1.5rem 1rem;
            }
        }
        .public-book-experience[data-public-book-overlay] .public-book-experience__backdrop {
            position: absolute !important;
            inset: 0 !important;
            border: 0;
            margin: 0;
            padding: 0;
            cursor: pointer;
            background: rgb(15 23 42 / 0.52) !important;
            -webkit-backdrop-filter: blur(5px);
            backdrop-filter: blur(5px);
        }
        body.public-book-overlay-open {
            overflow: hidden !important;
        }
        body.public-book-overlay-open .public-mobile-contact-bar {
            display: none !important;
        }
    </style>

    <div class="public-book-underlay" data-public-book-underlay aria-hidden="true">
        @include('partials.public.home-body')
    </div>

    <section
        class="public-book-experience"
        role="dialog"
        aria-modal="true"
        aria-label="Book an appointment"
        data-public-book-overlay
        data-close-url="{{ $closeUrl }}"
        style="position:fixed;inset:0;z-index:400;display:flex;align-items:center;justify-content:center;"
    >
        <button
            type="button"
            class="public-book-experience__backdrop"
            data-public-book-close
            aria-label="Close booking"
        ></button>

        <div class="public-book-experience__stage">
            <div class="public-book-experience__panel">
                @include('partials.public.book-experience-panel')
            </div>
        </div>
    </section>
</x-public.lead-intake>
