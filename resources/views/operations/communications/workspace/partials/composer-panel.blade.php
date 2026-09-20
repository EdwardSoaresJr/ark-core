@php
    /** @var array<string, mixed> $composer */
    /** @var string $section */
    $kind = $composer['kind'] ?? null;
@endphp

<footer class="ops-comms-workspace__composer">
    @if ($kind === 'platform_conversation')
        @php
            $canSend = \App\Ark\Platform\Communications\ManagedCommunicationsGate::platformSend();
            $hasHistory = ($thread['events'] ?? []) !== [];
            $displayPhone = (string) ($composer['display_phone'] ?? '');
            $callHref = \App\Ark\Operations\PhoneNumber::telUri($displayPhone)
                ?? \App\Ark\Operations\PhoneNumber::telUri((string) ($composer['contact_address'] ?? ''));
            $composerCustomer = $composer['customer'] ?? null;
            $primaryRepairOrder = $composer['repair_order'] ?? null;
            $coreConversation = $composer['conversation'] ?? null;
            $scheduleHref = filled($displayPhone)
                ? \App\Ark\Operations\Appointments\ScheduleUrl::to(['q' => $displayPhone])
                : null;
        @endphp
        @if ($canSend && $composerCustomer instanceof \App\Ark\Operations\Customers\Customer)
            <x-operations.conversation-quick-reply
                :customer="$composerCustomer"
                :repair-order="$primaryRepairOrder"
                :repair-order-id="$primaryRepairOrder?->repair_order_id"
                :conversation="$coreConversation"
                :open-repair-orders="$composer['open_repair_orders'] ?? []"
                :send-url="$composer['send_url'] ?? null"
                :send-estimate-url="$coreConversation && $primaryRepairOrder
                    ? route('operations.communications.conversations.send-estimate', $coreConversation)
                    : ($primaryRepairOrder ? route('operations.repair-orders.conversation-actions.send-estimate', $primaryRepairOrder) : null)"
                :send-payment-url="$coreConversation && $primaryRepairOrder
                    ? route('operations.communications.conversations.send-payment', $coreConversation)
                    : ($primaryRepairOrder ? route('operations.repair-orders.conversation-actions.send-payment', $primaryRepairOrder) : null)"
                :send-deposit-url="$coreConversation && $primaryRepairOrder
                    ? route('operations.communications.conversations.send-deposit', $coreConversation)
                    : ($primaryRepairOrder ? route('operations.repair-orders.conversation-actions.send-deposit', $primaryRepairOrder) : null)"
                :send-inspection-url="$primaryRepairOrder
                    ? route('operations.repair-orders.conversation-actions.send-inspection', $primaryRepairOrder)
                    : null"
                :messages-list-ids="['comms-workspace-thread-messages']"
                :has-conversation-history="$hasHistory"
                always-open
                keep-open-after-send
                show-quick-replies
                actions-menu
                platform-backed
                id="comms-thread-composer"
                class="border-0 bg-transparent"
            />
        @elseif ($canSend)
            <div
                id="comms-thread-composer"
                data-ark-workspace-dirty="off"
                data-ark-conversation-composer
                data-platform-backed="1"
                x-data="arkConversationQuickReply(@js([
                    'sendUrl' => $composer['send_url'],
                    'messagesListIds' => ['comms-workspace-thread-messages'],
                    'hasConversationHistory' => $hasHistory,
                    'alwaysOpen' => true,
                    'keepOpenAfterSend' => true,
                    'customerPhoneDisplay' => $displayPhone,
                    'showSmsComposer' => true,
                    'platformBacked' => true,
                ]))"
            >
                <div class="ops-comms-workspace__composer-compact">
                    <textarea
                        x-ref="replyBody"
                        x-model="body"
                        rows="2"
                        :placeholder="composerPlaceholder()"
                        class="ops-comms-workspace__composer-compact-input"
                        @keydown.meta.enter.prevent="send()"
                        @keydown.ctrl.enter.prevent="send()"
                    ></textarea>
                    @include('operations.communications.workspace.partials.quick-replies')
                    <div class="ops-comms-workspace__composer-compact-row">
                        <details
                            class="ops-comms-actions"
                            x-ref="actionsMenu"
                            @toggle="onActionsMenuToggle($event)"
                            @click.outside="closeActionsMenu()"
                        >
                            <summary class="ops-comms-actions__summary">Actions</summary>
                            <div class="ops-comms-actions__panel">
                                @if (filled($callHref))
                                    <a href="{{ $callHref }}" class="ops-comms-actions__item">Call</a>
                                @endif
                                @if (filled($scheduleHref))
                                    <a href="{{ $scheduleHref }}" class="ops-comms-actions__item">Schedule</a>
                                @endif
                                <label class="ops-comms-actions__item">
                                    Attach file
                                    <input
                                        x-ref="attachmentInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,application/pdf"
                                        @change="pickAttachment($event)"
                                    >
                                </label>
                            </div>
                        </details>
                        <button
                            type="button"
                            @click="send()"
                            :disabled="sending"
                            class="h-8 shrink-0 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                        >
                            <span x-show="! sending">Send</span>
                            <span x-show="sending" x-cloak>Sending…</span>
                        </button>
                    </div>
                    <p class="truncate text-[11px] font-medium text-slate-500" x-show="attachmentLabel" x-text="attachmentLabel" x-cloak></p>
                    <p class="text-xs font-semibold text-rose-700" x-show="error !== ''" x-text="error" x-cloak></p>
                </div>
            </div>
        @endif
    @elseif ($kind === 'conversation')
        @php
            $conversation = $composer['conversation'];
            $hasHistory = ($thread['events'] ?? []) !== [];
        @endphp

        <div
            class="ops-comms-workspace__composer-tabs"
            x-data="{ tab: 'reply' }"
        >
            <div class="ops-comms-workspace__composer-tablist" role="tablist">
                <button
                    type="button"
                    role="tab"
                    class="ops-comms-workspace__composer-tab"
                    :class="{ 'ops-comms-workspace__composer-tab--active': tab === 'reply' }"
                    @click="tab = 'reply'"
                >
                    SMS
                </button>
                <button
                    type="button"
                    role="tab"
                    class="ops-comms-workspace__composer-tab"
                    :class="{ 'ops-comms-workspace__composer-tab--active': tab === 'internal' }"
                    @click="tab = 'internal'"
                >
                    Internal note
                </button>
            </div>

            <div x-show="tab === 'reply'" x-cloak>
                @php
                    $composerCustomer = $composer['customer'] ?? $composer['repair_order']?->customer;
                    $primaryRepairOrder = $composer['repair_order'] ?? null;
                @endphp

                @if ($composerCustomer)
                    <x-operations.conversation-quick-reply
                        :customer="$composerCustomer"
                        :repair-order="$primaryRepairOrder"
                        :repair-order-id="$primaryRepairOrder?->repair_order_id"
                        :conversation="$conversation"
                        :open-repair-orders="$composer['open_repair_orders'] ?? []"
                        :send-estimate-url="$primaryRepairOrder ? route('operations.communications.conversations.send-estimate', $conversation) : null"
                        :send-payment-url="$primaryRepairOrder ? route('operations.communications.conversations.send-payment', $conversation) : null"
                        :send-deposit-url="$primaryRepairOrder ? route('operations.communications.conversations.send-deposit', $conversation) : null"
                        :send-inspection-url="$primaryRepairOrder ? route('operations.repair-orders.conversation-actions.send-inspection', $primaryRepairOrder) : null"
                        :messages-list-ids="['comms-workspace-thread-messages']"
                        :has-conversation-history="$hasHistory"
                        :nudge-key="$composer['nudge_key'] ?? null"
                        :entity-key="$composer['entity_key'] ?? null"
                        :initial-body="$composer['draft_reply'] ?? null"
                        always-open
                        keep-open-after-send
                        show-quick-replies
                        actions-menu
                        id="comms-thread-composer"
                        class="border-0 bg-transparent"
                    />
                @else
                    <x-operations.conversation-contact-quick-reply
                        :conversation="$conversation"
                        :display-phone="$composer['display_phone'] ?? ''"
                        messages-list-id="comms-workspace-thread-messages"
                        :has-conversation-history="$hasHistory"
                        :nudge-key="$composer['nudge_key'] ?? null"
                        :entity-key="$composer['entity_key'] ?? null"
                        :initial-body="$composer['draft_reply'] ?? null"
                        actions-menu
                        id="comms-thread-composer"
                    />
                @endif
            </div>

            <form
                x-show="tab === 'internal'"
                x-cloak
                method="POST"
                action="{{ route('operations.communications.conversations.internal-note', $conversation) }}"
                class="ops-comms-workspace__composer-form"
            >
                @csrf
                <input type="hidden" name="section" value="{{ $section }}">
                <textarea
                    name="body"
                    rows="2"
                    required
                    maxlength="2000"
                    placeholder="Internal note — never sent to customer"
                    class="ops-comms-workspace__composer-input"
                ></textarea>
                <div class="ops-comms-workspace__composer-actions">
                    <button type="submit" class="ops-comms-workspace__composer-submit">Add internal note</button>
                </div>
            </form>
        </div>
    @elseif ($kind === 'call')
        <form
            id="comms-call-note-composer"
            method="POST"
            action="{{ route('operations.communications.calls.note', $composer['call_session_id']) }}"
            class="ops-comms-workspace__composer-form"
        >
            @csrf
            <input type="hidden" name="section" value="{{ $section }}">
            <input type="hidden" name="entity_key" value="{{ $composer['entity_key'] ?? ('call:'.($composer['call_session_id'] ?? '')) }}">
            <input type="hidden" name="nudge_key" value="{{ $composer['nudge_key'] ?? 'call.log_note' }}">
            <p class="ops-comms-workspace__composer-label">Call note</p>
            <textarea
                name="body"
                rows="2"
                required
                maxlength="2000"
                placeholder="Log what happened on this call"
                class="ops-comms-workspace__composer-input"
            >{{ $composer['draft_reply'] ?? '' }}</textarea>
            <div class="ops-comms-workspace__composer-actions">
                <button type="submit" class="ops-comms-workspace__composer-submit">Log call note</button>
            </div>
        </form>
    @endif
</footer>
