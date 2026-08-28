@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
    <div x-data="arkCommsInterrupt()" x-init="init()">
        <div
            x-show="(activeCall !== null || activeMessage !== null) && attentionGateEnabled()"
            x-cloak
            class="ops-comms-interrupt-backdrop"
            aria-hidden="true"
        ></div>

        <div
            x-show="activeCall !== null"
            x-cloak
            x-ref="callInterruptDialog"
            class="ops-incoming-call-pop"
            role="alertdialog"
            aria-modal="true"
            aria-live="assertive"
            tabindex="-1"
            @mousedown="steerInterruptFocus($event)"
            @keydown.enter.prevent="activateInterruptPrimary($event)"
        >
            <div x-show="activeCall !== null" class="ops-incoming-call-pop__card">
                    <div class="ops-incoming-call-pop__head">
                        <div class="min-w-0">
                            <p class="ops-incoming-call-pop__eyebrow" x-text="callEyebrow()"></p>
                            <template x-if="activeCall.matched">
                                <p class="ops-incoming-call-pop__title" x-text="activeCall.customer_name"></p>
                            </template>
                            <template x-if="! activeCall.matched">
                                <p class="ops-incoming-call-pop__title">Unknown Caller</p>
                            </template>
                            <p class="ops-incoming-call-pop__phone" x-text="activeCall.display_phone"></p>
                            <template x-if="activeCall.owned_by_name">
                                <p class="ops-incoming-call-pop__owner" x-text="ownerLabel()"></p>
                            </template>
                        </div>
                        <button
                            type="button"
                            class="ops-incoming-call-pop__dismiss"
                            @click="dismissCall()"
                        >Dismiss</button>
                    </div>

                    <template x-if="activeCall.matched">
                        <div class="ops-incoming-call-pop__meta">
                            <p class="truncate text-xs font-semibold text-slate-700" x-text="vehicleSummary()"></p>
                            <p class="text-xs text-slate-500" x-text="openRoSummary()"></p>
                        </div>
                    </template>

                    <template x-if="activeCall?.orientation?.situation">
                        <div class="ops-incoming-call-pop__orientation">
                            <p class="ops-incoming-call-pop__orientation-eyebrow">Current Situation</p>
                            <p class="ops-incoming-call-pop__orientation-situation" x-text="activeCall.orientation.situation"></p>
                            <p class="ops-incoming-call-pop__orientation-story" x-text="activeCall.orientation.progress_stopped_because"></p>
                            <template x-if="activeCall.orientation.next_action">
                                <p class="ops-incoming-call-pop__orientation-next">
                                    <span>Next:</span>
                                    <span x-text="activeCall.orientation.next_action"></span>
                                </p>
                            </template>
                        </div>
                    </template>

                    <template x-if="activeCall.matched && previewOpenRos().length > 0">
                        <div class="ops-incoming-call-pop__ros">
                            <template x-for="openRepairOrder in previewOpenRos()" :key="openRepairOrder.repair_order_id">
                                <a
                                    :href="openRepairOrder.url"
                                    class="ops-incoming-call-pop__ro"
                                    @click="markCallHandledForId(activeCall.call_session_id)"
                                >
                                    <span class="font-bold text-slate-950" x-text="'RO #' + openRepairOrder.repair_order_id"></span>
                                    <span class="truncate text-slate-600" x-text="openRepairOrder.vehicle_name"></span>
                                    <span class="shrink-0 text-slate-500" x-text="openRepairOrder.status_label"></span>
                                </a>
                            </template>
                            <template x-if="extraOpenRoCount() > 0 && activeCall.customer_url">
                                <a :href="activeCall.customer_url" class="ops-incoming-call-pop__more" @click="markCallHandledForId(activeCall.call_session_id)">
                                    +<span x-text="extraOpenRoCount()"></span> more on Customer Hub
                                </a>
                            </template>
                        </div>
                    </template>

                    <template x-if="lastConversationSnippet()">
                        <p class="ops-incoming-call-pop__conversation" x-text="lastConversationSnippet()"></p>
                    </template>

                    <div class="ops-incoming-call-pop__actions">
                        <button
                            x-show="! ownedByOther() && activeCall.show_callback_action"
                            type="button"
                            data-comms-interrupt-primary
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                            @click="window.arkInitiateTelephonyCallback?.({
                                customerId: activeCall.callback_customer_id,
                                phone: activeCall.callback_phone,
                                callSessionId: activeCall.call_session_id,
                                button: $event.currentTarget,
                            }); activeCall = null;"
                        >Callback</button>

                        <a
                            x-show="! ownedByOther() && activeCall.matched && activeCall.customer_url"
                            :href="activeCall.customer_url"
                            class="ops-incoming-call-pop__action"
                            @click="markCallHandledForId(activeCall.call_session_id)"
                        >Open Customer</a>

                        <a
                            x-show="! ownedByOther() && ! activeCall.matched && activeCall.create_contact_url"
                            :href="activeCall.create_contact_url"
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                            @click="markCallHandledForId(activeCall.call_session_id)"
                        >Create Contact</a>

                        <a
                            x-show="! ownedByOther() && ! activeCall.matched"
                            :href="activeCall.intake_url"
                            class="ops-incoming-call-pop__action"
                            @click="markCallHandledForId(activeCall.call_session_id)"
                        >Start Check In</a>

                        <a
                            x-show="! ownedByOther()"
                            :href="activeCall.lookup_url"
                            class="ops-incoming-call-pop__action"
                            @click="markCallHandledForId(activeCall.call_session_id)"
                        >Caller Lookup</a>

                        <button
                            x-show="! ownedByOther()"
                            type="button"
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--ghost"
                            @click="markCallHandled()"
                        >Mark Handled</button>
                    </div>
            </div>
        </div>

        <div
            x-show="activeMessage !== null && activeCall === null"
            x-cloak
            x-ref="messageInterruptDialog"
            class="ops-incoming-message-pop"
            role="alertdialog"
            aria-modal="true"
            aria-live="assertive"
            tabindex="-1"
            @mousedown="steerInterruptFocus($event)"
            @keydown.enter.prevent="activateInterruptPrimary($event)"
        >
            <div x-show="activeMessage !== null" class="ops-incoming-message-pop__card">
                    <div class="ops-incoming-call-pop__head">
                        <div class="min-w-0">
                            <p class="ops-incoming-message-pop__eyebrow" x-text="channelLabel()"></p>
                            <p class="ops-incoming-call-pop__title" x-text="activeMessage.headline ?? 'Unknown'"></p>
                            <p class="ops-incoming-call-pop__phone" x-text="activeMessage.display_phone"></p>
                        </div>
                    </div>

                    <div class="ops-incoming-message-pop__body">
                        <p
                            class="ops-incoming-message-pop__snippet"
                            x-show="snippetPreview() !== ''"
                            x-text="snippetPreview()"
                        ></p>
                        <template x-if="activeMessage?.orientation?.situation">
                            <div class="ops-incoming-call-pop__orientation">
                                <p class="ops-incoming-call-pop__orientation-eyebrow">Current Situation</p>
                                <p class="ops-incoming-call-pop__orientation-situation" x-text="activeMessage.orientation.situation"></p>
                                <p class="ops-incoming-call-pop__orientation-story" x-text="activeMessage.orientation.progress_stopped_because"></p>
                                <template x-if="activeMessage.orientation.next_action">
                                    <p class="ops-incoming-call-pop__orientation-next">
                                        <span>Next:</span>
                                        <span x-text="activeMessage.orientation.next_action"></span>
                                    </p>
                                </template>
                            </div>
                        </template>
                        <template x-if="messageAttachments().length > 0">
                            <div class="ops-incoming-message-pop__attachments">
                                <template x-for="attachment in messageAttachments()" :key="attachment.id">
                                    <template x-if="attachment.is_image && attachment.url">
                                        <button
                                            type="button"
                                            class="ops-incoming-message-pop__attachment-thumb"
                                            :data-ops-lightbox="attachment.url"
                                            data-ops-lightbox-alt="Message attachment"
                                        >
                                            <img
                                                :src="attachment.url"
                                                alt="Attachment"
                                                class="ops-incoming-message-pop__attachment-image"
                                            >
                                        </button>
                                    </template>
                                    <template x-if="attachment.is_video && attachment.url">
                                        <video
                                            controls
                                            class="ops-incoming-message-pop__attachment-video"
                                            preload="metadata"
                                        >
                                            <source :src="attachment.url" :type="attachment.content_type">
                                        </video>
                                    </template>
                                    <template x-if="attachment.is_audio && attachment.url">
                                        <audio
                                            controls
                                            class="ops-incoming-message-pop__attachment-audio"
                                            preload="metadata"
                                        >
                                            <source :src="attachment.url" :type="attachment.content_type">
                                        </audio>
                                    </template>
                                    <template x-if="! attachment.is_image && ! attachment.is_video && ! attachment.is_audio && attachment.url">
                                        <a
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noopener"
                                            class="ops-incoming-message-pop__attachment-link"
                                            x-text="attachment.is_pdf ? 'PDF attachment' : 'Download attachment'"
                                        ></a>
                                    </template>
                                </template>
                            </div>
                        </template>
                        <p
                            class="ops-incoming-message-pop__context"
                            x-show="activeMessage.context_summary"
                            x-text="activeMessage.context_summary"
                        ></p>
                    </div>

                    <div class="ops-incoming-call-pop__actions">
                        <a
                            x-show="activeMessage?.kind === 'website_lead' && activeMessage?.intake_url"
                            :href="activeMessage.intake_url"
                            data-comms-interrupt-primary
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                            @click="openWebsiteLeadIntake($event)"
                        >Check In</a>
                        <a
                            x-show="activeMessage?.kind === 'portal' && activeMessage?.repair_order_url"
                            :href="activeMessage.repair_order_url"
                            data-comms-interrupt-primary
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                            @click="activeMessage?.show_mark_read_action ? markMessageRead() : dismissPortal()"
                        >Open RO</a>
                        <a
                            x-show="activeMessage?.kind !== 'portal' && activeMessage?.direction === 'inbound' && activeMessage?.reply_url"
                            :href="activeMessage.reply_url"
                            data-comms-interrupt-primary
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                            @click="replyToMessage($event)"
                        >Reply</a>
                        <a
                            x-show="activeMessage?.matched && activeMessage?.customer_url"
                            :href="activeMessage.customer_url"
                            class="ops-incoming-call-pop__action"
                        >Open Customer</a>
                        <a
                            x-show="! activeMessage?.matched && activeMessage?.create_contact_url && activeMessage?.kind !== 'website_lead'"
                            :href="activeMessage.create_contact_url"
                            data-comms-interrupt-primary
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--primary"
                        >Create Contact</a>
                        <a
                            x-show="activeMessage?.kind === 'website_lead' && ! activeMessage?.matched && activeMessage?.create_contact_url"
                            :href="activeMessage.create_contact_url"
                            class="ops-incoming-call-pop__action"
                            @click="dismissWebsiteLead()"
                        >Create contact</a>
                        <a
                            x-show="! activeMessage?.matched && activeMessage?.intake_url && ! activeMessage?.reply_url && activeMessage?.kind !== 'website_lead'"
                            :href="activeMessage.intake_url"
                            class="ops-incoming-call-pop__action"
                        >Start Check In</a>
                        <a
                            x-show="activeMessage?.lookup_url"
                            :href="activeMessage.lookup_url"
                            class="ops-incoming-call-pop__action"
                        >Lookup</a>
                        <button
                            type="button"
                            class="ops-incoming-call-pop__action ops-incoming-call-pop__action--ghost"
                            @click="activeMessage?.kind === 'website_lead'
                                ? dismissWebsiteLead()
                                : (activeMessage?.kind === 'portal' ? dismissPortal() : markMessageRead())"
                        >Dismiss</button>
                    </div>
            </div>
        </div>
    </div>
@endcan
