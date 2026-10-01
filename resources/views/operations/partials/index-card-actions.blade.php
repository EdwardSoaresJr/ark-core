@php
    /** @var list<array{href: ?string, key: string, label: string}> $indexActions */
@endphp

<div class="ops-job-card__activity" role="group" aria-label="Open">
    @foreach ($indexActions as $action)
        @if ($action['href'] !== null)
            <a
                href="{{ $action['href'] }}"
                class="ops-job-card__mark ops-ro-index-action"
                title="{{ $action['label'] }}"
                aria-label="{{ $action['label'] }}"
            >
        @else
            <span class="ops-job-card__mark ops-job-card__mark--none" aria-hidden="true">
        @endif
            <span class="ops-job-card__mark-icon" aria-hidden="true">
                @switch($action['key'])
                    @case('vehicle')
                        <svg viewBox="0 0 20 20" fill="none">
                            <path d="M4 13h12.25v1.35h-1.05a1.45 1.45 0 0 1-2.8 0H7.7a1.45 1.45 0 0 1-2.8 0H4V13Z" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>
                            <path d="M5.15 13 6.55 8.7h6.95L15.1 13" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>
                            <path d="M7.15 8.7V7.35h5.7V8.7" stroke="currentColor" stroke-width="1.55" stroke-linejoin="round"/>
                        </svg>
                        @break
                    @case('documents')
                        <svg viewBox="0 0 20 20" fill="none">
                            <path d="M6 3.5h5.2L14.5 7v9.5H6V3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <path d="M11.2 3.5V7h3.3M7.5 10.5h5M7.5 13.25h3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                        @break
                    @case('customer')
                        <svg viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="7" r="2.55" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M4.7 16.25c.75-2.7 2.55-4.05 5.3-4.05s4.55 1.35 5.3 4.05" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                        @break
                    @case('ro')
                        <svg viewBox="0 0 20 20" fill="none">
                            <rect x="4.25" y="4.25" width="11.5" height="12.5" rx="1.25" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M7.25 8.25h5.5M7.25 11h4.25M7.25 13.6h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                        @break
                @endswitch
            </span>
            @if ($action['href'] !== null)
                <span class="ops-job-card__tip" role="tooltip">{{ $action['label'] }}</span>
            @endif
        @if ($action['href'] !== null)
            </a>
        @else
            </span>
        @endif
    @endforeach
</div>
