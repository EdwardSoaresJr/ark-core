@php
    $featuredLocalServices = $featuredLocalServices ?? [];
    $localServicesSearchCatalog = $localServicesSearchCatalog ?? [];
    $surfacePage = $surfacePage ?? 'homepage';
@endphp

<section
    @class([
        'public-panel',
        $class ?? 'mt-8',
    ])
    aria-labelledby="local-services-heading"
    x-data="{
        query: '',
        catalog: {{ \Illuminate\Support\Js::from($localServicesSearchCatalog) }},
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (q === '') {
                return [];
            }

            return this.catalog.filter((service) => {
                const title = String(service.title || '').toLowerCase();
                const slug = String(service.slug || '').toLowerCase();
                const teaser = String(service.teaser || '').toLowerCase();

                return title.includes(q) || teaser.includes(q) || slug.includes(q) || slug.replaceAll('-', ' ').includes(q);
            }).slice(0, 12);
        },
        get searching() {
            return this.query.trim() !== '';
        },
    }"
>
    <h2 id="local-services-heading" class="public-section-title">
        {{ $heading ?? 'Auto repair in Demo City' }}
    </h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">
        {{ $lede ?? 'Looking for a shop, diagnostics, brakes, or maintenance — not just a symptom guide? Start here.' }}
    </p>

    <label class="sr-only" for="local-services-search">Search auto repair and services</label>
    <div class="public-problem-search mt-4">
        <input
            id="local-services-search"
            type="search"
            x-model="query"
            autocomplete="off"
            placeholder="Search services — brakes, diagnostics, Audi…"
            class="public-problem-search__input"
        >
    </div>

    <ul class="mt-4 grid gap-2 sm:grid-cols-2" x-show="!searching">
        @foreach ($featuredLocalServices as $service)
            <li>
                <a
                    href="{{ $service['href'] ?? route('public.common-problems.show', $service['slug']) }}"
                    data-public-surface-common-problem
                    data-public-surface-page="{{ $surfacePage }}"
                    data-public-surface-source="local_services"
                    data-public-surface-target="common-problems.{{ $service['slug'] }}"
                    class="public-problem-link"
                >
                    <span class="public-problem-link__title">{{ $service['title'] }}</span>
                    @if (filled($service['card_teaser'] ?? null))
                        <span class="public-problem-link__teaser">{{ $service['card_teaser'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <ul class="mt-4 grid gap-2 sm:grid-cols-2" x-show="searching" x-cloak>
        <template x-for="service in filtered" :key="service.slug">
            <li>
                <a
                    :href="service.href"
                    data-public-surface-common-problem
                    data-public-surface-page="{{ $surfacePage }}"
                    data-public-surface-source="local_services_search"
                    :data-public-surface-target="'common-problems.' + service.slug"
                    class="public-problem-link"
                >
                    <span class="public-problem-link__title" x-text="service.title"></span>
                    <span class="public-problem-link__teaser" x-show="service.teaser" x-text="service.teaser"></span>
                </a>
            </li>
        </template>
    </ul>

    <p class="mt-3 text-sm text-slate-500" x-show="searching && filtered.length === 0" x-cloak>
        No matches — try another word, or
        <a href="{{ route('public.common-problems.index') }}#local-services-heading" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">browse all services</a>.
    </p>

    @if ($showViewAll ?? true)
        <p class="mt-4">
            <a
                href="{{ route('public.common-problems.index') }}#local-services-heading"
                data-public-surface-common-problem
                data-public-surface-page="{{ $surfacePage }}"
                data-public-surface-source="view_all_local_services"
                data-public-surface-target="common-problems.index"
                class="inline-flex items-center text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
            >
                View all auto repair &amp; services →
            </a>
        </p>
    @endif
</section>
