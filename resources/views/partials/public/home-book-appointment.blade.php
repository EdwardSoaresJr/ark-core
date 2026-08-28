@php
    $sidePhoto = $compositionPhotos['appointment_process'] ?? null;
    $sideUrl = $sidePhoto['url'] ?? asset('shop-photos/lift-inspection.webp');
    $sideAlt = $sidePhoto['alt'] ?? 'Shop photo from Demo Auto Repair';
@endphp

{{--
    How it works — process drives the composition (not a marketing split).
    Booking CTA lives in header / hero / sticky / closing next-step — not here.
    Appointment remains a request until confirmed.
--}}
<section class="public-book-band public-surface--shop" id="book" aria-labelledby="public-book-band-heading">
    <div class="public-book-band__inner customer-page-inset">
        <div class="public-book-band__copy">
            <h2 id="public-book-band-heading" class="public-book-band__title">How an appointment works</h2>
            <p class="public-book-band__lede">
                Bring the car in. We’ll check what’s wrong and show you the estimate before we start any work.
            </p>

            <ol class="public-book-band__process" aria-label="What happens">
                <li>
                    <span class="public-book-band__process-num" aria-hidden="true">1</span>
                    <span><strong>Request a day</strong> — we confirm the time with you. Nothing is reserved yet.</span>
                </li>
                <li>
                    <span class="public-book-band__process-num" aria-hidden="true">2</span>
                    <span><strong>Bring the vehicle in</strong> — drop-off or wait, whichever works.</span>
                </li>
                <li>
                    <span class="public-book-band__process-num" aria-hidden="true">3</span>
                    <span><strong>We inspect and test</strong> — find the real problem before quoting parts.</span>
                </li>
                <li>
                    <span class="public-book-band__process-num" aria-hidden="true">4</span>
                    <span><strong>You approve the estimate</strong> — then we repair what you approved.</span>
                </li>
            </ol>
        </div>

        <figure class="public-book-band__media">
            <img
                src="{{ $sideUrl }}"
                alt="{{ $sideAlt }}"
                class="public-book-band__image"
                width="1200"
                height="675"
                loading="lazy"
            >
        </figure>
    </div>
</section>
