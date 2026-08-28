@php
    $searchCatalog = $searchCatalog ?? [];
    $featuredCommonProblems = $featuredCommonProblems ?? [];
@endphp

<section
    @class([
        'public-panel',
        $class ?? 'mb-6',
    ])
    aria-labelledby="vehicle-symptoms-heading"
    x-data="{
        query: '',
        catalog: {{ \Illuminate\Support\Js::from($searchCatalog) }},
        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (q === '') {
                return [];
            }

            return this.catalog.filter((problem) => {
                const title = String(problem.title || '').toLowerCase();
                const slug = String(problem.slug || '').toLowerCase();

                return title.includes(q) || slug.includes(q) || slug.replaceAll('-', ' ').includes(q);
            }).slice(0, 12);
        },
        get searching() {
            return this.query.trim() !== '';
        },
    }"
>
    <h2 id="vehicle-symptoms-heading" class="public-section-title">
        Common car problems
    </h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">
        Pick the issue closest to yours — what it might mean, whether you can keep driving, and when to come see us.
    </p>

    <label class="sr-only" for="common-problem-search">Search common car problems</label>
    <div class="public-problem-search mt-4">
        <input
            id="common-problem-search"
            type="search"
            x-model="query"
            autocomplete="off"
            placeholder="Search problems — brakes, overheating, P0171…"
            class="public-problem-search__input"
        >
    </div>

    <ul class="mt-4 grid gap-2 sm:grid-cols-2" x-show="!searching">
        @foreach ($featuredCommonProblems as $problem)
            <li>
                <a
                    href="{{ route('public.common-problems.show', $problem['slug']) }}"
                    data-public-surface-common-problem
                    data-public-surface-page="homepage"
                    data-public-surface-source="symptom_chooser"
                    data-public-surface-target="common-problems.{{ $problem['slug'] }}"
                    class="public-problem-link"
                >
                    <span class="public-problem-link__title">{{ $problem['title'] }}</span>
                    @if (filled($problem['card_teaser'] ?? null))
                        <span class="public-problem-link__teaser">{{ $problem['card_teaser'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <ul class="mt-4 grid gap-2 sm:grid-cols-2" x-show="searching" x-cloak>
        <template x-for="problem in filtered" :key="problem.slug">
            <li>
                <a
                    :href="problem.href"
                    data-public-surface-common-problem
                    data-public-surface-page="homepage"
                    data-public-surface-source="symptom_search"
                    :data-public-surface-target="'common-problems.' + problem.slug"
                    class="public-problem-link"
                >
                    <span class="public-problem-link__title" x-text="problem.title"></span>
                    <span class="public-problem-link__teaser" x-show="problem.teaser" x-text="problem.teaser"></span>
                </a>
            </li>
        </template>
    </ul>

    <p class="mt-3 text-sm text-slate-500" x-show="searching && filtered.length === 0" x-cloak>
        No matches — try another word, or
        <a href="{{ route('public.common-problems.index') }}" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">browse all problems</a>.
    </p>

    <p class="mt-4">
        <a
            href="{{ route('public.common-problems.index') }}"
            data-public-surface-common-problem
            data-public-surface-page="homepage"
            data-public-surface-source="view_all"
            data-public-surface-target="common-problems.index"
            class="inline-flex items-center text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
        >
            View all common car problems →
        </a>
    </p>
</section>
