@php
    /** @var array<string, mixed> $composer */
    /** @var string $section */
    $kind = $composer['kind'] ?? null;
@endphp

<footer class="card-footer p-0" data-comms-composer>
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
                ui="tabler"
                id="comms-thread-composer"
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
                <div class="px-3 py-2">
                    <label class="visually-hidden" for="comms-thread-composer-body">Message</label>
                    <textarea
                        id="comms-thread-composer-body"
                        x-ref="replyBody"
                        x-model="body"
                        rows="2"
                        :placeholder="composerPlaceholder()"
                        class="form-control"
                        @keydown.meta.enter.prevent="send()"
                        @keydown.ctrl.enter.prevent="send()"
                    ></textarea>
                    <div class="mt-2">
                        @include('operations.communications.workspace.partials.quick-replies')
                    </div>
                    <div class="small text-secondary text-truncate mt-1" x-show="attachmentLabel" x-text="attachmentLabel" x-cloak></div>
                    <div class="small text-danger fw-medium mt-1" x-show="error !== ''" x-text="error" x-cloak></div>
                </div>
                <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-top" role="group" aria-label="Conversation commands">
                    <details
                        class="position-relative"
                        x-ref="actionsMenu"
                        @toggle="onActionsMenuToggle($event)"
                        @click.outside="closeActionsMenu()"
                    >
                        <summary class="btn btn-sm">Actions</summary>
                        <div class="dropdown-menu show position-absolute bottom-100 mb-1" data-comms-actions-panel>
                            @if (filled($callHref))
                                <a href="{{ $callHref }}" class="dropdown-item">
                                    <x-tabler.icon name="phone" class="dropdown-item-icon" />
                                    Call
                                </a>
                            @endif
                            @if (filled($scheduleHref))
                                <a href="{{ $scheduleHref }}" class="dropdown-item">
                                    <x-tabler.icon name="calendar" class="dropdown-item-icon" />
                                    Schedule
                                </a>
                            @endif
                            <label class="dropdown-item">
                                <x-tabler.icon name="paperclip" class="dropdown-item-icon" />
                                Attach file
                                <input
                                    x-ref="attachmentInput"
                                    type="file"
                                    multiple
                                    class="d-none"
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
                        class="btn btn-primary btn-sm"
                    >
                        <span x-show="! sending">Send</span>
                        <span x-show="sending" x-cloak>Sending…</span>
                    </button>
                </div>
            </div>
        @endif
    @elseif ($kind === 'conversation')
        @php
            $conversation = $composer['conversation'];
            $hasHistory = ($thread['events'] ?? []) !== [];
        @endphp

        <div x-data="{ tab: 'reply' }" data-comms-composer-tabs>
            <ul class="nav nav-tabs px-2 pt-1" role="tablist" aria-label="Reply or note">
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        role="tab"
                        id="comms-composer-tab-reply"
                        aria-controls="comms-composer-panel-reply"
                        class="nav-link"
                        :class="{ active: tab === 'reply' }"
                        :aria-selected="(tab === 'reply').toString()"
                        @click="tab = 'reply'"
                    >
                        SMS
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        role="tab"
                        id="comms-composer-tab-internal"
                        aria-controls="comms-composer-panel-internal"
                        class="nav-link"
                        :class="{ active: tab === 'internal' }"
                        :aria-selected="(tab === 'internal').toString()"
                        @click="tab = 'internal'"
                    >
                        Internal note
                    </button>
                </li>
            </ul>

            <div
                id="comms-composer-panel-reply"
                role="tabpanel"
                aria-labelledby="comms-composer-tab-reply"
                x-show="tab === 'reply'"
                x-cloak
            >
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
                        ui="tabler"
                        id="comms-thread-composer"
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
                id="comms-composer-panel-internal"
                role="tabpanel"
                aria-labelledby="comms-composer-tab-internal"
                x-show="tab === 'internal'"
                x-cloak
                method="POST"
                action="{{ route('operations.communications.conversations.internal-note', $conversation) }}"
            >
                @csrf
                <input type="hidden" name="section" value="{{ $section }}">
                <div class="px-3 py-2">
                    <label class="visually-hidden" for="comms-internal-note-body">Internal note</label>
                    <textarea
                        id="comms-internal-note-body"
                        name="body"
                        rows="2"
                        required
                        maxlength="2000"
                        placeholder="Internal note - never sent to customer"
                        class="form-control"
                    ></textarea>
                </div>
                <div class="d-flex justify-content-end px-3 py-2 border-top">
                    <button type="submit" class="btn btn-sm">Add internal note</button>
                </div>
            </form>
        </div>
    @elseif ($kind === 'call')
        <form
            id="comms-call-note-composer"
            method="POST"
            action="{{ route('operations.communications.calls.note', $composer['call_session_id']) }}"
        >
            @csrf
            <input type="hidden" name="section" value="{{ $section }}">
            <input type="hidden" name="entity_key" value="{{ $composer['entity_key'] ?? ('call:'.($composer['call_session_id'] ?? '')) }}">
            <input type="hidden" name="nudge_key" value="{{ $composer['nudge_key'] ?? 'call.log_note' }}">
            <div class="px-3 py-2">
                <label class="form-label" for="comms-call-note-body">Call note</label>
                <textarea
                    id="comms-call-note-body"
                    name="body"
                    rows="2"
                    required
                    maxlength="2000"
                    placeholder="Log what happened on this call"
                    class="form-control"
                >{{ $composer['draft_reply'] ?? '' }}</textarea>
            </div>
            <div class="d-flex justify-content-end px-3 py-2 border-top">
                <button type="submit" class="btn btn-primary btn-sm">Log call note</button>
            </div>
        </form>
    @endif
</footer>
