@php
    $bookUrl = route('public.book');
@endphp

<section class="public-cp-index-book" aria-labelledby="public-cp-index-book-heading">
    <p class="public-cp-index-book__eyebrow">Ready to have it looked at?</p>
    <h2 id="public-cp-index-book-heading" class="public-cp-index-book__title">Book an Appointment</h2>
    <p class="public-cp-index-book__lede">
        Tell us what’s happening and choose a preferred day. We’ll confirm availability with you.
    </p>
    <div class="public-cp-index-book__actions">
        <a href="{{ $bookUrl }}" class="public-cta public-cta--primary public-cta--xl">
            Book an Appointment
            <span class="public-cta__hint">We’ll confirm availability with you</span>
        </a>
    </div>
</section>
