@php
    $seconds = max(0, (int) ($seconds ?? 0));
    $clock = $seconds >= 3600
        ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
        : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    $kind = $kind ?? 'Recording';
@endphp
<div class="ops-call-library__audio-block">
    <span class="ops-call-library__audio-label">{{ $kind }}</span>
    <div class="ops-call-library__player" data-call-player>
        <button type="button" class="ops-call-library__play" data-play aria-label="Play {{ strtolower($kind) }}">Play</button>
        <input type="range" class="ops-call-library__scrub" data-scrub min="0" max="{{ $seconds }}" value="0" step="0.1" aria-label="{{ $kind }} position">
        <span class="ops-call-library__clock" data-clock>0:00 / {{ $clock }}</span>
        <audio preload="none" class="ops-call-library__audio" src="{{ $src }}" data-duration="{{ $seconds }}"></audio>
    </div>
</div>
