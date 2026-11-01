@if (($attachments ?? []) !== [])
    @if (($mediaUi ?? 'legacy') === 'tabler')
        <div class="d-flex flex-wrap gap-2 mt-2" data-comms-media>
            @foreach ($attachments as $attachment)
                @if (($attachment['kind'] ?? '') === 'image' && filled($attachment['url'] ?? null))
                    <button
                        type="button"
                        data-ops-lightbox="{{ $attachment['url'] }}"
                        data-ops-lightbox-alt="Photo"
                        class="btn btn-ghost-secondary p-0"
                        aria-label="Open photo"
                    >
                        <img src="{{ $attachment['url'] }}" alt="Photo" class="rounded border object-cover" width="96" height="96">
                    </button>
                @elseif (($attachment['kind'] ?? '') === 'file' && filled($attachment['url'] ?? null))
                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="btn btn-sm">
                        <x-tabler.icon name="paperclip" />
                        {{ $attachment['label'] ?? 'Attachment' }}
                    </a>
                @else
                    <span class="text-secondary small">{{ $attachment['label'] ?? 'Attachment unavailable' }}</span>
                @endif
            @endforeach
        </div>
    @else
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
@endif
