@php
    $toneClass = match ($finding['intent_tone']) {
        'rose' => 'ops-inspection-intent-badge--rose',
        'amber' => 'ops-inspection-intent-badge--amber',
        'sky' => 'ops-inspection-intent-badge--sky',
        'emerald' => 'ops-inspection-intent-badge--emerald',
        default => 'ops-inspection-intent-badge--slate',
    };
@endphp

<article
    id="finding-{{ $finding['id'] }}"
    class="ops-inspection-finding-card"
    x-data="{ expanded: false }"
    x-init="expanded = window.location.hash === '#finding-{{ $finding['id'] }}'"
>
    <div class="ops-inspection-finding-card__summary-row">
        <button
            type="button"
            class="ops-inspection-finding-card__summary"
            x-on:click="expanded = ! expanded"
            x-bind:aria-expanded="expanded"
        >
            <div class="ops-inspection-finding-card__main">
                <div class="ops-inspection-finding-card__identity">
                    <h3 class="ops-inspection-finding-card__label">{{ $finding['label'] }}</h3>
                    <p class="ops-inspection-finding-card__meta">
                        @if ($finding['intent'])
                            <span class="ops-inspection-intent-badge {{ $toneClass }}">{{ $finding['intent'] }}</span>
                        @endif
                        <span>{{ $finding['category'] }}</span>
                        @if ($finding['measurement'])
                            <span class="ops-inspection-finding-card__measurement">{{ $finding['measurement'] }}</span>
                        @endif
                        <span class="ops-inspection-finding-card__age">{{ $finding['age'] }}</span>
                    </p>
                    @if ($finding['note'])
                        <p class="ops-inspection-finding-card__note">{{ $finding['note'] }}</p>
                    @endif
                </div>
            </div>
        </button>

        @if ($finding['first_photo_url'])
            <button
                type="button"
                class="ops-inspection-finding-card__thumb-btn"
                data-ops-lightbox="{{ $finding['first_photo_url'] }}"
                data-ops-lightbox-alt="{{ $finding['label'] }} photo"
                aria-label="View photo for {{ $finding['label'] }}"
            >
                <img
                    src="{{ $finding['first_photo_url'] }}"
                    alt=""
                    class="ops-inspection-finding-card__thumb"
                >
            </button>
        @elseif ($finding['first_video_url'] ?? null)
            <a
                href="{{ $finding['first_video_url'] }}"
                class="ops-inspection-finding-card__thumb-btn ops-inspection-finding-card__thumb-btn--video"
                aria-label="View video for {{ $finding['label'] }}"
            >
                <span class="ops-inspection-finding-card__video-badge">Video</span>
            </a>
        @elseif ($finding['photo_count'] > 0)
            <span class="ops-inspection-finding-card__photo-count">{{ $finding['photo_count'] }} attached</span>
        @endif
    </div>

    <div class="ops-inspection-finding-card__detail" x-show="expanded" x-cloak>
        @if ($item)
            @include('operations.repair-orders.inspection.partials.finding-detail', ['item' => $item])
        @endif
    </div>
</article>
