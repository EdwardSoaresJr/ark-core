@php
    /** @var string $key */
    /** @var bool $filled */
    $filled = $filled ?? false;
    $badge = $badge ?? null;
@endphp
<span class="ops-job-card__mark-icon" aria-hidden="true">
    @switch($key)
        @case('estimate')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" d="M6 3.25h5.35L14.75 7.1v9.65H6V3.25Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M6 3.5h5.2L14.5 7v9.5H6V3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <path d="M11.2 3.5V7h3.3M7.5 10.5h5M7.5 13.25h3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            @endif
            @break
        @case('sms')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" d="M3.75 5h12.5v8.35H8.15L3.75 16.5V5Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M4 5.25h12v8.1H8.4L4 16.25v-11Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            @endif
            @break
        @case('email')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" d="M3 5.25h14v9.5H3v-9.5Zm7 4.35L3.85 5.9h12.3L10 9.6Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <rect x="3.25" y="5" width="13.5" height="10" rx="1.25" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M4 6.25 10 10.5 16 6.25" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            @endif
            @break
        @case('phone')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" d="M6.15 3.5h2.55l.95 2.55-1.35 1.2a9.4 9.4 0 0 0 4.35 4.35l1.2-1.35 2.55.95v2.55c0 .85-.7 1.55-1.5 1.7-6.9 1.45-11.6-3.25-10.15-10.15.15-.8.85-1.5 1.7-1.5Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M6.4 3.75h2.3l.85 2.35-1.2 1.2a9.2 9.2 0 0 0 4.15 4.15l1.2-1.2 2.35.85v2.3c0 .7-.55 1.35-1.25 1.5-6.35 1.35-10.7-3-9.35-9.35.15-.7.8-1.25 1.5-1.25Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            @endif
            @break
        @case('dvi')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" fill-rule="evenodd" d="M7.05 4h5.9l.7 1.45H16.5v10.8h-13V5.45h3.55L7.05 4Zm1.7 6.35 1.45 1.45 3.1-3.15.95.95-4.05 4.15-2.4-1.45.95-.95Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M7 4.25h6l.75 1.5H16.5v10.5h-13V5.75h2.75L7 4.25Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <path d="M7.75 11.1 9.3 12.6l3.2-3.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            @endif
            @break
        @case('scheduled')
            @if ($filled)
                <svg viewBox="0 0 20 20">
                    <path fill="currentColor" fill-rule="evenodd" d="M6.25 3.25h1.5v1.35h4.5V3.25h1.5v1.35H16.5V17.5H3.5V4.6h2.75V3.25Zm-1.35 5.1h10.2v7.4H4.9V8.35Z"/>
                </svg>
            @else
                <svg viewBox="0 0 20 20" fill="none">
                    <rect x="3.25" y="5" width="13.5" height="12" rx="1.25" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M3.25 8.5h13.5M7 3.5v3.25M13 3.5v3.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
            @endif
            @break
    @endswitch
</span>
@if ($badge === 'viewed')
    <span class="ops-job-card__mark-badge" aria-hidden="true">
        <svg viewBox="0 0 12 12"><path fill="currentColor" d="M6 2.25c2.35 0 4.35 1.45 5.25 3.5C10.35 7.8 8.35 9.25 6 9.25S1.65 7.8.75 5.75C1.65 3.7 3.65 2.25 6 2.25Zm0 1.4A2.1 2.1 0 1 0 6 7.85 2.1 2.1 0 0 0 6 3.65Z"/></svg>
    </span>
@elseif ($badge === 'replied')
    <span class="ops-job-card__mark-badge" aria-hidden="true">
        <svg viewBox="0 0 12 12"><path fill="currentColor" d="M2.25 6.1 6.4 2.6v2.15c2.7.15 4.35 1.45 4.85 4.1-.95-1.15-2.2-1.7-4.85-1.7V9.4L2.25 6.1Z"/></svg>
    </span>
@elseif ($badge === 'check')
    <span class="ops-job-card__mark-badge" aria-hidden="true">
        <svg viewBox="0 0 12 12"><path fill="currentColor" d="M2.4 6.2 4.7 8.5 9.6 3.5l.9.9-5.8 5.9-3.2-3.2.9-.9Z"/></svg>
    </span>
@elseif ($badge === 'alert')
    <span class="ops-job-card__mark-badge ops-job-card__mark-badge--alert" aria-hidden="true">!</span>
@endif
