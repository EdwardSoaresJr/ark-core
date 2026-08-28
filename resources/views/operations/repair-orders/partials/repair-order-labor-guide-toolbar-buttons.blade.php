@php
    use App\Ark\Operations\LaborGuides\LaborGuideLauncher;
    use App\Ark\Operations\LaborGuides\LaborGuideProvider;

    $concernId = $concernId ?? null;
    $laborGuideLauncher = app(LaborGuideLauncher::class);
    $laborGuides = [];

    foreach (LaborGuideProvider::enabled() as $provider) {
        $launchUrl = $laborGuideLauncher->launchUrl($repairOrder, $provider, $concernId);
        $clipboardVin = $laborGuideLauncher->clipboardVin($repairOrder);

        $notice = $laborGuideLauncher->handoffNotice($repairOrder, $provider, $concernId);
        $windowName = 'ark-labor-'.$provider->value.'-ro-'.$repairOrder->repair_order_id;

        $laborGuides[] = [
            'provider' => $provider->value,
            'label' => $provider->label(),
            'launchUrl' => $launchUrl,
            'blockedReason' => $laborGuideLauncher->blockedReason($repairOrder, $provider),
            'clipboardVin' => $clipboardVin,
            'notice' => $notice,
            'windowName' => $windowName,
            'title' => $clipboardVin !== null
                ? 'Open '.$provider->label().' — VIN copied for vehicle search'
                : 'Open '.$provider->label().' — search by year, make, and model after sign-in',
            'laborGuideJson' => $launchUrl !== null ? json_encode([
                'url' => $launchUrl,
                'vin' => $clipboardVin,
                'notice' => $notice,
                'windowName' => $windowName,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : null,
        ];
    }
@endphp

@foreach ($laborGuides as $guide)
    <button
        type="button"
        class="ops-review-action ops-review-action--labor-guide ops-review-action--labor-guide-{{ $guide['provider'] }}"
        @if ($guide['launchUrl'])
            data-labor-guide="{{ $guide['laborGuideJson'] }}"
            @click="openLaborGuideFromTrigger($event.currentTarget)"
            title="{{ $guide['title'] }}"
        @else
            disabled
            title="{{ $guide['blockedReason'] }}"
        @endif
    >
        {{ $guide['label'] }}
    </button>
@endforeach
