@php
    $wsLogoUrl = \App\Support\Branding\Branding::logo('full_white');
@endphp

<div class="ws-presence-backdrop" aria-hidden="true">
    <div class="ws-presence-backdrop__gradient"></div>
    <div class="ws-presence-backdrop__pattern"></div>
    <img src="{{ $wsLogoUrl }}" class="ws-presence-backdrop__wordmark" alt="">
</div>
