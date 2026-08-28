@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $shop */
    /** @var \App\Ark\Operations\Leads\Public\LeadThanksProjection $thanks */
    $thanks = $thanks->data;
    $shopName = $thanks['shop_name'];
    $firstName = $thanks['first_name'];
    $hasLead = $thanks['has_lead'];
@endphp

<x-public.lead-intake :shop="$shop" :title="$title" :seo="$seo" publicSurfacePage="lead-thanks">
    <x-customer.split-page>
        <x-slot:primary>
            <div class="space-y-6">
                <section class="customer-panel overflow-hidden border-t-4 border-t-emerald-500 p-0">
                    <div class="border-b border-emerald-100 bg-emerald-50/80 px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M5.5 10.5 8.5 13.5 14.5 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">{{ $thanks['status_eyebrow'] }}</p>
                                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                                    @if ($hasLead && filled($firstName))
                                        Thank you, {{ $firstName }}.
                                    @else
                                        Thank you for reaching out.
                                    @endif
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-5 sm:px-6 sm:py-6">
                        @if ($hasLead)
                            <p class="text-base leading-7 text-slate-700">
                                @if ($thanks['appointment_request'] ?? false)
                                    {{ $thanks['summary_line'] }}
                                @else
                                    Your concern was sent to
                                    <span class="font-semibold text-slate-950">{{ $shopName }}</span>.
                                    We’ll review it and reach out with the next step.
                                @endif
                            </p>
                        @else
                            <p class="text-base leading-7 text-slate-700">
                                If you just sent a request, an advisor will review it shortly.
                                Prefer to talk now? Call or text us below.
                            </p>
                        @endif

                        @if ($hasLead && (filled($thanks['vehicle_label']) || filled($thanks['concern']) || filled($thanks['preferred_availability'])))
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Your request</p>
                                <dl class="mt-3 space-y-3 text-sm sm:text-base">
                                    @if (filled($thanks['vehicle_label']))
                                        <div>
                                            <dt class="font-semibold text-slate-900">Vehicle</dt>
                                            <dd class="mt-0.5 text-slate-700">{{ $thanks['vehicle_label'] }}</dd>
                                        </div>
                                    @endif
                                    @if (filled($thanks['preferred_availability']))
                                        <div>
                                            <dt class="font-semibold text-slate-900">Preferred visit</dt>
                                            <dd class="mt-0.5 text-slate-700">{{ $thanks['preferred_availability'] }}</dd>
                                        </div>
                                    @endif
                                    @if (filled($thanks['concern']))
                                        <div>
                                            <dt class="font-semibold text-slate-900">{{ ($thanks['appointment_request'] ?? false) ? 'What’s happening' : 'Concern' }}</dt>
                                            <dd class="mt-0.5 whitespace-pre-wrap text-slate-700">{{ $thanks['concern'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        @endif

                        @unless ($thanks['appointment_request'] ?? false)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expected response</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $thanks['response_time_hint'] }}</p>
                                </div>
                                @if (filled($thanks['business_hours_label']))
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shop hours</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $thanks['business_hours_label'] }}</p>
                                    </div>
                                @endif
                            </div>

                            <p class="border-l-4 border-[#0099cc] pl-4 text-sm leading-6 text-slate-700 sm:text-base">
                                <span class="font-semibold text-slate-950">{{ $thanks['personality_line'] }}</span>
                                @if (filled($thanks['local_tagline']))
                                    <span class="mt-1 block text-slate-600">{{ $thanks['local_tagline'] }}</span>
                                @endif
                            </p>
                        @endunless
                    </div>
                </section>

                <section class="customer-panel">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">What happens next</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ($thanks['next_steps'] as $index => $step)
                            <li class="flex gap-3 text-sm leading-6 text-slate-700 sm:text-base">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#0099cc]/10 text-xs font-bold text-[#0099cc]">
                                    {{ ($thanks['appointment_request'] ?? false) ? '✓' : $index + 1 }}
                                </span>
                                <span class="pt-0.5">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="customer-panel text-center sm:text-left">
                    <h2 class="text-base font-semibold text-slate-950">Need to talk sooner?</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Call or text {{ $thanks['phone_display'] }} to reach the shop directly.
                    </p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                        <a
                            href="tel:{{ $thanks['phone_tel'] }}"
                            data-public-surface-call
                            class="inline-flex min-h-11 items-center justify-center rounded-md bg-[#0099cc] px-4 text-sm font-semibold text-white no-underline shadow-sm hover:bg-[#0088b8]"
                        >
                            Call {{ $thanks['phone_display'] }}
                        </a>
                        <a
                            href="{{ $thanks['sms_href'] }}"
                            data-public-surface-text
                            class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-900 no-underline hover:bg-slate-100"
                        >
                            Text the shop
                        </a>
                    </div>
                </section>

                @if (($thanks['social_links'] ?? []) !== [])
                    <x-customer.connect-with-us
                        class="customer-panel"
                        heading-id="thanks-connect-heading"
                        :links="$thanks['social_links']"
                        headline="While you wait"
                        lede="Follow us on Facebook or Instagram if you want shop updates — and leave a review if we’ve earned it."
                    />
                @endif
            </div>
        </x-slot:primary>

        <x-slot:rail>
            <section class="customer-panel">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">While you wait</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Useful links while you wait — vehicle records, related guides, or text us a photo.
                </p>
                <ul class="mt-4 space-y-3">
                    @foreach ($thanks['while_you_wait'] as $link)
                        <li>
                            <a
                                href="{{ $link['href'] }}"
                                class="block rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 no-underline transition hover:border-[#0099cc] hover:bg-white"
                            >
                                <span class="text-sm font-semibold text-slate-950">{{ $link['label'] }}</span>
                                <span class="mt-1 block text-sm leading-5 text-slate-600">{{ $link['description'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($thanks['shop_photos'] !== [])
                <section class="grid grid-cols-2 gap-2">
                    @foreach ($thanks['shop_photos'] as $photo)
                        <div class="aspect-[4/3] overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <img
                                src="{{ $photo['url'] }}"
                                alt="{{ $photo['alt'] }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                        </div>
                    @endforeach
                </section>
            @endif
        </x-slot:rail>
    </x-customer.split-page>

    @if ($thanks['appointment_request'] ?? false)
        <script>
            try { window.localStorage.removeItem('ark.public.book.draft.v1'); } catch (e) {}
        </script>
    @endif
</x-public.lead-intake>
