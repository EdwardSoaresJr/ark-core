@if (($attachments ?? []) !== [])
    <div class="mt-2 space-y-2" data-comms-media>
        @foreach ($attachments as $attachment)
            @if (($attachment['kind'] ?? '') === 'image' && filled($attachment['url'] ?? null))
                <button
                    type="button"
                    data-ops-lightbox="{{ $attachment['url'] }}"
                    data-ops-lightbox-alt="Photo"
                    class="ops-attachment-thumb"
                >
                    <img src="{{ $attachment['url'] }}" alt="Photo" class="ops-attachment-thumb__image">
                </button>
            @elseif (($attachment['kind'] ?? '') === 'file' && filled($attachment['url'] ?? null))
                <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center text-xs font-semibold text-sky-800 hover:text-sky-950">
                    {{ $attachment['label'] ?? 'Attachment' }}
                </a>
            @else
                <p class="text-xs text-slate-600">{{ $attachment['label'] ?? 'Attachment unavailable' }}</p>
            @endif
        @endforeach
    </div>
@endif
