@props([
    'snapshot' => [],
    'shopName' => null,
    'subtitle' => null,
])

@php
    $shop = is_array($snapshot['shop'] ?? null) ? $snapshot['shop'] : [];
    $name = $shopName ?? ($shop['name'] ?? config('app.name', 'ARK-SMS'));
    $logoUrl = $shop['logo_url'] ?? null;
    $phone = $shop['phone_display'] ?? $shop['phone'] ?? null;
    $phoneDigits = preg_replace('/\D+/', '', (string) ($shop['phone'] ?? $phone ?? '')) ?: null;
    $addressParts = array_filter([
        $shop['address_line_1'] ?? null,
        trim(implode(' ', array_filter([
            $shop['city'] ?? null,
            $shop['state'] ?? null,
            $shop['postal_code'] ?? null,
        ]))),
    ]);
@endphp

<header {{ $attributes->merge(['class' => 'border-b-2 border-[#0099cc] pb-5']) }}>
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            @if (filled($logoUrl))
                <img
                    src="{{ $logoUrl }}"
                    alt="{{ $name }}"
                    class="h-14 w-auto max-w-[140px] shrink-0 object-contain object-left sm:h-16 sm:max-w-[160px]"
                >
            @else
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-[#0099cc]/10 text-lg font-black text-[#0099cc] sm:h-16 sm:w-16">
                    {{ strtoupper(substr(preg_replace('/\s+/', '', $name) ?: 'A', 0, 1)) }}
                </div>
            @endif

            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-wide text-slate-800">{{ $name }}</p>
                @if ($addressParts !== [])
                    <p class="mt-0.5 text-xs leading-5 text-slate-500 md:max-w-md">{{ implode(' · ', $addressParts) }}</p>
                @endif
            </div>
        </div>

        @if (filled($phone) || filled($shop['hours_summary'] ?? null))
            <div class="md:shrink-0 md:text-right">
                @if (filled($phone) && filled($phoneDigits))
                    <p class="text-sm text-slate-600">Call or text us at {{ $phone }}.</p>
                    @include('portal.partials._shop-call-text-actions', [
                        'phoneTel' => $phoneDigits,
                        'class' => 'mt-2 max-w-xs md:ml-auto',
                    ])
                @endif
                @if (filled($shop['hours_summary'] ?? null))
                    <p class="mt-0.5 text-xs leading-5 text-slate-500 md:max-w-xs md:ml-auto">Hours: {{ $shop['hours_summary'] }}</p>
                @endif
            </div>
        @endif
    </div>

    @if (filled($subtitle))
        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $subtitle }}</p>
    @endif
</header>
