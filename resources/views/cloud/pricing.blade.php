<x-cloud.shell title="Pricing">
    <div class="mx-auto max-w-5xl px-5 sm:px-8 py-16 sm:py-20 cloud-stage">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Pricing</p>
        <h1 class="cloud-display mt-3 text-4xl sm:text-5xl font-semibold max-w-2xl leading-tight">
            Simple pricing. No software handshake required.
        </h1>
        <p class="mt-5 max-w-xl text-lg text-[var(--cloud-muted)]">
            Start free. Run real work. Decide when you’re ready.
        </p>

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
            @foreach ([
                [
                    'name' => 'Trial',
                    'price' => 'Free',
                    'period' => '14 days',
                    'blurb' => 'Create your shop. Write real repair orders. Try it before you pay.',
                    'cta' => 'Start Free Trial',
                    'href' => \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop'),
                    'featured' => true,
                ],
                [
                    'name' => 'Shop',
                    'price' => '$299',
                    'period' => '/ month',
                    'blurb' => 'Repairs, customer communication, website, and portal for one location.',
                    'cta' => 'Start Free Trial',
                    'href' => \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop'),
                    'featured' => false,
                ],
                [
                    'name' => 'Multi-Location',
                    'price' => 'Talk to us',
                    'period' => '',
                    'blurb' => 'For shops running more than one location — same product, room to grow.',
                    'cta' => 'See how it works',
                    'href' => \App\Ark\Platform\Cloud\CloudUrls::route('demo'),
                    'featured' => false,
                ],
            ] as $plan)
                <div @class([
                    'rounded-2xl p-7 sm:p-8 border',
                    'border-[var(--cloud-cerulean)] bg-white shadow-[0_24px_60px_-36px_rgba(0,153,204,0.55)]' => $plan['featured'],
                    'border-[var(--cloud-line)] bg-white/70' => ! $plan['featured'],
                ])>
                    <p class="cloud-display text-xl font-semibold">{{ $plan['name'] }}</p>
                    <p class="mt-4 flex items-baseline gap-1">
                        <span class="cloud-display text-4xl font-semibold">{{ $plan['price'] }}</span>
                        @if ($plan['period'] !== '')
                            <span class="text-[var(--cloud-muted)]">{{ $plan['period'] }}</span>
                        @endif
                    </p>
                    <p class="mt-4 text-[var(--cloud-muted)] leading-relaxed">{{ $plan['blurb'] }}</p>
                    <a
                        href="{{ $plan['href'] }}"
                        @class([
                            'mt-8 w-full',
                            'cloud-btn-primary' => $plan['featured'],
                            'cloud-btn-ghost' => ! $plan['featured'],
                        ])
                    >
                        {{ $plan['cta'] }}
                    </a>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-sm text-[var(--cloud-muted)]">
            Start free. No contract required to try it on a real Tuesday.
        </p>
    </div>
</x-cloud.shell>
