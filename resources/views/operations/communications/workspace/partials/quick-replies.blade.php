@php
    use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;

    $quickReplies = CommunicationsQuickReplyTemplates::all();
@endphp

@if ($quickReplies !== [])
    <div class="ops-comms-workspace__quick-replies-list" aria-label="Quick replies">
        @foreach ($quickReplies as $template)
            @php
                $chipStyle = \App\Ark\Operations\Communications\CommunicationsAccentColor::chipStyle($template['color'] ?? null);
            @endphp
            <button
                type="button"
                class="ops-comms-workspace__quick-reply-chip"
                @if ($chipStyle) style="{{ $chipStyle }}" @endif
                @click="body = @js($template['body']); $refs.replyBody?.focus()"
            >
                {{ $template['label'] }}
            </button>
        @endforeach
    </div>
@endif
