@php
    /** @var array<string, mixed> $aside */
@endphp

<div class="space-y-6">
    <section class="public-panel">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Questions?</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            Call or text {{ $aside['phone_display'] }} during business hours.
        </p>
        @include('portal.partials._shop-call-text-actions', [
            'phoneTel' => $aside['phone_tel'],
            'class' => 'mt-4',
        ])
        @if (filled($aside['business_hours_label'] ?? null))
            <p class="mt-3 text-xs text-slate-500">{{ $aside['business_hours_label'] }}</p>
        @endif
    </section>

    @if (($aside['help_links'] ?? []) !== [])
        <section class="public-panel">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Helpful links</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($aside['help_links'] as $link)
                    <li>
                        <a
                            href="{{ $link['href'] }}"
                            class="block rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 no-underline transition hover:border-[#0099cc]"
                        >
                            <span class="text-sm font-semibold text-slate-950">{{ $link['label'] }}</span>
                            <span class="mt-1 block text-sm leading-5 text-slate-600">{{ $link['description'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
