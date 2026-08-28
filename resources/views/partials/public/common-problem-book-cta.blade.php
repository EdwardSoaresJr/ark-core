@php
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $concern = trim((string) ($prefilledConcern ?? ''));
    $bookUrl = route('public.book', array_filter(['concern' => $concern !== '' ? $concern : null]));
    $heading = $formHeading ?? (filled($problemTitle ?? null) ? 'Book for '.$problemTitle : 'Book an Appointment');
@endphp

<section class="public-problem-book-cta" id="book-appointment" aria-labelledby="public-problem-book-heading">
    <h2 id="public-problem-book-heading" class="public-problem-book-cta__title">
        {{ $heading }}
    </h2>
    <p class="public-problem-book-cta__lede">
        Bring the vehicle in. We’ll confirm availability and verify what’s happening before any repairs.
    </p>

    @if ($concern !== '')
        <p class="public-problem-book-cta__concern">
            <span class="public-problem-book-cta__concern-label">We’ll start with</span>
            {{ $concern }}
        </p>
    @endif

    <div class="public-problem-book-cta__actions">
        <a href="{{ $bookUrl }}" class="public-cta public-cta--primary public-cta--xl">
            Book an Appointment
            <span class="public-cta__hint">We’ll confirm availability with you</span>
        </a>

        <div class="public-problem-book-cta__secondary">
            <a href="tel:{{ $phoneTel }}" data-public-surface-call class="public-problem-book-cta__link">
                Call {{ $phoneDisplay }}
            </a>
            <a href="sms:{{ $phoneTel }}" data-public-surface-text class="public-problem-book-cta__link">
                Text {{ $shopName }}
            </a>
        </div>
    </div>
</section>
