@php
    $photo = $compositionPhotos['diagnostic_evidence'] ?? null;
    $photoUrl = $photo['url'] ?? asset('shop-photos/scan-data.webp');
    $photoAlt = $photo['alt'] ?? 'Shop photo from Demo Auto Repair';
@endphp

{{--
    Differentiator — statement + diagnostic/testing evidence (named composition role).
    Desktop: content beside photograph. Mobile: content → photograph.
--}}
<section class="public-brand-moment public-surface--diagnostic" aria-labelledby="public-brand-moment-heading">
    <div class="public-brand-moment__inner customer-page-inset">
        <div class="public-brand-moment__copy">
            <h2 id="public-brand-moment-heading" class="public-brand-moment__title">
                Still having the same problem?
            </h2>
            <p class="public-brand-moment__lede">
                If new parts didn’t fix it, we’ll test the car and find out what was missed.
            </p>

            <ul class="public-brand-moment__list">
                <li>Parts were replaced and the problem came back</li>
                <li>The dealer wants thousands in repairs</li>
                <li>The problem comes and goes</li>
                <li>Check engine light nobody can explain</li>
                <li>You want a second look before a big job</li>
            </ul>
        </div>

        <figure class="public-brand-moment__media">
            <img
                src="{{ $photoUrl }}"
                alt="{{ $photoAlt }}"
                class="public-brand-moment__image"
                width="1200"
                height="900"
                loading="lazy"
            >
        </figure>
    </div>
</section>
