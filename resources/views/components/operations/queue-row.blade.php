@props([
    'row',
    'showTimestamp' => false,
    'variant' => 'list',
])

@php
    /** @var array<string, mixed> $row */
    $kind = (string) ($row['kind'] ?? 'call');
    $isCall = $kind === 'call';
    $isInterrupt = $variant === 'interrupt';
    $channelLabel = (string) ($row['channel_label'] ?? ($isCall ? 'Call' : strtoupper($kind)));
    $stateLabel = (string) ($row['state_label'] ?? '');
    $tone = match ($row['state'] ?? '') {
        'ringing' => 'ringing',
        'missed', 'failed' => 'missed',
        'answered', 'active' => 'active',
        'unread' => $isCall ? 'missed' : 'active',
        default => 'completed',
    };
    $callSessionId = (int) ($row['call_session_id'] ?? 0);
    $touchCall = $callSessionId > 0
        ? "window.arkMarkCallSessionHandled?.({$callSessionId})"
        : null;
    $replyUrl = $row['reply_url'] ?? null;

    if (
        ! $isCall
        && ! filled($replyUrl)
        && ($row['direction'] ?? '') === 'inbound'
        && in_array($kind, ['sms', 'mms'], true)
    ) {
        if (filled($row['customer_url'] ?? null)) {
            $replyUrl = $row['customer_url'].'?compose=text#customer-communication';
        } elseif (filled($row['conversation_id'] ?? null)) {
            $replyUrl = route('operations.conversations.reply', $row['conversation_id']).'?compose=text#conversation-composer';
        }
    }

    $rowClasses = ['ops-queue-row'];

    if ($isInterrupt) {
        if (in_array($kind, ['sms', 'mms', 'messenger'], true)) {
            $rowClasses[] = 'ops-queue-row--message';
        } elseif (! empty($row['has_voicemail'])) {
            $rowClasses[] = 'ops-queue-row--voicemail';
        }
    }

    $statusChipTone = $tone;
    if ($isInterrupt && $isCall) {
        $statusChipTone = match (true) {
            ! empty($row['has_voicemail']) => 'voicemail',
            ! empty($row['has_recording']) => 'recording',
            default => $tone,
        };
    }
@endphp

