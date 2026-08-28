@php
    /**
     * Editorial discovery — typography, not a chip wall.
     * Homepage symptoms open Book with concern context (same modal as Book CTA).
     * Full Common Problems index remains for browsing / SEO pages.
     *
     * @var list<array{label: string, concern: string}> $editorialLinks
     */
    $editorialLinks = [
        ['label' => 'Check engine light', 'concern' => 'Check Engine Light'],
        ['label' => 'Noise or vibration', 'concern' => 'Strange Noise'],
        ['label' => 'Car won’t start', 'concern' => 'Car won’t start'],
        ['label' => 'Overheating', 'concern' => 'Overheating'],
        ['label' => 'Electrical problem', 'concern' => 'Electrical'],
        ['label' => 'Something else', 'concern' => 'Something Else'],
    ];
    $servicesIndexUrl = route('public.common-problems.index').'#local-services-heading';
@endphp

<section class="public-home-problems" aria-labelledby="public-home-symptoms-heading">
    <h2 id="public-home-symptoms-heading" class="public-home-problems__title">
        Not sure what’s wrong?
    </h2>
    <p class="public-home-problems__lede">
        Pick the closest match. We’ll start your request with that.
    </p>

    <ul class="public-home-problems__list">
        @foreach ($editorialLinks as $link)
            <li>
                <a
                    href="{{ route('public.book', ['concern' => $link['concern']]) }}"
                    data-public-surface-page="homepage"
                    data-public-surface-source="symptom_chooser"
                    data-public-surface-target="book.{{ \Illuminate\Support\Str::slug($link['concern']) }}"
                    class="public-home-problems__link"
                >
                    {{ $link['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <p class="public-home-problems__actions">
        <a
            href="{{ route('public.common-problems.index') }}"
            data-public-surface-common-problem
            data-public-surface-page="homepage"
            data-public-surface-source="view_all"
            data-public-surface-target="common-problems.index"
            class="public-home-problems__view-all"
        >
            View all problems
        </a>
        <a
            href="{{ $servicesIndexUrl }}"
            data-public-surface-common-problem
            data-public-surface-page="homepage"
            data-public-surface-source="view_all_local_services"
            data-public-surface-target="common-problems.index"
            class="public-home-problems__services"
        >
            Looking for a specific service? View Auto Repair Services
        </a>
    </p>
</section>
