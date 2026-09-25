<x-website.layout :website="$website" :seo="$seo" page="financing">
    <article class="public-static-page">
        <h1 class="public-page-title">Financing for unexpected repairs</h1>
        @if ($website->financingLede() !== '')
            <p class="public-page-lede">{{ $website->financingLede() }}</p>
        @endif
        <section class="public-content-section mt-6">
            <h2>How it works</h2>
            <p>We inspect the car and show you the estimate. If you want financing, we walk you through the options that fit that repair. Not every job qualifies. Approval and terms depend on the program.</p>
        </section>
        @if ($website->financingPrograms() !== [])
            <section class="public-content-section">
                <h2>Programs</h2>
                <ul>
                    @foreach ($website->financingPrograms() as $program)
                        <li class="mt-4">
                            <p class="font-semibold">{{ $program['name'] }}</p>
                            @if ($program['body'] !== '')
                                <p>{{ $program['body'] }}</p>
                            @endif
                            @if ($program['url'] !== '')
                                <p><a class="public-link" href="{{ $program['url'] }}">{{ $program['name'] }}</a></p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-website.layout>
