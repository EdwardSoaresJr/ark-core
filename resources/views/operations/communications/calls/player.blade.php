@php
    $seconds = max(0, (int) ($seconds ?? 0));
    $clock = $seconds >= 3600
        ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
        : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    $kind = $kind ?? 'Recording';
@endphp
<div>
    <div class="subheader mb-1">{{ $kind }}</div>
    <div class="d-flex align-items-center gap-2" style="max-width: 22rem" data-call-player>
        <button type="button" class="btn btn-sm flex-shrink-0" data-play aria-label="Play {{ strtolower($kind) }}">Play</button>
        <input type="range" class="form-range flex-fill" data-scrub min="0" max="{{ $seconds }}" value="0" step="0.1" aria-label="{{ $kind }} position">
        <span class="small text-secondary text-nowrap ark-money" data-clock>0:00 / {{ $clock }}</span>
        <audio preload="none" data-call-audio src="{{ $src }}" data-duration="{{ $seconds }}"></audio>
    </div>
</div>
