<x-cloud.shell title="Resources">
    <div class="mx-auto max-w-5xl px-5 sm:px-8 py-16 sm:py-20 cloud-stage">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Resources</p>
        <h1 class="cloud-display mt-3 text-4xl sm:text-5xl font-semibold max-w-2xl leading-tight">
            Built for the floor.
        </h1>
        <p class="mt-5 max-w-xl text-lg text-[var(--cloud-muted)]">
            Written for owners who already have enough on their plate — not for a software committee.
        </p>

        <div class="mt-14 space-y-6">
            @foreach ([
                ['Getting started', 'Create your shop → invite your team → write your first repair order → help your first customer. Usually under five minutes.'],
                ['Built for the floor', 'Who needs attention, who’s waiting, what’s next on the car — the questions advisors already ask out loud.'],
                ['Your data stays yours', 'Your customers, vehicles, and repair history belong to your shop — not locked inside someone else’s inbox.'],
            ] as [$title, $blurb])
                <div class="rounded-2xl border border-[var(--cloud-line)] bg-white/80 p-7">
                    <p class="cloud-display text-xl font-semibold">{{ $title }}</p>
                    <p class="mt-2 text-[var(--cloud-muted)] leading-relaxed max-w-2xl">{{ $blurb }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-wrap gap-3">
            <a href="{{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaUrl() }}" class="cloud-btn-primary">
                {{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaLabel() }}
            </a>
            <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}#product" class="cloud-btn-ghost">See how it works</a>
        </div>
    </div>
</x-cloud.shell>
