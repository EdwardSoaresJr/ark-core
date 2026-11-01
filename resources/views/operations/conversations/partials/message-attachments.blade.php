@php
    /** @var \App\Ark\Operations\Conversations\ConversationMessage $message */
    $mediaUi = $mediaUi ?? 'legacy';
@endphp

@if ($message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
    @if ($mediaUi === 'tabler')
        <div class="d-flex flex-wrap align-items-start gap-2 mt-2">
            @foreach ($message->attachments as $attachment)
                @php
                    $attachmentUrl = route('operations.conversation-attachments.show', [
                        'conversation' => $message->conversation_id,
                        'message' => $message->id,
                        'attachment' => $attachment,
                    ]);
                @endphp
                @if ($attachment->isImage() && filled($attachment->storage_path))
                    <button
                        type="button"
                        data-ops-lightbox="{{ $attachmentUrl }}"
                        data-ops-lightbox-alt="Message attachment"
                        class="btn btn-ghost-secondary p-0"
                        aria-label="Open attachment"
                    >
                        <img
                            src="{{ $attachmentUrl }}"
                            alt="Attachment"
                            class="rounded border object-cover"
                            width="96"
                            height="96"
                        >
                    </button>
                @elseif ($attachment->isVideo() && filled($attachment->storage_path))
                    <video controls class="rounded border mw-100" style="max-height: 12rem" preload="metadata">
                        <source src="{{ $attachmentUrl }}" type="{{ $attachment->content_type }}">
                    </video>
                @elseif ($attachment->isAudio() && filled($attachment->storage_path))
                    <audio controls preload="metadata" class="mw-100">
                        <source src="{{ $attachmentUrl }}" type="{{ $attachment->content_type }}">
                    </audio>
                @elseif ($attachment->isPdf() && filled($attachment->storage_path))
                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="btn btn-sm">
                        <x-tabler.icon name="file-text" />
                        PDF attachment
                    </a>
                @elseif (filled($attachment->storage_path))
                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="btn btn-sm">
                        <x-tabler.icon name="paperclip" />
                        Download attachment
                    </a>
                @endif
            @endforeach
        </div>
    @else
        <div class="mt-2 space-y-2">
            @foreach ($message->attachments as $attachment)
                @if ($attachment->isImage() && filled($attachment->storage_path))
                    @php
                        $attachmentUrl = route('operations.conversation-attachments.show', [
                            'conversation' => $message->conversation_id,
                            'message' => $message->id,
                            'attachment' => $attachment,
                        ]);
                    @endphp
                    <button
                        type="button"
                        data-ops-lightbox="{{ $attachmentUrl }}"
                        data-ops-lightbox-alt="Message attachment"
                        class="ops-attachment-thumb"
                    >
                        <img
                            src="{{ $attachmentUrl }}"
                            alt="Attachment"
                            class="ops-attachment-thumb__image"
                        >
                    </button>
                @elseif ($attachment->isVideo() && filled($attachment->storage_path))
                    <video controls class="max-h-48 max-w-full rounded-sm border border-slate-200" preload="metadata">
                        <source src="{{ route('operations.conversation-attachments.show', ['conversation' => $message->conversation_id, 'message' => $message->id, 'attachment' => $attachment]) }}" type="{{ $attachment->content_type }}">
                    </video>
                @elseif ($attachment->isAudio() && filled($attachment->storage_path))
                    <audio controls preload="metadata" class="ops-attachment-audio">
                        <source src="{{ route('operations.conversation-attachments.show', ['conversation' => $message->conversation_id, 'message' => $message->id, 'attachment' => $attachment]) }}" type="{{ $attachment->content_type }}">
                    </audio>
                @elseif ($attachment->isPdf() && filled($attachment->storage_path))
                    <a href="{{ route('operations.conversation-attachments.show', ['conversation' => $message->conversation_id, 'message' => $message->id, 'attachment' => $attachment]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-sky-800 hover:text-sky-950">
                        PDF attachment
                    </a>
                @elseif (filled($attachment->storage_path))
                    <a href="{{ route('operations.conversation-attachments.show', ['conversation' => $message->conversation_id, 'message' => $message->id, 'attachment' => $attachment]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-sky-800 hover:text-sky-950">
                        Download attachment
                    </a>
                @endif
            @endforeach
        </div>
    @endif
@endif