<li @class($rowClasses)>
    <div class="ops-queue-row__main">
        @if ($isInterrupt)
            <div class="ops-queue-row__top">
                <div class="ops-queue-row__status">
                    @if (! empty($row['direction_label']))
                        <span class="ops-call-queue__chip ops-call-queue__chip--direction ops-call-queue__chip--direction-{{ $row['direction'] ?? 'inbound' }}">{{ $row['direction_label'] }}</span>
                    @endif
                    @if ($isCall)
                        <span class="ops-call-queue__chip ops-call-queue__chip--channel">{{ $channelLabel }}</span>
                        <span class="ops-call-queue__chip ops-call-queue__chip--{{ $statusChipTone }}">{{ $stateLabel }}</span>
                    @else
                        <span class="ops-call-queue__chip ops-call-queue__chip--channel">{{ $channelLabel }}</span>
                    @endif
                    @if (! empty($row['has_attachment']) || $kind === 'mms')
                        <span class="ops-call-queue__chip ops-call-queue__chip--muted">Attachment</span>
                    @endif
                </div>
                @if (! empty($row['age_label']))
                    <span class="ops-queue-row__age">{{ $row['age_label'] }}</span>
                @endif
            </div>
        @else
            <div class="ops-queue-row__status">
                @if (! empty($row['direction_label']))
                    <span class="ops-call-queue__chip ops-call-queue__chip--direction ops-call-queue__chip--direction-{{ $row['direction'] ?? 'inbound' }}">{{ $row['direction_label'] }}</span>
                @endif
                @if ($isCall)
                    <span class="ops-call-queue__chip ops-call-queue__chip--channel">{{ $channelLabel }}</span>
                    <span class="ops-call-queue__status ops-call-queue__status--{{ $tone }}">{{ $stateLabel }}</span>
                @else
                    <span class="ops-call-queue__chip ops-call-queue__chip--channel">{{ $channelLabel }}</span>
                @endif
                @if (! empty($row['has_attachment']))
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Attachment</span>
                @endif
                @if (! empty($row['has_voicemail']))
                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-800">Voicemail</span>
                @endif
                @if (! empty($row['has_recording']))
                    <span class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Recording</span>
                @endif
            </div>
        @endif

        <p class="ops-queue-row__identity">{{ $row['headline'] ?? 'Unknown' }}</p>

        @if (! empty($row['orientation']))
            @include('operations.orientation.partials.snippet', [
                'orientation' => $row['orientation'],
            ])
        @endif

        <p class="ops-queue-row__context">
            @if ($showTimestamp && ! empty($row['occurred_at_label']))
                <span class="font-semibold text-slate-700">{{ $row['occurred_at_label'] }}</span>
                <span class="ops-call-queue__dot">·</span>
            @endif
            @if (! empty($row['snippet']))
                <span class="ops-queue-row__snippet text-slate-700">"{{ $row['snippet'] }}"</span>
                <span class="ops-call-queue__dot">·</span>
            @endif
            <span>{{ $row['context_summary'] ?? '' }}</span>
            @if (! $isInterrupt && ! empty($row['age_label']))
                <span class="ops-call-queue__dot">·</span>
                <span>{{ $row['age_label'] }}</span>
            @endif
        </p>

        @if ($isCall && ! empty($row['owned_by_name']))
            <p class="ops-queue-row__meta">
                @if (! empty($row['is_owned_by_me']))
                    Owned by me
                @else
                    Owned by {{ $row['owned_by_name'] }}
                @endif
            </p>
        @endif

        @if (! $isCall && ! empty($row['display_phone']))
            <p class="ops-queue-row__meta">{{ $row['display_phone'] }}</p>
        @endif
    </div>

    @if ($isInterrupt)
        <div class="ops-queue-row__action-rows">
            <div class="ops-queue-row__actions ops-queue-row__actions--main ops-call-queue__actions ops-call-queue__actions--main">
                @if ($isCall)
                    @if (! empty($row['show_callback_action']))
                        <button
                            type="button"
                            class="ops-call-queue__action ops-call-queue__action--primary"
                            onclick="window.arkInitiateTelephonyCallback?.({
                                customerId: {{ $row['callback_customer_id'] ? (int) $row['callback_customer_id'] : 'null' }},
                                phone: @js($row['callback_phone'] ?? null),
                                callSessionId: {{ $callSessionId > 0 ? $callSessionId : 'null' }},
                                button: this,
                            })"
                        >Callback</button>
                    @endif
                    @if (! empty($row['show_text_customer_action']) && ! empty($row['text_customer_url']))
                        <a href="{{ $row['text_customer_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Text</a>
                    @endif
                @elseif (filled($replyUrl) && ($row['direction'] ?? '') === 'inbound' && in_array($kind, ['sms', 'mms'], true))
                    <a href="{{ $replyUrl }}" class="ops-call-queue__action ops-call-queue__action--primary">Reply</a>
                @endif
                @if (! empty($row['matched']) && ! empty($row['customer_url']))
                    <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Customer</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['create_contact_url']))
                    <a
                        href="{{ $row['create_contact_url'] }}"
                        class="ops-call-queue__action{{ ($isCall || filled($replyUrl)) ? '' : ' ops-call-queue__action--primary' }}"
                        @if ($touchCall) onclick="{{ $touchCall }}" @endif
                    >Contact</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['intake_url']))
                    <a
                        href="{{ $row['intake_url'] }}"
                        class="ops-call-queue__action{{ ($isCall || filled($replyUrl)) ? '' : ' ops-call-queue__action--primary' }}"
                        @if ($touchCall) onclick="{{ $touchCall }}" @endif
                    >Check In</a>
                @endif
                @if (! empty($row['primary_ro_url']))
                    <a href="{{ $row['primary_ro_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Open RO</a>
                @elseif (! empty($row['open_ros_url']))
                    <a href="{{ $row['open_ros_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Open ROs</a>
                @endif
            </div>

            <div class="ops-queue-row__actions ops-queue-row__actions--secondary ops-call-queue__actions ops-call-queue__actions--secondary">
                @if ($isCall)
                    @if (! empty($row['show_play_voicemail_action']) && ! empty($row['voicemail_url']))
                        <a href="{{ $row['voicemail_url'] }}" class="ops-call-queue__action ops-call-queue__action--voicemail" target="_blank" rel="noopener noreferrer" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Voicemail</a>
                    @endif
                    @if (! empty($row['show_play_recording_action']) && ! empty($row['recording_url']))
                        <a href="{{ $row['recording_url'] }}" class="ops-call-queue__action ops-call-queue__action--recording" target="_blank" rel="noopener noreferrer" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Recording</a>
                    @endif
                    @if (! empty($row['lookup_url']))
                        <a href="{{ $row['lookup_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Lookup</a>
                    @endif
                    @if (! empty($row['show_claim_action']) && $callSessionId > 0)
                        <button type="button" class="ops-call-queue__action" data-call-queue-claim="{{ $callSessionId }}">Claim</button>
                    @endif
                    @if (! empty($row['is_owned_by_me']))
                        <span class="ops-call-queue__action ops-call-queue__action--owned">Mine</span>
                    @endif
                    @if (! empty($row['show_handled_action']) && $callSessionId > 0)
                        <button
                            type="button"
                            class="ops-call-queue__action ops-call-queue__action--ghost"
                            title="We've handled this call — customer context is covered on the shop floor"
                            data-call-queue-mark-worked="{{ $callSessionId }}"
                        >Handled</button>
                    @endif
                @else
                    @if (! empty($row['lookup_url']))
                        <a href="{{ $row['lookup_url'] }}" class="ops-call-queue__action">Lookup</a>
                    @endif
                    @if (! empty($row['show_mark_read_action']) && ! empty($row['conversation_id']))
                        <button type="button" class="ops-call-queue__action ops-call-queue__action--ghost" data-call-queue-mark-read="{{ (int) $row['conversation_id'] }}">Mark read</button>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="ops-queue-row__actions ops-call-queue__actions">
            @if ($isCall)
                @if (! empty($row['show_callback_action']))
                    <button
                        type="button"
                        class="ops-call-queue__action ops-call-queue__action--primary"
                        onclick="window.arkInitiateTelephonyCallback?.({
                            customerId: {{ $row['callback_customer_id'] ? (int) $row['callback_customer_id'] : 'null' }},
                            phone: @js($row['callback_phone'] ?? null),
                            callSessionId: {{ $callSessionId > 0 ? $callSessionId : 'null' }},
                            button: this,
                        })"
                    >Callback</button>
                @endif
                @if (! empty($row['show_text_customer_action']) && ! empty($row['text_customer_url']))
                    <a href="{{ $row['text_customer_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Text Customer</a>
                @endif
                @if (! empty($row['matched']) && ! empty($row['customer_url']))
                    <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Open Customer</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['create_contact_url']))
                    <a href="{{ $row['create_contact_url'] }}" class="ops-call-queue__action ops-call-queue__action--primary" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Create Contact</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['intake_url']))
                    <a href="{{ $row['intake_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Start Check In</a>
                @endif
                @if (! empty($row['primary_ro_url']))
                    <a href="{{ $row['primary_ro_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Open RO</a>
                @elseif (! empty($row['open_ros_url']))
                    <a href="{{ $row['open_ros_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Open ROs</a>
                @endif
                @if (! empty($row['lookup_url']))
                    <a href="{{ $row['lookup_url'] }}" class="ops-call-queue__action" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Lookup</a>
                @endif
                @if (! empty($row['has_voicemail']) && ! empty($row['voicemail_url']))
                    <a href="{{ $row['voicemail_url'] }}" class="ops-call-queue__action ops-call-queue__action--voicemail" target="_blank" rel="noopener noreferrer" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Play Voicemail</a>
                @endif
                @if (! empty($row['has_recording']) && ! empty($row['recording_url']))
                    <a href="{{ $row['recording_url'] }}" class="ops-call-queue__action ops-call-queue__action--recording" target="_blank" rel="noopener noreferrer" @if ($touchCall) onclick="{{ $touchCall }}" @endif>Play Recording</a>
                @endif
                @if (! empty($row['show_handled_action']) && $callSessionId > 0)
                    <form method="POST" action="{{ route('operations.telephony.call-queue.worked', ['callSession' => $callSessionId]) }}" class="inline">
                        @csrf
                        <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Mark Handled</button>
                    </form>
                @endif
            @else
                @if (filled($replyUrl) && ($row['direction'] ?? '') === 'inbound' && in_array($kind, ['sms', 'mms', 'messenger'], true))
                    <a href="{{ $replyUrl }}" class="ops-call-queue__action ops-call-queue__action--primary">Reply</a>
                @endif
                @if (! empty($row['matched']) && ! empty($row['customer_url']))
                    <a href="{{ $row['customer_url'] }}" class="ops-call-queue__action">Open Customer</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['create_contact_url']))
                    <a href="{{ $row['create_contact_url'] }}" class="ops-call-queue__action{{ filled($replyUrl) ? '' : ' ops-call-queue__action--primary' }}">Create Contact</a>
                @endif
                @if (empty($row['matched']) && ! empty($row['intake_url']))
                    <a href="{{ $row['intake_url'] }}" class="ops-call-queue__action{{ filled($replyUrl) ? '' : ' ops-call-queue__action--primary' }}">Start Check In</a>
                @endif
                @if (! empty($row['show_link_customer_action']) && ! empty($row['link_customer_url']))
                    @include('operations.communications.partials.messenger-link-customer', ['row' => $row])
                @endif
                @if (! empty($row['primary_ro_url']))
                    <a href="{{ $row['primary_ro_url'] }}" class="ops-call-queue__action">Open RO</a>
                @elseif (! empty($row['open_ros_url']))
                    <a href="{{ $row['open_ros_url'] }}" class="ops-call-queue__action">Open ROs</a>
                @endif
                @if (! empty($row['lookup_url']))
                    <a href="{{ $row['lookup_url'] }}" class="ops-call-queue__action">Lookup</a>
                @endif
                @if (! empty($row['show_mark_read_action']) && ! empty($row['mark_read_url']))
                    <form method="POST" action="{{ $row['mark_read_url'] }}" class="inline">
                        @csrf
                        <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Mark Read</button>
                    </form>
                @endif
            @endif
        </div>
    @endif
</li>
