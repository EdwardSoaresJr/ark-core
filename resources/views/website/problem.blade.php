<x-website.layout :website="$website" :seo="$seo" :page="'common-problems.'.($problem['slug'] ?? '')">
    @if ($presentation === null)
        <article class="public-cp-article">
            <h1 class="public-cp-title public-page-title">{{ $problem['title'] ?? '' }}</h1>
            @if (! empty($problem['problem']))
                <p class="public-cp-meaning">{{ $problem['problem'] }}</p>
            @endif

            @if (! empty($problem['symptoms']))
                <section class="public-content-section mt-6">
                    <h2>Symptoms</h2>
                    <ul>
                        @foreach ($problem['symptoms'] as $symptom)
                            <li>{{ $symptom }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['can_drive']))
                <section class="public-content-section mt-6">
                    <h2>{{ $problem['can_drive_heading'] ?? 'Can I keep driving?' }}</h2>
                    <ul>
                        @foreach ($problem['can_drive'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['common_causes']))
                <section class="public-content-section mt-6">
                    <h2>Common causes</h2>
                    <ul>
                        @foreach ($problem['common_causes'] as $cause)
                            <li>{{ $cause }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['often_confused_with']))
                <section class="public-content-section mt-6">
                    <h2>Often confused with</h2>
                    <ul>
                        @foreach ($problem['often_confused_with'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['if_you_ignore']))
                <section class="public-content-section mt-6">
                    <h2>If you ignore it</h2>
                    <ul>
                        @foreach ($problem['if_you_ignore'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['repair_overview']))
                <section class="public-content-section mt-6">
                    <h2>Repair overview</h2>
                    <ul>
                        @foreach ($problem['repair_overview'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['what_happens_next']))
                <section class="public-content-section mt-6">
                    <h2>What happens next</h2>
                    <ul>
                        @foreach ($problem['what_happens_next'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($problem['faq']))
                <section class="public-content-section mt-6">
                    <h2>Questions</h2>
                    @foreach ($problem['faq'] as $item)
                        @if (! empty($item['question']))
                            <h3 class="mt-4 font-semibold">{{ $item['question'] }}</h3>
                            <p>{{ $item['answer'] ?? '' }}</p>
                        @endif
                    @endforeach
                </section>
            @endif

            @if (! empty($problem['related_problem_slugs']))
                <section class="public-content-section mt-6">
                    <h2>Related</h2>
                    <ul>
                        @foreach ($problem['related_problem_slugs'] as $related)
                            @php $relatedProblem = $website->problem($related); @endphp
                            @if ($relatedProblem !== null)
                                <li><a class="public-link" href="{{ route('public.common-problems.show', $related) }}">{{ $relatedProblem['title'] ?? $related }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endif

            <p class="mt-6">
                <a class="public-cta public-cta--primary" href="{{ route('public.book', array_filter(['concern' => $problem['concern_prefill'] ?? null])) }}">Request an appointment</a>
            </p>
        </article>
    @else
        @php
            $relatedProblems = [];
            foreach ($problem['related_problem_slugs'] ?? [] as $relatedSlug) {
                $relatedProblem = $website->problem($relatedSlug);
                if ($relatedProblem !== null) {
                    $relatedProblems[] = [
                        'href' => route('public.common-problems.show', $relatedSlug),
                        'label' => $relatedProblem['title'] ?? $relatedSlug,
                    ];
                }
            }
            $concern = trim((string) ($problem['concern_prefill'] ?? ''));
            $bookHref = $concern !== ''
                ? route('public.book', ['concern' => $concern])
                : route('public.book');
        @endphp
        <div class="public-service public-problem">
            <div class="public-service__layout">
                <article class="public-cp-article">
                    <h1 class="public-cp-title public-page-title">{{ $problem['title'] ?? '' }}</h1>
                    @if (! empty($problem['problem']))
                        <p class="public-cp-meaning">{{ $problem['problem'] }}</p>
                    @endif

                    @foreach (\App\Ark\Website\PublicProblemPresentation::sections($presentation) as $section)
                        @php
                            $items = $problem[$section['key']] ?? [];
                            $items = is_array($items) ? $items : [];
                        @endphp
                        @if ($items !== [])
                            <section class="public-content-section">
                                <h2>{{ $section['key'] === 'can_drive' ? ($problem['can_drive_heading'] ?? 'Can I keep driving?') : $section['heading'] }}</h2>
                                <ul class="public-bullet-list">
                                    @foreach ($items as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach

                    @if (! empty($problem['faq']))
                        <section class="public-content-section">
                            <h2>Questions</h2>
                            @foreach ($problem['faq'] as $item)
                                @if (! empty($item['question']))
                                    <h3 class="mt-4 font-semibold">{{ $item['question'] }}</h3>
                                    <p>{{ $item['answer'] ?? '' }}</p>
                                @endif
                            @endforeach
                        </section>
                    @endif
                </article>

                <aside class="public-service__rail">
                    @if ($serviceLinks !== [])
                        <section class="public-service__related public-problem__service">
                            <h2>Related service</h2>
                            @foreach ($serviceLinks as $link)
                                <p><a class="public-link" href="{{ $link['href'] }}">{{ $link['label'] }}</a></p>
                                @if ($link['note'] !== '')
                                    <p class="public-problem__note">{{ $link['note'] }}</p>
                                @endif
                            @endforeach
                        </section>
                    @endif

                    @if ($relatedProblems !== [])
                        <section class="public-service__related">
                            <h2>{{ \App\Ark\Website\PublicProblemPresentation::relatedHeading($presentation) }}</h2>
                            <ul class="public-related-links">
                                @foreach ($relatedProblems as $link)
                                    <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <p class="public-service__rail-action">
                        <a class="public-cta public-cta--primary" href="{{ $bookHref }}">Request an appointment</a>
                    </p>
                </aside>
            </div>

            <section class="public-service__close">
                <h2 class="public-section-title">{{ \App\Ark\Website\PublicProblemPresentation::closeHeading($presentation) }}</h2>
                <p class="public-page-lede">{{ \App\Ark\Website\PublicProblemPresentation::closeLede($presentation) }}</p>
                <p class="public-service__close-action">
                    <a class="public-cta public-cta--primary" href="{{ $bookHref }}">Request an appointment</a>
                </p>
            </section>
        </div>
    @endif
</x-website.layout>
