@php
    $financing = $financing ?? ($trustSignals['financing'] ?? app(\App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection::class)->forDisplay()['financing']);
    $showQuickLinks = (bool) ($showQuickLinks ?? false);
    $showProgramButtons = (bool) ($showProgramButtons ?? false);
@endphp

@if (($financing['available'] ?? false) && filled($financing['body'] ?? null))
    <aside @class([
        'public-financing-note',
        'public-financing-note--featured' => $showProgramButtons,
        $class ?? '',
    ])>
        <div class="public-financing-note__content">
            <p class="public-financing-note__headline">{{ $financing['headline'] }}</p>
            <p class="public-financing-note__body">{{ $financing['body'] }}</p>

            @if ($showProgramButtons && filled($financing['learn_more_url'] ?? null))
                <p class="public-financing-note__learn-more">
                    <a href="{{ $financing['learn_more_url'] }}" class="text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                        Financing options →
                    </a>
                </p>
            @endif
        </div>

        @if ($showProgramButtons)
            <div class="public-financing-note__actions">
                @include('partials.public.financing-program-buttons', [
                    'financing' => $financing,
                ])
            </div>
        @endif

        @if (! $showProgramButtons && ($showQuickLinks || filled($financing['learn_more_url'] ?? null)))
            <div class="public-financing-note__links">
                @if (filled($financing['learn_more_url'] ?? null))
                    <a href="{{ $financing['learn_more_url'] }}" class="text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                        Financing options →
                    </a>
                @endif
                @if ($showQuickLinks && filled($financing['wisetack_url'] ?? null))
                    <a href="{{ $financing['wisetack_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                        Prequalify with Wisetack →
                    </a>
                @endif
                @if ($showQuickLinks && filled($financing['synchrony_url'] ?? null))
                    <a href="{{ $financing['synchrony_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                        Apply with Synchrony Car Care →
                    </a>
                @endif
            </div>
        @endif
    </aside>
@endif
