<x-cloud.shell title="Features">
    <div class="mx-auto max-w-5xl px-5 sm:px-8 py-16 sm:py-20 cloud-stage">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">What makes Tuesday easier</p>
        <h1 class="cloud-display mt-3 text-4xl sm:text-5xl font-semibold max-w-2xl leading-tight">
            Everything your shop needs. Nothing you don’t.
        </h1>
        <p class="mt-5 max-w-xl text-lg text-[var(--cloud-muted)]">
            Built for independent shops that need to move fast — not learn a new vocabulary.
        </p>

        <ul class="mt-14 grid gap-6 sm:grid-cols-2">
            @foreach ([
                ['Repair orders & estimates', 'Write the job, price the work, and keep the car moving without switching tools.'],
                ['Customer updates', 'Approvals, texts, and “is it ready?” answers in one place — without the chaos.'],
                ['Inspections at the car', 'Capture findings and turn them into work the counter can sell.'],
                ['Your shop online', 'A website tied to the work you actually do.'],
                ['Approvals & payments', 'Send estimates and collect payment without phone tag.'],
                ['Room to grow', 'Add people and locations without starting over.'],
            ] as [$title, $blurb])
                <li class="rounded-2xl border border-[var(--cloud-line)] bg-white/80 p-6">
                    <p class="cloud-display text-xl font-semibold">{{ $title }}</p>
                    <p class="mt-2 text-[var(--cloud-muted)] leading-relaxed">{{ $blurb }}</p>
                </li>
            @endforeach
        </ul>

        <div class="mt-12 flex flex-wrap gap-3">
            <a href="{{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaUrl() }}" class="cloud-btn-primary">
                {{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaLabel() }}
            </a>
            <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}#product" class="cloud-btn-ghost">See the product</a>
        </div>
    </div>
</x-cloud.shell>
