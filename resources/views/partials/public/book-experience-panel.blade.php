@php
    $shopName = $shop->displayName();
    $experience = $bookExperience ?? [
        'mode' => \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::GUEST,
        'recognition' => null,
        'vehicle_home' => null,
        'schedule_vehicle_id' => null,
        'schedule_radar_ids' => [],
        'recognized' => false,
    ];
    $mode = $experience['mode'] ?? \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::GUEST;
@endphp

@if (! ($requestAvailability['accepting_requests'] ?? true))
    <div class="public-book-wizard public-book-wizard--paused">
        <p class="public-book-wizard__kicker">{{ $shopName }}</p>
        <h1 class="public-book-wizard__question">Online requests are paused</h1>
        <p class="public-book-wizard__lede">
            {{ $requestAvailability['empty_message'] ?? 'Call or text us and we’ll help you find a time.' }}
        </p>
        <div class="public-book-wizard__actions public-book-wizard__actions--row">
            <a href="tel:{{ preg_replace('/\D+/', '', (string) ($shop->phone ?? '')) ?: '7194136227' }}" class="public-book-wizard__primary">Call</a>
            <a href="{{ route('public.contact') }}" class="public-book-wizard__secondary-link">Contact</a>
        </div>
    </div>
@elseif ($mode === \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::IDENTITY)
    @include('partials.public.book-acquaintance', [
        'shop' => $shop,
        'shopName' => $shopName,
        'identityGateReady' => (bool) ($experience['identity_gate_ready'] ?? false),
    ])
@elseif ($mode === \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::VEHICLE_PICK)
    @include('partials.public.book-recognition-vehicles', [
        'shopName' => $shopName,
        'recognition' => $experience['recognition'],
    ])
@elseif ($mode === \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::VEHICLE_HOME)
    @include('partials.public.book-vehicle-home', [
        'shopName' => $shopName,
        'vehicleHome' => $experience['vehicle_home'],
        'recognition' => $experience['recognition'],
    ])
@else
    @include('partials.public.book-wizard', [
        'shop' => $shop,
        'phoneVerificationRequired' => $phoneVerificationRequired,
        'formRenderedAt' => $formRenderedAt,
        'surfaceContext' => $surfaceContext,
        'requestAvailability' => $requestAvailability,
        'recognizedSchedule' => (bool) ($experience['recognized'] ?? false)
            && $mode === \App\Ark\Operations\Leads\Public\PublicBookExperienceMode::SCHEDULE,
        'scheduleVehicleId' => $experience['schedule_vehicle_id'] ?? null,
        'scheduleRadarIds' => $experience['schedule_radar_ids'] ?? [],
        'scheduleIntent' => $experience['schedule_intent'] ?? null,
        'scheduleIntentDetails' => $experience['schedule_intent_details'] ?? '',
        'vehicleHome' => $experience['vehicle_home'] ?? null,
        'gateVerifiedPhone' => $experience['verified_phone'] ?? null,
        'gateVerifiedEmail' => $experience['verified_email'] ?? null,
    ])
@endif
