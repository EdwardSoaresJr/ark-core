@php
    /** @var array<string, mixed> $composer */
    /** @var string $section */
    $kind = $composer['kind'] ?? null;
@endphp

<footer class="ops-comms-workspace__composer">
    @if ($kind === 'conversation')
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
                    Reply
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

                <div class="ops-comms-workspace__composer-transport" aria-label="Reply transport" x-show="tab === 'reply'">
                    <span class="ops-comms-workspace__composer-transport-tab ops-comms-workspace__composer-transport-tab--active">SMS</span>
                    <span class="ops-comms-workspace__composer-transport-tab ops-comms-workspace__composer-transport-tab--disabled" title="Email from thread — coming soon">Email</span>
                    @if (filled($composer['display_phone'] ?? null))
                        <a
                            href="tel:{{ preg_replace('/\D+/', '', (string) ($composer['conversation']->contact_address ?? '')) }}"
                            class="ops-comms-workspace__composer-transport-tab"
                        >
                            Call
                        </a>
                    @else
                        <span class="ops-comms-workspace__composer-transport-tab ops-comms-workspace__composer-transport-tab--disabled">Call</span>
                    @endif
                </div>
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
