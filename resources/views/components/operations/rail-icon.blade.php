@props(['name'])

@switch($name)
    @case('dashboard')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M4 4.5h12v11H4v-11z" stroke="currentColor" stroke-width="1.4" />
            <path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('time-clock')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <circle cx="10" cy="10" r="6.5" stroke="currentColor" stroke-width="1.4" />
            <path d="M10 6.5V10l2.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('job-board')
    @case('workboard')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M3 4h6v5H3V4zM11 4h6v5h-6V4zM3 11h6v5H3v-5zM11 11h6v5h-6v-5z" stroke="currentColor" stroke-width="1.4" />
        </svg>
        @break
    @case('work-queues')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M3.5 5l1.25 1.25L7 4M3.5 10l1.25 1.25L7 9M3.5 15l1.25 1.25L7 14M9.5 5.5h7M9.5 10.5h7M9.5 15.5h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        @break
    @case('schedule')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <rect x="3.5" y="4.5" width="13" height="12" rx="0.5" stroke="currentColor" stroke-width="1.4" />
            <path d="M7 3v3M13 3v3M3.5 9h13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('communications')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M3.5 4.5h13v9h-13v-9z" stroke="currentColor" stroke-width="1.4" />
            <path d="M7.5 8.5h5M7.5 11h3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('repair-orders')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M5 2.5h7l3 3V17a.5.5 0 01-.5.5h-9A.5.5 0 015 17V2.5z" stroke="currentColor" stroke-width="1.4" />
            <path d="M12 2.5V6h3M7.5 9h5M7.5 12h5M7.5 15h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('vehicles')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M3.5 5.5h13v9.5h-13V5.5z" stroke="currentColor" stroke-width="1.4" />
            <path d="M3.5 8.5h13M6 11.5h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
            <circle cx="6.25" cy="13.75" r="1.25" fill="currentColor" />
            <circle cx="13.75" cy="13.75" r="1.25" fill="currentColor" />
        </svg>
        @break
    @case('customers')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 9a3 3 0 116 0 3 3 0 01-6 0z" stroke="currentColor" stroke-width="1.4" />
            <path d="M3.5 17c.85-2.7 3.1-4.25 6.5-4.25S15.65 14.3 16.5 17" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('cockpit')
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="h-4 w-4">
            <path d="M3 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm8 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V4ZM3 12a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4Zm8-1a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1h-4Z" />
        </svg>
        @break
    @case('reports')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M4 15.5h12M5.5 13V8M10 13V4.5M14.5 13v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>
        @break
    @case('day-review')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M4 15.5h12M5.5 13V8M10 13V4.5M14.5 13v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            <path d="M3.5 3.5l13 13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.35" />
        </svg>
        @break
    @case('tech-production')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M4 14.5h12M6 14.5V8.5M10 14.5V5.5M14 14.5V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>
        @break
    @case('learn')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M3.5 4.5h13v11h-13v-11z" stroke="currentColor" stroke-width="1.4" />
            <path d="M6.5 8.5h7M6.5 11h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
            <path d="M10 2.5v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('stations')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M4 4.5h12v11H4v-11z" stroke="currentColor" stroke-width="1.4" />
            <path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @case('settings')
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
            <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" stroke="currentColor" stroke-width="1.4" />
            <path d="M10 2.5v2M10 15.5v2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M2.5 10h2M15.5 10h2M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
        </svg>
        @break
    @default
        @php
            throw new InvalidArgumentException("Unknown rail icon [{$name}].");
        @endphp
@endswitch
