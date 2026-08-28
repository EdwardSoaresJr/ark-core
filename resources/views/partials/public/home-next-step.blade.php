@php
    $bookUrl = route('public.book');
@endphp

<section class="public-home-next" aria-labelledby="public-home-next-heading">
    <div class="public-home-next__inner customer-page-inset" id="tell-the-shop">
        <h2 id="public-home-next-heading" class="public-home-next__title">Ready when you are</h2>
        <p class="public-home-next__lede">
            Request a day that works. We’ll confirm the time with you before anything is reserved.
        </p>
        <a href="{{ $bookUrl }}" class="public-cta public-cta--primary public-cta--xl">
            Book an Appointment
            <span class="public-cta__hint">We’ll confirm the time with you</span>
        </a>
    </div>
</section>
