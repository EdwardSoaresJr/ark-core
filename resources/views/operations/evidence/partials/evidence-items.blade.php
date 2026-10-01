{{-- One renderer family for EvidenceProjection rows --}}
@php
    $items = collect($items ?? []);
@endphp

@if ($items->isNotEmpty())
    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach ($items as $item)
            <figure class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="block aspect-square bg-slate-200">
                    @if ($item['is_image'] ?? false)
                        <img src="{{ $item['url'] }}" alt="{{ $item['caption'] ?? 'Evidence' }}" class="h-full w-full object-cover" loading="lazy">
                    @elseif ($item['is_video'] ?? false)
                        <span class="flex h-full items-center justify-center text-xs font-semibold text-slate-600">Video</span>
                    @else
                        <span class="flex h-full items-center justify-center text-xs font-semibold text-slate-600">PDF</span>
                    @endif
                </a>
                @if (filled($item['caption'] ?? null))
                    <figcaption class="px-2 py-1.5 text-xs text-slate-700">{{ $item['caption'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif
