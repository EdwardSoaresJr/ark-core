@php
    /** @var array<int, array<string, mixed>> $lead_message_drawers */
@endphp

<div
    x-show="openLeadId !== null"
    x-cloak
    class="ops-lead-message-drawer"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ops-lead-message-drawer-title"
    @keydown.escape.window="openLeadId = null"
>
    <button
        type="button"
        class="ops-lead-message-drawer__backdrop"
        aria-label="Close message"
        @click="openLeadId = null"
    ></button>

    <div class="ops-lead-message-drawer__panel">
        @foreach ($lead_message_drawers as $drawer)
            @php
                /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Conversations\ConversationMessage> $messages */
                $messages = $drawer['messages'];
                $conversation = $drawer['conversation'];
            @endphp

            <div
                x-show="openLeadId === {{ $drawer['lead_id'] }}"
                x-cloak
                class="ops-lead-message-drawer__content"
            >
                <div class="ops-lead-message-drawer__header">
                    <div class="min-w-0">
                        <p id="ops-lead-message-drawer-title" class="ops-lead-message-drawer__title">
                            {{ $drawer['contact_name'] }}
                        </p>
                        <p class="ops-lead-message-drawer__meta">
                            {{ $drawer['display_phone'] }} · {{ $drawer['source_label'] }}
                        </p>
                    </div>
                    <div class="ops-lead-message-drawer__header-actions">
                        <a href="{{ $drawer['intake_url'] }}" class="ops-page-link text-xs">Check In</a>
                        @if (filled($drawer['reply_page_url'] ?? null))
                            <a href="{{ $drawer['reply_page_url'] }}?compose=text" class="ops-page-link text-xs">Full thread</a>
                        @endif
                        <button
                            type="button"
                            class="ops-page-link text-xs"
                            @click="openLeadId = null"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div class="ops-lead-message-drawer__body">
                    <section class="ops-lead-message-drawer__section">
                        <h2 class="ops-lead-message-drawer__section-title">Concern</h2>
                        <p class="ops-lead-message-drawer__concern">{{ $drawer['concern'] }}</p>
                    </section>

                    @if ($messages->isNotEmpty())
                        <section class="ops-lead-message-drawer__section">
                            <div class="ops-lead-message-drawer__section-head">
                                <h2 class="ops-lead-message-drawer__section-title">Timeline</h2>
                                @if ($messages->count() > 1)
                                    <span class="ops-lead-message-drawer__count">{{ $messages->count() }} messages</span>
                                @endif
                            </div>
                            <div id="{{ $drawer['messages_list_id'] }}" class="ops-lead-message-drawer__timeline divide-y divide-slate-100 rounded border border-slate-200 bg-white">
                                @foreach ($messages as $message)
                                    <x-operations.conversation-message :message="$message" />
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                @if (($drawer['can_sms_reply'] ?? false) && $conversation !== null)
                    <x-operations.conversation-contact-quick-reply
                        :conversation="$conversation"
                        :display-phone="$drawer['display_phone']"
                        :messages-list-id="$drawer['messages_list_id']"
                        :has-conversation-history="$messages->isNotEmpty()"
                        class="ops-lead-message-drawer__composer"
                    />
                @endif
            </div>
        @endforeach
    </div>
</div>
