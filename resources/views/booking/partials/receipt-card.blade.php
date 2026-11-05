<div class="ark-booking__card ark-booking__receipt" data-ark-booking-receipt data-status="{{ $booked ? 'booked' : 'requested' }}" role="status">
    <p class="ark-booking__shop">{{ $shop->displayName() }}</p>
    <h1 class="ark-booking__title" tabindex="-1" data-receipt-heading>{{ $heading }}</h1>
    <p class="ark-booking__intro">{{ $message }}</p>

    @if ($bookingRequest !== null)
        <dl class="ark-booking__summary">
            <div><dt>{{ $booked ? 'When' : 'You asked for' }}</dt>
                <dd>
                    @if ($booked)
                        {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::format($bookingRequest->requested_starts_at, 'l, F j \a\t g:i A') }}
                    @else
                        {{ $bookingRequest->preferenceLabel() }}
                    @endif
                </dd>
            </div>
            <div><dt>Service</dt><dd>{{ $bookingRequest->serviceLabel() }}</dd></div>
            @if ($bookingRequest->locationType()->isOnSite() && $bookingRequest->addressLabel())
                <div><dt>Where</dt><dd>{{ $bookingRequest->addressLabel() }}</dd></div>
            @endif
            <div><dt>Name</dt><dd>{{ $bookingRequest->contact_name }}</dd></div>
        </dl>
        @unless ($booked)
            <p class="ark-booking__mode"><strong>Not confirmed yet.</strong> The shop will contact you to confirm the exact time.</p>
        @endunless
    @endif

    @if ($phoneHref)
        <p class="ark-booking__footer">Questions? Call <a class="public-link" href="tel:{{ $phoneHref }}" target="_top">{{ \App\Ark\Operations\PhoneNumber::display($phone) ?? $phone }}</a></p>
    @endif
    @if ($embed)
        <p><button type="button" class="public-cta public-cta--secondary" data-booking-close>Close</button></p>
    @endif
    @if ($seo !== null)
        @include('website.partials.appointment-request-conversion')
    @endif
</div>
