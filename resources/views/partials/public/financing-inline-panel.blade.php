@php
    $financing = $financing ?? app(\App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection::class)->forDisplay()['financing'];
    $trustSignals = $trustSignals ?? ['financing' => $financing];
@endphp

@if (($financing['available'] ?? false) && filled($financing['body'] ?? null))
    <section
        @class(['public-financing-inline-panel', 'public-content-section', $class ?? 'mt-8'])
        aria-labelledby="financing-inline-heading"
    >
        <h2 id="financing-inline-heading" class="public-section-title">{{ $financing['headline'] }}</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $financing['body'] }}</p>

        <div class="public-financing-inline-panel__actions mt-4">
            @include('partials.public.financing-program-buttons', [
                'financing' => $financing,
                'trustSignals' => $trustSignals,
                'compact' => true,
                'class' => null,
            ])

            @if (filled($financing['learn_more_url'] ?? null))
                <a
                    href="{{ $financing['learn_more_url'] }}"
                    class="public-financing-inline-panel__details"
                >
                    Financing details →
                </a>
            @endif
        </div>
    </section>
@endif
