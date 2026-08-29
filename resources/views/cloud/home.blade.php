@php
    $ctaUrl = \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaUrl();
    $ctaLabel = \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaLabel();
    $signupsOpen = \App\Ark\Platform\Cloud\CloudPublicPosture::signupsOpen();
    $pricingPublic = \App\Ark\Platform\Cloud\CloudPublicPosture::pricingPublic();
    $pricing = \App\Ark\Platform\Cloud\CloudUrls::route('pricing');
@endphp

<x-cloud.shell title="Shop management for independent repair shops" :wide="true">
    {{-- 1. Hero — outcome first --}}
    <section class="cloud-hero relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <img
                src="{{ asset('assets/cloud/product/shop-bay.webp') }}"
                alt=""
                class="h-full w-full object-cover opacity-35"
                width="1600"
                height="900"
            >
            <div class="absolute inset-0 cloud-hero-glow opacity-95" aria-hidden="true"></div>
        </div>
        <div class="relative mx-auto max-w-6xl px-5 sm:px-8 pt-20 pb-16 sm:pt-28 sm:pb-20 cloud-stage">
            <p class="cloud-display text-sm font-semibold tracking-[0.18em] uppercase mb-6" style="color: rgba(255,255,255,0.7)">
                Built for the floor
            </p>
            <h1 class="cloud-display text-4xl sm:text-5xl lg:text-[3.65rem] font-semibold max-w-3xl leading-[1.05]" style="color: #fff">
                Run your shop with confidence.
            </h1>
            <p class="mt-8 max-w-2xl text-lg sm:text-xl leading-relaxed" style="color: rgba(255,255,255,0.82)">
                Estimates, inspections, customer communication, AI, and your website—all working together
                so you can spend less time managing software and more time running your shop.
            </p>
            <div class="mt-12 flex flex-wrap items-center gap-4">
                <a href="{{ $ctaUrl }}" data-cloud-event="cloud_funnel_homepage_cta" class="cloud-btn-primary text-lg !px-9 !py-4">
                    {{ $ctaLabel }}
                </a>
                <a href="#product" class="cloud-btn-ghost cloud-btn-ghost-on-dark !px-7 !py-3.5">
                    See the product
                </a>
            </div>
            <p class="mt-5 text-sm" style="color: rgba(255,255,255,0.55)">
                @if ($signupsOpen)
                    Free trial · no credit card to start
                @else
                    Prefer not to run your own server? We’ll host ARK for you.
                @endif
            </p>
        </div>
    </section>

    {{-- 2. Product dominates — screenshot before belief --}}
    <section id="product" class="relative bg-[var(--cloud-ink)] pt-4 pb-16 sm:pb-24">
        <div class="mx-auto max-w-[92rem] px-2 sm:px-4 lg:px-6 -mt-10 sm:-mt-14 cloud-stage" style="animation-delay: 80ms">
            <figure class="relative overflow-hidden rounded-lg sm:rounded-2xl border border-white/10 shadow-[0_50px_120px_-28px_rgba(0,0,0,0.85)]">
                <img
                    src="{{ asset('assets/cloud/product/repair-order-live.png') }}"
                    alt="Live ARK repair order workspace from an independent shop"
                    class="w-full h-auto min-h-[280px] sm:min-h-[420px] lg:min-h-[560px] object-cover object-top"
                    width="1600"
                    height="1000"
                >
                <div class="pointer-events-none absolute inset-0 hidden xl:block" aria-hidden="true">
                    <div class="absolute top-[10%] left-[2.5%] max-w-[12rem] rounded-lg bg-white/95 px-3.5 py-2.5 text-sm font-semibold text-[var(--cloud-ink)] shadow-lg">
                        Who’s here and what’s waiting
                    </div>
                    <div class="absolute top-[42%] right-[2.5%] max-w-[12rem] rounded-lg bg-white/95 px-3.5 py-2.5 text-sm font-semibold text-[var(--cloud-ink)] shadow-lg">
                        The estimate you can explain
                    </div>
                    <div class="absolute bottom-[12%] left-[6%] max-w-[13rem] rounded-lg bg-[var(--cloud-cerulean)] px-3.5 py-2.5 text-sm font-semibold text-white shadow-lg">
                        What to do next on this car
                    </div>
                </div>
            </figure>
            <p class="mt-6 text-center text-sm sm:text-base" style="color: rgba(255,255,255,0.5)">
                Real ARK workspace — staged shop data, not a design mockup.
            </p>
        </div>
    </section>

    {{-- 3. Why shops switch — floor language + proof --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 py-24 sm:py-32">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Why shops switch</p>
        <h2 class="cloud-display mt-4 text-3xl sm:text-5xl font-semibold text-[var(--cloud-ink)] max-w-3xl leading-tight">
            A busy Tuesday shouldn’t feel like chaos.
        </h2>
        <p class="mt-5 max-w-2xl text-[var(--cloud-muted)] text-xl leading-relaxed">
            You’re not buying software. You’re buying a calmer morning, fewer callbacks,
            and a team that can keep up without another hire.
        </p>
        <ul class="mt-14 grid gap-5 sm:grid-cols-2">
            @foreach ([
                ['I’m behind before lunch.', 'Repair orders, approvals, and follow-ups stack up while the phone keeps ringing.'],
                ['Customers chase updates.', 'They shouldn’t have to. Status, estimates, and pickup should be obvious.'],
                ['Tools fight each other.', 'One place for the car, the customer, and what’s next — not five tabs.'],
                ['The floor needs the truth.', 'Built beside real bays, for advisors and techs who already have enough to do.'],
            ] as [$line, $detail])
                <li class="rounded-2xl border border-[var(--cloud-line)] bg-white/85 px-7 py-7">
                    <p class="cloud-display text-xl sm:text-2xl font-semibold text-[var(--cloud-ink)]">{{ $line }}</p>
                    <p class="mt-3 text-[var(--cloud-muted)] text-lg leading-relaxed">{{ $detail }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Evidence — large product proof, less explanation --}}
    <section class="border-y border-[var(--cloud-line)] bg-white/55">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 py-24 sm:py-32 space-y-28 sm:space-y-36">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Job board</p>
                <h2 class="cloud-display mt-4 text-3xl sm:text-4xl font-semibold leading-tight max-w-2xl">
                    Built for the floor — not a boardroom demo.
                </h2>
                <figure class="mt-10 overflow-hidden rounded-2xl border border-[var(--cloud-line)] bg-[var(--cloud-ink)] shadow-[0_40px_100px_-40px_rgba(11,18,32,0.65)]">
                    <img
                        src="{{ asset('assets/cloud/product/job-board.png') }}"
                        alt="Shop job board with repair orders by stage"
                        class="w-full h-auto"
                        width="1600"
                        height="1000"
                        loading="lazy"
                    >
                </figure>
            </div>

            <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Estimates</p>
                    <h2 class="cloud-display mt-4 text-3xl sm:text-4xl font-semibold leading-tight">
                        Write the job once. Explain it with confidence.
                    </h2>
                    <p class="mt-5 text-xl text-[var(--cloud-muted)] leading-relaxed">
                        Labor and parts your advisors can stand behind when the customer asks why.
                    </p>
                </div>
                <figure class="overflow-hidden rounded-2xl border border-[var(--cloud-line)] bg-white shadow-[0_32px_80px_-40px_rgba(11,18,32,0.5)]">
                    <img
                        src="{{ asset('assets/cloud/product/worksheet.png') }}"
                        alt="Estimate worksheet"
                        class="w-full h-auto"
                        width="1440"
                        height="900"
                        loading="lazy"
                    >
                </figure>
            </div>

            <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                <figure class="order-2 lg:order-1 overflow-hidden rounded-2xl border border-[var(--cloud-line)] bg-white shadow-[0_32px_80px_-40px_rgba(11,18,32,0.5)]">
                    <img
                        src="{{ asset('assets/cloud/product/conversation.png') }}"
                        alt="Live repair-order communications rail — reply, send estimate, and commitments on the job"
                        class="w-full h-auto"
                        width="1400"
                        height="900"
                        loading="lazy"
                    >
                </figure>
                <div class="order-1 lg:order-2">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Customer updates</p>
                    <h2 class="cloud-display mt-4 text-3xl sm:text-4xl font-semibold leading-tight">
                        Approvals and “is it ready?” without the phone tag.
                    </h2>
                    <p class="mt-5 text-xl text-[var(--cloud-muted)] leading-relaxed">
                        Keep the conversation with the work — so you’re not rewriting the same answer all afternoon.
                    </p>
                </div>
            </div>

            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Inspections</p>
                <h2 class="cloud-display mt-4 text-3xl sm:text-4xl font-semibold leading-tight max-w-2xl">
                    Show what you found at the car — clearly.
                </h2>
                <div class="mt-10 grid gap-5 sm:grid-cols-3">
                    <figure class="overflow-hidden rounded-2xl border border-[var(--cloud-line)] sm:col-span-1">
                        <img
                            src="{{ asset('assets/cloud/product/lift-inspection.webp') }}"
                            alt="Vehicle on a lift"
                            class="h-full w-full object-cover aspect-[4/5]"
                            width="800"
                            height="1000"
                            loading="lazy"
                        >
                    </figure>
                    <figure class="overflow-hidden rounded-2xl border border-[var(--cloud-line)] sm:col-span-1">
                        <img
                            src="{{ asset('assets/cloud/product/inspection.webp') }}"
                            alt="Verifying findings"
                            class="h-full w-full object-cover aspect-[4/5]"
                            width="800"
                            height="1000"
                            loading="lazy"
                        >
                    </figure>
                    <figure class="overflow-hidden rounded-2xl border border-[var(--cloud-line)] sm:col-span-1">
                        <img
                            src="{{ asset('assets/cloud/product/scan-data.webp') }}"
                            alt="Scan data at the bay"
                            class="h-full w-full object-cover aspect-[4/5]"
                            width="800"
                            height="1000"
                            loading="lazy"
                        >
                    </figure>
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Getting started — story timeline --}}
    <section class="mx-auto max-w-3xl px-5 sm:px-8 py-24 sm:py-32">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)] text-center">Getting started</p>
        <h2 class="cloud-display mt-4 text-3xl sm:text-5xl font-semibold text-center leading-tight">
            How your day gets easier
        </h2>

        <ol class="mt-16 space-y-0">
            @foreach ([
                'Create your shop',
                'Invite your team',
                'Write your first repair order',
                'Help your first customer',
            ] as $i => $label)
                <li class="flex flex-col items-center text-center">
                    <div class="rounded-2xl border border-[var(--cloud-line)] bg-white px-8 py-6 sm:px-12 sm:py-7 shadow-[0_20px_50px_-36px_rgba(11,18,32,0.45)] w-full max-w-md">
                        <p class="cloud-display text-xl sm:text-2xl font-semibold text-[var(--cloud-ink)]">{{ $label }}</p>
                    </div>
                    @if ($i < 3)
                        <div class="flex flex-col items-center py-4 text-[var(--cloud-cerulean)]" aria-hidden="true">
                            <span class="text-2xl leading-none">↓</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        <p class="mt-14 text-center text-lg text-[var(--cloud-muted)]">
            Most shops are writing real work in <span class="font-semibold text-[var(--cloud-ink)]">under 5 minutes</span>.
        </p>
    </section>

    @if ($pricingPublic)
        <section class="border-t border-[var(--cloud-line)] bg-white/60">
            <div class="mx-auto max-w-6xl px-5 sm:px-8 py-24 sm:py-32">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Pricing</p>
                <h2 class="cloud-display mt-4 text-3xl sm:text-5xl font-semibold max-w-2xl leading-tight">
                    Start free. Decide when Tuesday feels better.
                </h2>

                <div class="mt-14 grid gap-6 lg:grid-cols-3">
                    @foreach ([
                        [
                            'name' => 'Trial',
                            'price' => 'Free',
                            'period' => '14 days',
                            'blurb' => 'Get your shop online. Write real repair orders. No contract to try it.',
                            'featured' => true,
                        ],
                        [
                            'name' => 'Shop',
                            'price' => '$299',
                            'period' => '/ month',
                            'blurb' => 'One location — repairs, customer updates, website, and the tools your floor needs.',
                            'featured' => false,
                        ],
                        [
                            'name' => 'Multi-Location',
                            'price' => 'Talk to us',
                            'period' => '',
                            'blurb' => 'More than one shop. Same product. Room to grow.',
                            'featured' => false,
                        ],
                    ] as $plan)
                        <div @class([
                            'rounded-2xl p-8 sm:p-9 border',
                            'border-[var(--cloud-cerulean)] bg-white shadow-[0_24px_60px_-36px_rgba(0,153,204,0.55)]' => $plan['featured'],
                            'border-[var(--cloud-line)] bg-white/80' => ! $plan['featured'],
                        ])>
                            <p class="cloud-display text-xl font-semibold">{{ $plan['name'] }}</p>
                            <p class="mt-5 flex items-baseline gap-1">
                                <span class="cloud-display text-4xl font-semibold">{{ $plan['price'] }}</span>
                                @if ($plan['period'] !== '')
                                    <span class="text-[var(--cloud-muted)]">{{ $plan['period'] }}</span>
                                @endif
                            </p>
                            <p class="mt-4 text-[var(--cloud-muted)] leading-relaxed text-lg">{{ $plan['blurb'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <a href="{{ $ctaUrl }}" data-cloud-event="cloud_funnel_homepage_cta" class="cloud-btn-primary text-lg !px-9 !py-4">{{ $ctaLabel }}</a>
                    <a href="{{ $pricing }}" class="cloud-btn-ghost">See full pricing</a>
                </div>
            </div>
        </section>
    @else
        <section class="border-t border-[var(--cloud-line)] bg-white/60">
            <div class="mx-auto max-w-3xl px-5 sm:px-8 py-24 sm:py-32 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Hosted ARK</p>
                <h2 class="cloud-display mt-4 text-3xl sm:text-5xl font-semibold leading-tight">
                    Don’t want to mess with a server?
                </h2>
                <p class="mt-6 text-xl text-[var(--cloud-muted)] leading-relaxed">
                    We’ll host ARK for your shop. Self-serve signup isn’t open yet — tell us you’re interested
                    and we’ll reach out when hosted is ready.
                </p>
                <a href="{{ $ctaUrl }}" data-cloud-event="cloud_funnel_homepage_cta" class="cloud-btn-primary mt-10 text-lg !px-10 !py-4 inline-flex">
                    {{ $ctaLabel }}
                </a>
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-6xl px-5 sm:px-8 py-24 sm:py-32">
        <div class="rounded-3xl border border-[var(--cloud-line)] bg-white px-8 py-16 sm:px-16 sm:py-20 text-center shadow-[0_40px_100px_-48px_rgba(11,18,32,0.45)]">
            <h2 class="cloud-display text-3xl sm:text-5xl font-semibold max-w-2xl mx-auto leading-tight">
                @if ($signupsOpen)
                    Ready to get your shop online?
                @else
                    Ready when you are.
                @endif
            </h2>
            <p class="mt-6 text-xl text-[var(--cloud-muted)] max-w-xl mx-auto leading-relaxed">
                @if ($signupsOpen)
                    Start running your shop in minutes — not after a week of training.
                @else
                    See the product, then ask about hosted ARK if you’d rather we run the infrastructure.
                @endif
            </p>
            <a href="{{ $ctaUrl }}" data-cloud-event="cloud_funnel_homepage_cta" class="cloud-btn-primary mt-10 text-lg !px-10 !py-4 inline-flex">
                {{ $ctaLabel }}
            </a>
            @if ($signupsOpen)
                <p class="mt-4 text-sm text-[var(--cloud-muted)]">Free trial · explore the Cloud Funnel at your pace</p>
            @endif
        </div>
    </section>
</x-cloud.shell>
