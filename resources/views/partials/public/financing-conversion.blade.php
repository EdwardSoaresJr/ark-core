@php
    $contactUrl = route('public.contact');
    $bookUrl = route('public.book', ['concern' => 'Pre-Purchase Inspection']);
@endphp

<aside class="public-financing-conversion" aria-label="Financing next steps" id="tell-the-shop">
    <div class="public-financing-conversion__block public-financing-conversion__block--primary">
        <h2 class="public-financing-conversion__title">Ready to bring the car in?</h2>
        <p class="public-financing-conversion__lede">
            Pick a day that works. We’ll confirm the time with you.
        </p>
        <a href="{{ $bookUrl }}" class="public-cta public-cta--primary public-cta--xl">
            Book Inspection
        </a>
    </div>

    <div class="public-financing-conversion__block">
        <h2 class="public-financing-conversion__title">Question about financing?</h2>
        <p class="public-financing-conversion__lede">Have a question? We can help.</p>
        <a href="{{ $contactUrl }}" class="public-cta public-cta--secondary">
            Contact Us
        </a>
    </div>
</aside>
