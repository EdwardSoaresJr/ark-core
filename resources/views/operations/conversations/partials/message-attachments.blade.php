@php
    /** @var \App\Ark\Operations\Conversations\ConversationMessage $message */
@endphp

@if ($message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
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
