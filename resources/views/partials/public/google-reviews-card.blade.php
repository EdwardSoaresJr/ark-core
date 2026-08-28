@if ((float) ($googleRating ?? 0) > 0 && filled($googleReviewsUrl ?? null))
    <div @class([$class ?? 'mt-6'])>
        <a
            href="{{ $googleReviewsUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            data-public-surface-reviews
            aria-label="Read our Google reviews — {{ $googleRating }} star rating"
            class="public-review-link group"
        >
            <span class="flex flex-wrap items-center gap-2">
                <x-public.star-rating size="md" />
                <span class="font-semibold text-slate-900">{{ $googleRating }} stars on Google</span>
            </span>
            <span class="mt-1.5 block text-sm font-semibold text-[#0099cc] group-hover:text-[#0088b8]">
                Read reviews on Google →
            </span>
        </a>
    </div>
@endif
