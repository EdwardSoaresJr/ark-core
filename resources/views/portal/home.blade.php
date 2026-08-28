@php
    /** @var array<string, mixed> $home */
@endphp

<x-portal.app>
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <div class="space-y-6">
                <div class="public-hero">
                    <p class="public-hero__eyebrow">Welcome back</p>
                    <h1 class="public-hero__title">{{ $home['first_name'] }}</h1>

                    <div class="public-hero__lede">
                        <p>
                            Your vehicles and anything that needs your attention right now.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Your vehicles</p>
                            <p class="mt-1 text-2xl font-bold text-slate-950">{{ count($home['vehicle_cards']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active visits</p>
                            <p class="mt-1 text-2xl font-bold text-slate-950">{{ $home['active_visit_count'] }}</p>
                        </div>
                    </div>

                    @if (filled($home['personality_line'] ?? null))
                        <p class="mt-6 border-l-4 border-[#0099cc] pl-4 text-sm leading-6 text-slate-700 sm:text-base">
                            <span class="font-semibold text-slate-950">{{ $home['personality_line'] }}</span>
                        </p>
                    @endif
                </div>

                <section class="space-y-4" aria-labelledby="your-vehicles-heading">
                    <div class="flex items-end justify-between gap-3">
                        <h2 id="your-vehicles-heading" class="public-section-title">Your vehicles</h2>
                        @if ($home['has_vehicles'])
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Select a vehicle</p>
                        @endif
                    </div>

                    @if (! $home['has_vehicles'])
                        <div class="public-panel">
                            <p class="text-sm leading-6 text-slate-600">No vehicles are linked to this account yet.</p>
                            <p class="mt-3 text-sm text-slate-600">
                                <a href="{{ \App\Ark\Customer\CustomerSurfaceUrls::publicHome() }}" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                                    Contact the shop
                                </a>
                                if that looks wrong.
                            </p>
                        </div>
                    @else
                        <ul class="space-y-4">
                            @foreach ($home['vehicle_cards'] as $vehicle)
                                <li>
                                    <a
                                        href="{{ $vehicle['url'] }}"
                                        class="public-panel block no-underline transition hover:border-[#0099cc]"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p class="text-lg font-bold text-slate-950">{{ $vehicle['display_name'] }}</p>
                                                @if (filled($vehicle['plate']))
                                                    <p class="mt-0.5 text-sm text-slate-500">Plate {{ $vehicle['plate'] }}</p>
                                                @endif
                                            </div>
                                            @if ($vehicle['document_count'] > 0)
                                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                    {{ $vehicle['document_count'] }} {{ $vehicle['document_count'] === 1 ? 'document' : 'documents' }}
                                                </span>
                                            @endif
                                        </div>

                                        @if ($vehicle['active_visit'])
                                            <div @class([
                                                'mt-4 rounded-lg border px-4 py-3',
                                                'border-amber-200 bg-amber-50' => $vehicle['active_visit']['needs_attention'],
                                                'border-sky-200 bg-sky-50' => ! $vehicle['active_visit']['needs_attention'],
                                            ])>
                                                <p @class([
                                                    'text-xs font-semibold uppercase tracking-wide',
                                                    'text-amber-800' => $vehicle['active_visit']['needs_attention'],
                                                    'text-sky-800' => ! $vehicle['active_visit']['needs_attention'],
                                                ])>
                                                    Active visit · {{ $vehicle['active_visit']['status_label'] }}
                                                </p>
                                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $vehicle['active_visit']['summary'] }}</p>
                                                <p class="mt-1 text-xs text-slate-600">
                                                    @if (filled($vehicle['active_visit']['opened_at_label']))
                                                        Opened {{ $vehicle['active_visit']['opened_at_label'] }}
                                                        ·
                                                    @endif
                                                    Repair order #{{ $vehicle['active_visit']['repair_order_id'] }}
                                                </p>
                                            </div>
                                        @elseif (filled($vehicle['last_visit_label']))
                                            <p class="mt-3 text-sm text-slate-600">Last visit {{ $vehicle['last_visit_label'] }}</p>
                                        @endif

                                        <p class="mt-4 text-sm font-semibold text-[#0099cc]">
                                            @if (data_get($vehicle, 'active_visit.needs_attention') === true)
                                                Review what needs your attention →
                                            @else
                                                Open vehicle →
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </x-slot:primary>

        <x-slot:rail>
            @include('portal.partials._customer-aside', [
                'aside' => [
                    'phone_display' => $home['phone_display'],
                    'phone_tel' => $home['phone_tel'],
                    'sms_href' => $home['sms_href'],
                    'business_hours_label' => $home['business_hours_label'],
                    'help_links' => $home['help_links'],
                    'shop_photos' => $home['shop_photos'],
                ],
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-portal.app>
