@props([
    'point' => [],
    'variant' => 'portal', // portal | print
    'liveReportUrl' => null,
    'showVideosPlayable' => true,
])

@php
    $condition = (string) ($point['condition_value'] ?? 'not_checked');
    $badgeClass = match ($condition) {
        'failed' => 'border-rose-300 bg-rose-50 text-rose-950',
        'needs_attention' => 'border-amber-300 bg-amber-50 text-amber-950',
        'monitor' => 'border-sky-300 bg-sky-50 text-sky-950',
        'good' => 'border-emerald-300 bg-emerald-50 text-emerald-950',
        'na' => 'border-slate-300 bg-slate-100 text-slate-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
    $presentation = $point['measurement_presentation'] ?? null;
    $photos = collect($point['photos'] ?? []);
    $videos = collect($point['videos'] ?? []);
    $isPrint = $variant === 'print';
@endphp

<article @class([
    'inspection-finding',
    'break-inside-avoid' => $isPrint,
    'border-b border-slate-100 px-4 py-5 sm:px-6' => ! $isPrint,
    'finding-block' => $isPrint,
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            @if (filled($point['category_name'] ?? null))
                <p @class([
                    'text-[11px] font-bold uppercase tracking-wide text-slate-500',
                    'finding-category' => $isPrint,
                ])>{{ $point['category_name'] }}</p>
            @endif
            <h3 @class([
                'mt-1 text-lg font-bold text-slate-950',
                'finding-title' => $isPrint,
            ])>{{ $point['label'] ?? 'Finding' }}</h3>
        </div>
        <span @class([
            'rounded-full border px-3 py-1 text-xs font-semibold',
            $badgeClass,
            'finding-badge' => $isPrint,
        ])>
            {{ $point['condition_label'] ?? 'Not checked' }}
        </span>
    </div>

    @include('portal.partials._inspection-report-measurements', [
        'presentation' => $presentation,
        'variant' => $variant,
    ])

    @foreach (($point['comparison_observations'] ?? []) as $observation)
        <p @class([
            'mt-2 text-sm leading-relaxed text-slate-700',
            'finding-observation' => $isPrint,
        ])>{{ $observation }}</p>
    @endforeach

    @if (filled($point['note'] ?? null))
        <p @class([
            'mt-2 text-sm leading-relaxed text-slate-700',
            'finding-note' => $isPrint,
        ])>{{ $point['note'] }}</p>
    @endif

    @if ($photos->isNotEmpty() || $videos->isNotEmpty())
        <div @class([
            'mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3',
            'finding-evidence' => $isPrint,
        ])>
            @foreach ($photos as $photo)
                @if (filled($photo['url'] ?? null))
                    @if ($isPrint)
                        <figure class="evidence-photo">
                            <img src="{{ $photo['url'] }}" alt="">
                        </figure>
                    @else
                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <img src="{{ $photo['url'] }}" alt="" class="aspect-[4/3] h-full w-full object-cover">
                        </a>
                    @endif
                @endif
            @endforeach

            @foreach ($videos as $video)
                @if ($showVideosPlayable && ! $isPrint && filled($video['url'] ?? null))
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-950">
                        <video src="{{ $video['url'] }}" controls playsinline class="aspect-[4/3] w-full object-cover"></video>
                    </div>
                @elseif ($isPrint)
                    <div class="evidence-video">
                        <p class="evidence-video-label">Video evidence</p>
                        @if (filled($liveReportUrl))
                            <p class="evidence-video-link">
                                View video online:<br>
                                <a href="{{ $liveReportUrl }}">{{ $liveReportUrl }}</a>
                            </p>
                        @endif
                    </div>
                @elseif (filled($video['url'] ?? null))
                    <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-900 no-underline">
                        View video
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</article>
