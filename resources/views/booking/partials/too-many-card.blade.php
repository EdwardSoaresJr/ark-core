@php
    $phone = $shop->phone ?? null;
    $phoneHref = filled($phone) ? preg_replace('/\D+/', '', (string) $phone) : null;
@endphp
<div class="ark-booking__card ark-booking__receipt" role="alert" data-booking-too-many>
    <p class="ark-booking__shop">{{ $shop->displayName() }}</p>
    <h1 class="ark-booking__title" tabindex="-1">Too many tries</h1>
    <p class="ark-booking__intro">Wait a minute, then go back and try again. Nothing was sent to the shop yet.</p>
    @if ($phoneHref)
        <p class="ark-booking__footer">Or call <a class="public-link" href="tel:{{ $phoneHref }}" target="_top">{{ \App\Ark\Operations\PhoneNumber::display($phone) ?? $phone }}</a></p>
    @endif
    <p><button type="button" class="public-cta public-cta--secondary" onclick="history.back()">Go back</button></p>
</div>
