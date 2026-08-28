@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="common-problems.index">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <div class="public-cp-index">
                <header class="public-cp-identity">
                    <h1 class="public-page-title public-cp-title">Common car problems</h1>
                    <p class="public-page-lede public-cp-index__lede">
                        Straight answers when something does not feel right — what it might mean, whether you can keep driving, and how to reach us at {{ $shopName }}.
                    </p>
                    <p class="public-cp-index__lede-secondary">
                        Pick the symptom closest to yours. Each page walks through what we usually see, whether it is safe to drive, and how to book.
                    </p>
                </header>

                <section
                    class="public-content-section public-cp-index__catalog"
                    aria-labelledby="symptom-problems-heading"
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
                            }).slice(0, 16);
                        },
                        get searching() {
                            return this.query.trim() !== '';
                        },
                    }"
                >
                    <h2 id="symptom-problems-heading" class="public-section-title">What&apos;s wrong with your car?</h2>

                    <label class="sr-only" for="common-problem-index-search">Search common car problems</label>
                    <div class="public-problem-search mt-4">
                        <input
                            id="common-problem-index-search"
                            type="search"
                            x-model="query"
                            autocomplete="off"
                            placeholder="Search problems — brakes, overheating, P0171…"
                            class="public-problem-search__input"
                        >
                    </div>

                    <ul class="public-cp-index__list mt-4" x-show="!searching">
                        @foreach ($symptomProblems as $problem)
                            <li>
                                <a
                                    href="{{ route('public.common-problems.show', $problem['slug']) }}"
                                    data-public-surface-common-problem
                                    data-public-surface-page="common-problems.index"
                                    data-public-surface-source="symptom_list"
                                    data-public-surface-target="common-problems.{{ $problem['slug'] }}"
                                    class="public-cp-index__link"
                                >
                                    <span class="public-cp-index__link-title">{{ $problem['title'] }}</span>
                                    @if (filled($problem['card_teaser'] ?? null))
                                        <span class="public-cp-index__link-teaser">{{ $problem['card_teaser'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <ul class="public-cp-index__list mt-4" x-show="searching" x-cloak>
                        <template x-for="problem in filtered" :key="problem.slug">
                            <li>
                                <a
                                    :href="problem.href"
                                    data-public-surface-common-problem
                                    data-public-surface-page="common-problems.index"
                                    data-public-surface-source="symptom_search"
                                    :data-public-surface-target="'common-problems.' + problem.slug"
                                    class="public-cp-index__link"
                                >
                                    <span class="public-cp-index__link-title" x-text="problem.title"></span>
                                    <span class="public-cp-index__link-teaser" x-show="problem.teaser" x-text="problem.teaser"></span>
                                </a>
                            </li>
                        </template>
                    </ul>

                    <p class="mt-3 text-sm text-slate-500" x-show="searching && filtered.length === 0" x-cloak>
                        No matches — try another word, or browse services below.
                    </p>
                </section>

                @include('partials.public.common-problems-index-services')

                <p class="public-cp-quiet-proof">
                    @if ((float) ($googleRating ?? 0) > 0 && filled($googleReviewsUrl ?? null))
                        <a href="{{ $googleReviewsUrl }}" target="_blank" rel="noopener noreferrer" class="public-link">{{ number_format((float) $googleRating, 1) }} on Google</a>
                        <span aria-hidden="true"> · </span>
                    @endif
                    <a href="{{ route('public.warranty') }}" class="public-link">24/24 shop warranty</a>
                    <span aria-hidden="true"> · </span>
                    <a href="{{ route('public.repairpal.certified') }}" class="public-link">RepairPal Certified</a>
                </p>

                @include('partials.public.financing-inline-panel', [
                    'class' => 'public-cp-financing mt-8',
                ])

                <p class="public-cp-footer-note">
                    Colorado Springs independent repair. We test before we recommend parts.
                    <a href="{{ route('public.home') }}" class="public-link">Back to homepage</a>
                </p>
            </div>
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.public.common-problems-index-book')
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
