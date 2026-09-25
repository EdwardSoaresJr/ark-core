<x-website.layout :website="$website" :seo="$seo" page="financing">
    <div class="public-canvas">
        <div class="public-canvas__prose">
            <h1 class="public-page-title">Financing for unexpected repairs</h1>
            @if ($website->financingLede() !== '')
                <p class="public-page-lede">{{ $website->financingLede() }}</p>
            @endif
            <section class="public-content-section">
                <h2>How it works</h2>
                <p>We inspect the car and show you the estimate. If you want financing, we walk you through the options that fit that repair. Not every job qualifies. Approval and terms depend on the program.</p>
            </section>
        </div>
        @if ($website->financingPrograms() !== [])
            <section class="public-content-section">
                <h2>Programs</h2>
                <div class="public-financing__programs">
                    @foreach ($website->financingPrograms() as $program)
                        <article class="public-financing__program">
                            <h3>{{ $program['name'] }}</h3>
                            @if ($program['body'] !== '')
                                <p>{{ $program['body'] }}</p>
                            @endif
                            @if ($program['url'] !== '')
                                <p><a class="public-link" href="{{ $program['url'] }}">{{ $program['name'] }}</a></p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-website.layout>
