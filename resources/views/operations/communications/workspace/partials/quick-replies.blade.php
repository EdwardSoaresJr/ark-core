@php
    use App\Ark\Operations\Communications\CommunicationsAccentColor;
    use App\Ark\Operations\Communications\CommunicationsQuickReplyTemplates;

    $quickReplies = CommunicationsQuickReplyTemplates::all();
@endphp

@if ($quickReplies !== [])
    <div class="d-flex flex-wrap gap-1" role="group" aria-label="Quick replies" data-comms-quick-replies>
        @foreach ($quickReplies as $template)
            @php
                $chipClass = match (CommunicationsAccentColor::normalize($template['color'] ?? null)) {
                    CommunicationsAccentColor::SLATE => 'btn-outline-secondary',
                    CommunicationsAccentColor::BLUE => 'btn-outline-azure',
                    CommunicationsAccentColor::AMBER => 'btn-outline-yellow',
                    CommunicationsAccentColor::ORANGE => 'btn-outline-orange',
                    CommunicationsAccentColor::GREEN => 'btn-outline-green',
                    default => '',
                };
            @endphp
            <button
                type="button"
                class="btn btn-sm {{ $chipClass }}"
                data-quick-reply-color="{{ CommunicationsAccentColor::normalize($template['color'] ?? null) }}"
                @click="body = @js($template['body']); $refs.replyBody?.focus()"
            >
                {{ $template['label'] }}
            </button>
        @endforeach
    </div>
@endif
