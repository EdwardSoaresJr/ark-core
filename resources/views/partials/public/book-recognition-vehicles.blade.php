@php
    $closeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
    $firstName = trim((string) ($recognition['customer']['first_name'] ?? ''));
    $vehicles = $recognition['vehicles'] ?? [];
@endphp

<div class="public-book-wizard public-book-recognition">
    <div class="public-book-wizard__chrome">
        <div class="public-book-wizard__toolbar">
            <span class="public-book-wizard__toolbar-spacer"></span>
            <a
                href="{{ $closeUrl }}"
                class="public-book-wizard__close"
                data-public-book-close
                aria-label="Close"
            >
                <span aria-hidden="true">×</span>
            </a>
        </div>
    </div>

    <p class="public-book-wizard__kicker">{{ $shopName }}</p>
    <h1 class="public-book-wizard__question">
        @if ($firstName !== '')
            Welcome back, {{ $firstName }}
        @else
            Welcome back
        @endif
    </h1>
    <p class="public-book-wizard__lede">Which vehicle should we look at?</p>

    @if ($vehicles === [])
        <p class="public-book-wizard__hint">We don’t have a vehicle on file yet — you can still request a time.</p>
        <div class="public-book-wizard__actions">
            <a href="{{ route('public.book', ['schedule' => 1]) }}" class="public-book-wizard__primary">
                Schedule Service
            </a>
        </div>
    @else
        <div class="public-book-wizard__choices">
            @foreach ($vehicles as $vehicle)
                <a
                    href="{{ route('public.book', ['vehicle' => $vehicle['id']]) }}"
                    class="public-book-wizard__choice public-book-wizard__choice--link"
                >
                    {{ $vehicle['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
