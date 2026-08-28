import { arkEchoEnabled, getArkEcho } from './ark-echo';
import { arkOpsToast } from './ark-ops-toast';
import {
    conversationSendBusyLabel,
    estimateDeliveryBusyLabel,
    paymentDeliveryBusyLabel,
    depositDeliveryBusyLabel,
    withWorksheetBusy,
} from './ark-worksheet-busy';
import { deliveryChannelBlockReason, deliveryHttpErrorMessage, deliveryPayload } from './ark-delivery-errors';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function arkConversationQuickReply(config = {}) {
    return {
        sendUrl: config.sendUrl ?? '',
        repairOrderId: config.repairOrderId ?? null,
        openRepairOrders: config.openRepairOrders ?? [],
        selectedRepairOrderId: config.repairOrderId ?? config.openRepairOrders?.[0]?.repair_order_id ?? null,
        sendEstimateUrl: config.sendEstimateUrl ?? null,
        cancelScheduledEstimateUrl: config.cancelScheduledEstimateUrl ?? null,
        cancelScheduledSmsUrl: config.cancelScheduledSmsUrl ?? null,
        sendPaymentUrl: config.sendPaymentUrl ?? null,
        sendDepositUrl: config.sendDepositUrl ?? null,
        sendInspectionUrl: config.sendInspectionUrl ?? null,
        messageActions: config.messageActions ?? [],
        contextEstimateSend: config.contextEstimateSend ?? null,
        contextPaymentSend: config.contextPaymentSend ?? null,
        contextDepositSend: config.contextDepositSend ?? null,
        contextInspectionSend: config.contextInspectionSend ?? null,
        estimateSchedule: config.estimateSchedule ?? { shop_is_open: true, pending: null, upcoming: [], next_open_morning_label: 'Next open morning · 8:00 AM' },
        smsSchedule: config.smsSchedule ?? { shop_is_open: true, pending: null, upcoming: [], next_open_morning_label: 'Next open morning · 8:00 AM' },
        customerEmail: config.customerEmail ?? '',
        estimateDelivery: config.estimateDelivery ?? 'sms',
        paymentDelivery: config.paymentDelivery ?? 'sms',
        depositDelivery: config.depositDelivery ?? 'sms',
        depositAmount: config.depositAmount ?? '',
        canSmsEstimate: config.canSmsEstimate ?? true,
        canEmailEstimate: config.canEmailEstimate ?? false,
        canSmsPayment: config.canSmsPayment ?? true,
        canEmailPayment: config.canEmailPayment ?? false,
        canSmsDeposit: config.canSmsDeposit ?? true,
        canEmailDeposit: config.canEmailDeposit ?? false,
        canSmsInspection: config.canSmsInspection ?? true,
        showSmsComposer: config.showSmsComposer ?? true,
        customerId: config.customerId ?? null,
        messagesListIds: config.messagesListIds ?? [],
        hasConversationHistory: config.hasConversationHistory ?? true,
        autoOpenComposer: config.autoOpenComposer ?? false,
        alwaysOpen: config.alwaysOpen ?? false,
        keepOpenAfterSend: config.keepOpenAfterSend ?? false,
        nudgeKey: config.nudgeKey ?? '',
        entityKey: config.entityKey ?? '',
        customerPhoneDisplay: config.customerPhoneDisplay ?? '',
        channel: config.channel ?? 'sms',
        messengerDisplay: config.messengerDisplay ?? 'Messenger',
        messengerMessageTags: config.messengerMessageTags ?? [],
        messengerMessageTag: config.messengerMessageTag ?? '',
        defaultMessengerMessageTag: config.defaultMessengerMessageTag ?? '',
        open: (config.alwaysOpen ?? false) || (config.autoOpenComposer ?? false),
        body: config.initialBody ?? '',
        attachment: null,
        attachmentLabel: '',
        sending: false,
        error: '',
        estimateMenuOpen: false,
        paymentMenuOpen: false,
        depositMenuOpen: false,
        moreMenuOpen: false,
        smsSendMenuOpen: false,
        estimateMenuStyle: '',
        paymentMenuStyle: '',
        depositMenuStyle: '',
        moreMenuStyle: '',
        smsSendMenuStyle: '',
        sendEstimateAddVinUrl: config.sendEstimateAddVinUrl ?? null,
        vinWarningOpen: false,
        vinAcknowledged: false,
        fluidsWarningOpen: false,
        fluidsAcknowledged: false,
        pendingEstimateDelivery: null,
        afterHoursPromptOpen: false,
        afterHoursKind: null,
        pendingEstimateTiming: null,
        pendingEstimateScheduledFor: null,

        init() {
            this.bindRealtime();
            this.bindDeliveryMenuDismiss();
            this.bindComposerFocusRequests();
            this.bindComposerPrefillRequests();

            this.$watch('selectedRepairOrderId', () => {
                this.vinAcknowledged = false;
                this.vinWarningOpen = false;
                this.fluidsAcknowledged = false;
                this.fluidsWarningOpen = false;
                this.pendingEstimateDelivery = null;
                this.pendingEstimateTiming = null;
                this.pendingEstimateScheduledFor = null;
                this.afterHoursPromptOpen = false;
                this.afterHoursKind = null;
                this.syncEstimateScheduleFromSelection();
                this.syncDepositAmountFromSelection();
            });

            if (this.autoOpenComposer || this.alwaysOpen) {
                this.open = true;
                this.focusReplyInput();
            }
        },

        bindComposerPrefillRequests() {
            document.addEventListener('ark:prefill-comms-composer', (event) => {
                const detail = event.detail ?? {};

                if (typeof detail.body === 'string' && detail.body !== '') {
                    this.body = detail.body;
                }

                if (typeof detail.nudgeKey === 'string' && detail.nudgeKey !== '') {
                    this.nudgeKey = detail.nudgeKey;
                }

                if (typeof detail.entityKey === 'string' && detail.entityKey !== '') {
                    this.entityKey = detail.entityKey;
                }

                this.open = true;
                this.$nextTick(() => this.focusReplyInput());
            });
        },

        bindComposerFocusRequests() {
            document.addEventListener('ark:focus-comms-composer', () => {
                this.open = true;
                this.$nextTick(() => this.focusReplyInput());
            });

            if (sessionStorage.getItem('ark:focus-comms-composer') === '1') {
                sessionStorage.removeItem('ark:focus-comms-composer');
                this.open = true;
                this.$nextTick(() => this.focusReplyInput());
            }
        },

        isComposerInputVisible(element) {
            if (! element) {
                return false;
            }

            const style = window.getComputedStyle(element);

            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }

            const rect = element.getBoundingClientRect();

            return rect.width > 0 && rect.height > 0;
        },

        focusReplyInput(attempt = 0) {
            const maxAttempts = 40;
            const input = this.$refs.replyBody;

            if (input && this.isComposerInputVisible(input)) {
                input.focus({ preventScroll: true });

                return;
            }

            if (attempt < maxAttempts) {
                window.setTimeout(() => this.focusReplyInput(attempt + 1), attempt < 10 ? 16 : 50);
            }
        },

        bindDeliveryMenuDismiss() {
            const releaseListeners = () => {
                document.removeEventListener('click', onDocumentClick);
                window.removeEventListener('scroll', onScroll, true);
            };

            const onDocumentClick = (event) => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (! this.estimateMenuOpen && ! this.paymentMenuOpen && ! this.depositMenuOpen && ! this.moreMenuOpen && ! this.smsSendMenuOpen) {
                    return;
                }

                const roots = [
                    this.$refs.estimateMenu,
                    this.$refs.estimateMenuPanel,
                    this.$refs.paymentMenu,
                    this.$refs.paymentMenuPanel,
                    this.$refs.depositMenu,
                    this.$refs.depositMenuPanel,
                    this.$refs.moreMenu,
                    this.$refs.moreMenuPanel,
                    this.$refs.smsSendMenu,
                    this.$refs.smsSendMenuPanel,
                ].filter(Boolean);

                for (const root of roots) {
                    if (root.contains(event.target)) {
                        return;
                    }
                }

                this.closeDeliveryMenus();
            };

            const onScroll = () => {
                if (! this.$el.isConnected) {
                    releaseListeners();

                    return;
                }

                if (this.estimateMenuOpen || this.paymentMenuOpen || this.depositMenuOpen || this.moreMenuOpen || this.smsSendMenuOpen) {
                    this.closeDeliveryMenus();
                }
            };

            document.addEventListener('click', onDocumentClick);
            window.addEventListener('scroll', onScroll, true);
        },

        syncDeliveryMenuPosition(triggerRef, styleKey) {
            this.$nextTick(() => {
                const trigger = this.$refs[triggerRef];

                if (! trigger) {
                    return;
                }

                const rect = trigger.getBoundingClientRect();
                const left = Math.round(rect.left);
                const minWidth = Math.max(Math.round(rect.width), 112);

                // Composers sit near the bottom of the viewport; open upward
                // when there is not enough room below the trigger.
                if (window.innerHeight - rect.bottom < 200) {
                    const bottom = Math.round(window.innerHeight - rect.top + 4);
                    this[styleKey] = `top:auto;bottom:${bottom}px;left:${left}px;min-width:${minWidth}px;`;

                    return;
                }

                this[styleKey] = `top:${Math.round(rect.bottom + 4)}px;left:${left}px;min-width:${minWidth}px;`;
            });
        },

        sendButtonLabel() {
            if (this.channel === 'messenger') {
                return this.attachment ? 'Send Attachment' : 'Send Messenger';
            }

            return this.attachment ? 'Send MMS' : 'Send Text';
        },

        composerActionLabel() {
            if (this.open) {
                return 'Cancel';
            }

            if (this.channel === 'messenger') {
                return this.hasConversationHistory ? 'Reply on Messenger' : 'Message on Messenger';
            }

            return this.hasConversationHistory ? 'Reply' : 'Text Customer';
        },

        composerPlaceholder() {
            if (this.channel === 'messenger') {
                return this.hasConversationHistory
                    ? `Reply on Messenger to ${this.messengerDisplay}`
                    : `First Messenger message to ${this.messengerDisplay}`;
            }

            const phone = this.customerPhoneDisplay;

            if (this.hasConversationHistory) {
                return phone !== ''
                    ? `Message or MMS to ${phone}`
                    : 'Message or MMS to this customer';
            }

            return phone !== ''
                ? `First text or MMS to ${phone}`
                : 'First text or MMS to this customer';
        },

        composerHelper() {
            if (this.channel === 'messenger') {
                return this.attachment
                    ? 'Photo, video, or PDF sends on Messenger. Appears in the timeline immediately.'
                    : 'Sends via Facebook Messenger. Appears in the timeline immediately.';
            }

            if (this.attachment) {
                return 'Photo, video, or PDF sends as MMS. Appears in the timeline immediately.';
            }

            return this.hasConversationHistory
                ? 'Text sends as SMS. Attach a file for MMS. Appears in the timeline immediately.'
                : 'First message to this customer. Attach a file to send MMS.';
        },

        bindRealtime() {
            if (! arkEchoEnabled() || this.customerId === null) {
                return;
            }

            const echo = getArkEcho();

            if (! echo) {
                return;
            }

            echo.private('operations.conversations')
                .listen('.conversation.message.received', (payload) => {
                    if (Number(payload?.customer_id) !== Number(this.customerId)) {
                        return;
                    }

                    if (payload?.html) {
                        this.prependMessage(payload.html, payload?.message?.id, payload?.hub_filter ?? 'text');
                    }
                });
        },

        toggleReply() {
            if (this.alwaysOpen) {
                return;
            }

            this.open = ! this.open;
            this.error = '';

            if (this.open) {
                this.$nextTick(() => this.$refs.replyBody?.focus());
            }
        },

        pickAttachment(event) {
            const file = event.target.files?.[0] ?? null;

            this.attachment = file;
            this.attachmentLabel = file?.name ?? '';
            this.error = '';
        },

        clearAttachment() {
            this.attachment = null;
            this.attachmentLabel = '';

            if (this.$refs.attachmentInput) {
                this.$refs.attachmentInput.value = '';
            }
        },

        prependMessage(html, messageId = null, hubFilter = 'text') {
            let refreshedCanonical = false;

            for (const listId of this.messagesListIds) {
                const list = document.getElementById(listId);

                if (! list) {
                    continue;
                }

                // RO Comms uses event-bubble + canonical timeline — never inject hub-row HTML.
                if (list.dataset.timelineRefresh === 'comms-tab') {
                    if (! refreshedCanonical && typeof window.arkReloadRepairOrderWorkspaceTab === 'function') {
                        window.arkReloadRepairOrderWorkspaceTab('comms');
                        refreshedCanonical = true;
                    }

                    continue;
                }

                if (messageId && list.querySelector(`[data-conversation-message-id="${messageId}"]`)) {
                    continue;
                }

                const emptyState = list.querySelector('[data-conversation-empty]');

                if (emptyState) {
                    emptyState.remove();
                }

                const isCommsThread = list.classList.contains('ops-comms-workspace__thread-body');
                const isHubRelationship = listId === 'conversation-messages-relationship';
                const wrappedHtml = isHubRelationship
                    ? `<div data-conversation-row data-filter="${hubFilter}">${html}</div>`
                    : html;

                list.insertAdjacentHTML(isCommsThread ? 'beforeend' : 'afterbegin', wrappedHtml);

                if (isHubRelationship) {
                    document.dispatchEvent(new CustomEvent('ark:hub-comms-ingest', {
                        detail: {
                            message_id: messageId,
                            filter: hubFilter,
                        },
                    }));
                }

                if (isCommsThread) {
                    list.scrollTop = list.scrollHeight;
                }
            }

            this.hasConversationHistory = true;
        },

        resolveRepairOrderActionUrl(field) {
            const directUrl = this[field];

            if (directUrl) {
                return directUrl;
            }

            const match = this.openRepairOrders.find(
                (repairOrder) => Number(repairOrder.repair_order_id) === Number(this.selectedRepairOrderId),
            );

            if (! match) {
                return null;
            }

            return field === 'sendEstimateUrl'
                ? (match.send_estimate_url ?? null)
                : field === 'cancelScheduledEstimateUrl'
                    ? (match.cancel_scheduled_estimate_url ?? null)
                    : field === 'sendInspectionUrl'
                        ? (match.send_inspection_url ?? null)
                        : field === 'sendDepositUrl'
                            ? (match.send_deposit_url ?? null)
                            : (match.send_payment_url ?? null);
        },

        resolveSendProjection(kind) {
            if (kind === 'estimate' && this.contextEstimateSend) {
                return this.contextEstimateSend;
            }

            if (kind === 'payment' && this.contextPaymentSend) {
                return this.contextPaymentSend;
            }

            if (kind === 'deposit' && this.contextDepositSend) {
                return this.contextDepositSend;
            }

            if (kind === 'inspection' && this.contextInspectionSend) {
                return this.contextInspectionSend;
            }

            const match = this.openRepairOrders.find(
                (repairOrder) => Number(repairOrder.repair_order_id) === Number(this.selectedRepairOrderId),
            );

            if (! match) {
                return null;
            }

            return kind === 'estimate'
                ? (match.estimate ?? null)
                : kind === 'inspection'
                    ? (match.inspection ?? null)
                    : kind === 'deposit'
                        ? (match.deposit ?? null)
                        : (match.payment ?? null);
        },

        syncDepositAmountFromSelection() {
            const projection = this.resolveSendProjection('deposit');
            const suggested = projection?.suggested_amount_decimal ?? '';

            if (suggested !== '' && suggested != null) {
                this.depositAmount = suggested;
            }
        },
        estimateMissingVin() {
            return this.resolveSendProjection('estimate')?.missing_vin ?? false;
        },

        estimateVinBlockMessage() {
            return this.resolveSendProjection('estimate')?.vin_block_message
                ?? 'Add the vehicle VIN on this repair order before sending the estimate to the customer.';
        },

        estimateTimingFluidsMissing() {
            return this.resolveSendProjection('estimate')?.timing_fluids_missing ?? false;
        },

        estimateTimingFluidsMessage() {
            return this.resolveSendProjection('estimate')?.timing_fluids_message
                ?? 'This job is missing companions the shop usually includes';
        },

        estimateTimingFluidsDetail() {
            return this.resolveSendProjection('estimate')?.timing_fluids_detail
                ?? 'Add the usual companions on this job before the customer sees the estimate.';
        },

        addVinUrl() {
            if (this.sendEstimateAddVinUrl) {
                return this.sendEstimateAddVinUrl;
            }

            const match = this.openRepairOrders.find(
                (repairOrder) => Number(repairOrder.repair_order_id) === Number(this.selectedRepairOrderId),
            );

            return match?.add_vin_url ?? '#ro-identity-band';
        },

        cancelVinWarning() {
            this.vinWarningOpen = false;
            this.fluidsWarningOpen = false;
            this.pendingEstimateDelivery = null;
            this.pendingEstimateTiming = null;
            this.pendingEstimateScheduledFor = null;
        },

        continueWithoutTimingFluids() {
            this.fluidsAcknowledged = true;
            this.fluidsWarningOpen = false;

            if (this.pendingEstimateDelivery) {
                const delivery = this.pendingEstimateDelivery;
                const timing = this.pendingEstimateTiming ?? 'now';
                const scheduledFor = this.pendingEstimateScheduledFor;
                this.pendingEstimateDelivery = null;
                this.pendingEstimateTiming = null;
                this.pendingEstimateScheduledFor = null;
                this.executeEstimateSend(delivery, timing, scheduledFor);
            }
        },

        continueWithoutVin() {
            this.vinAcknowledged = true;
            this.vinWarningOpen = false;

            if (this.pendingEstimateDelivery) {
                const delivery = this.pendingEstimateDelivery;
                const timing = this.pendingEstimateTiming ?? 'now';
                const scheduledFor = this.pendingEstimateScheduledFor;
                this.pendingEstimateDelivery = null;
                this.pendingEstimateTiming = null;
                this.pendingEstimateScheduledFor = null;
                this.executeEstimateSend(delivery, timing, scheduledFor);
            }
        },

        channelBlockReason(kind, delivery) {
            const projection = this.resolveSendProjection(kind);

            if (projection?.send_block_reason) {
                return projection.send_block_reason;
            }

            return deliveryChannelBlockReason(delivery, {
                canSms: projection?.can_sms ?? false,
                canEmail: projection?.can_email ?? false,
                smsBlockReason: projection?.sms_block_reason ?? '',
                emailBlockReason: projection?.email_block_reason ?? '',
            });
        },

        prependDeliveries(data) {
            const deliveries = Array.isArray(data?.deliveries) && data.deliveries.length > 0
                ? data.deliveries
                : (data?.html ? [{ html: data.html, message_id: data.message_id }] : []);

            for (const delivery of deliveries) {
                if (delivery?.html) {
                    this.prependMessage(
                        delivery.html,
                        delivery?.message_id,
                        delivery?.filter ?? 'text',
                    );
                }
            }
        },

        async postDelivery(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));

            return { response, data };
        },

        toggleEstimateMenu() {
            if (this.sending) {
                return;
            }

            this.paymentMenuOpen = false;
            this.depositMenuOpen = false;
            this.moreMenuOpen = false;
            this.smsSendMenuOpen = false;
            const nextOpen = ! this.estimateMenuOpen;
            this.estimateMenuOpen = nextOpen;

            if (nextOpen) {
                this.syncDeliveryMenuPosition('estimateMenuTrigger', 'estimateMenuStyle');
            }
        },

        togglePaymentMenu() {
            if (this.sending) {
                return;
            }

            this.estimateMenuOpen = false;
            this.depositMenuOpen = false;
            this.moreMenuOpen = false;
            this.smsSendMenuOpen = false;
            const nextOpen = ! this.paymentMenuOpen;
            this.paymentMenuOpen = nextOpen;

            if (nextOpen) {
                this.syncDeliveryMenuPosition('paymentMenuTrigger', 'paymentMenuStyle');
            }
        },

        toggleDepositMenu() {
            if (this.sending) {
                return;
            }

            this.estimateMenuOpen = false;
            this.paymentMenuOpen = false;
            this.moreMenuOpen = false;
            this.smsSendMenuOpen = false;
            const nextOpen = ! this.depositMenuOpen;
            this.depositMenuOpen = nextOpen;

            if (nextOpen) {
                this.syncDeliveryMenuPosition('depositMenuTrigger', 'depositMenuStyle');
            }
        },

        closeDeliveryMenus() {
            this.estimateMenuOpen = false;
            this.paymentMenuOpen = false;
            this.depositMenuOpen = false;
            this.moreMenuOpen = false;
            this.smsSendMenuOpen = false;
        },

        toggleMoreMenu() {
            if (this.sending) {
                return;
            }

            this.estimateMenuOpen = false;
            this.paymentMenuOpen = false;
            this.depositMenuOpen = false;
            this.smsSendMenuOpen = false;
            const nextOpen = ! this.moreMenuOpen;
            this.moreMenuOpen = nextOpen;

            if (nextOpen) {
                this.syncDeliveryMenuPosition('moreMenuTrigger', 'moreMenuStyle');
            }
        },

        toggleSmsSendMenu() {
            if (this.sending) {
                return;
            }

            this.estimateMenuOpen = false;
            this.paymentMenuOpen = false;
            this.depositMenuOpen = false;
            this.moreMenuOpen = false;
            const nextOpen = ! this.smsSendMenuOpen;
            this.smsSendMenuOpen = nextOpen;

            if (nextOpen) {
                this.syncDeliveryMenuPosition('smsSendMenuTrigger', 'smsSendMenuStyle');
            }
        },

        async sendEstimate(delivery = null) {
            const chosenDelivery = delivery ?? this.estimateDelivery;

            if (this.sending) {
                return;
            }

            if (this.shouldPromptAfterHours('estimate')) {
                this.pendingEstimateDelivery = chosenDelivery;
                this.afterHoursKind = 'estimate';
                this.afterHoursPromptOpen = true;
                this.closeDeliveryMenus();

                return;
            }

            await this.executeEstimateSend(chosenDelivery, 'now');
        },

        scheduleEstimateTomorrowMorning() {
            const chosenDelivery = this.estimateDelivery;

            if (this.sending) {
                return;
            }

            this.closeDeliveryMenus();
            this.executeEstimateSend(chosenDelivery, 'tomorrow_morning');
        },

        scheduleEstimateForMorning(scheduledFor) {
            const chosenDelivery = this.estimateDelivery;

            if (this.sending) {
                return;
            }

            this.closeDeliveryMenus();
            this.executeEstimateSend(chosenDelivery, 'tomorrow_morning', scheduledFor);
        },

        confirmAfterHoursNextOpenMorning() {
            const kind = this.afterHoursKind ?? 'estimate';
            const nextIso = this.smsSchedule?.upcoming?.[0]?.scheduled_for
                ?? this.estimateSchedule?.upcoming?.[0]?.scheduled_for
                ?? null;

            this.afterHoursPromptOpen = false;
            this.afterHoursKind = null;

            if (kind === 'sms') {
                this.executeSmsSend('tomorrow_morning', nextIso);

                return;
            }

            const delivery = this.pendingEstimateDelivery ?? this.estimateDelivery;
            this.pendingEstimateDelivery = null;
            this.executeEstimateSend(delivery, 'tomorrow_morning', nextIso);
        },

        shouldPromptAfterHours(kind = 'estimate') {
            if (kind === 'sms') {
                return this.smsSchedule?.shop_is_open === false;
            }

            return this.estimateSchedule?.shop_is_open === false
                || (this.estimateSchedule?.shop_is_open == null && this.smsSchedule?.shop_is_open === false);
        },

        syncEstimateScheduleFromSelection() {
            if (this.contextEstimateSend && this.sendEstimateUrl) {
                return;
            }

            const match = this.openRepairOrders.find(
                (repairOrder) => Number(repairOrder.repair_order_id) === Number(this.selectedRepairOrderId),
            );

            if (match?.schedule) {
                this.estimateSchedule = match.schedule;
            }
        },

        dismissAfterHoursPrompt() {
            this.afterHoursPromptOpen = false;
            this.afterHoursKind = null;
            this.pendingEstimateDelivery = null;
            this.pendingEstimateTiming = null;
        },

        confirmAfterHoursTiming(timing) {
            const kind = this.afterHoursKind ?? 'estimate';
            this.afterHoursPromptOpen = false;
            this.afterHoursKind = null;

            if (kind === 'sms') {
                this.executeSmsSend(timing);

                return;
            }

            const delivery = this.pendingEstimateDelivery ?? this.estimateDelivery;
            this.pendingEstimateDelivery = null;
            this.executeEstimateSend(delivery, timing);
        },

        confirmEstimateTiming(timing) {
            this.afterHoursKind = 'estimate';
            this.confirmAfterHoursTiming(timing);
        },

        applyEstimateSchedule(data) {
            if (data?.schedule && typeof data.schedule === 'object') {
                this.estimateSchedule = data.schedule;
            }
        },

        applySmsSchedule(data) {
            if (data?.sms_schedule && typeof data.sms_schedule === 'object') {
                this.smsSchedule = data.sms_schedule;
            }
        },

        async executeEstimateSend(delivery = null, timing = 'now', scheduledFor = null) {
            const url = this.resolveRepairOrderActionUrl('sendEstimateUrl');
            const chosenDelivery = delivery ?? this.estimateDelivery;

            if (this.sending) {
                return;
            }

            if (url === null || url === '') {
                this.error = 'Select a repair order to send the estimate.';

                return;
            }

            const blockedReason = this.channelBlockReason('estimate', chosenDelivery);

            if (blockedReason !== null) {
                this.error = blockedReason;
                this.closeDeliveryMenus();

                return;
            }

            if (this.estimateMissingVin() && ! this.vinAcknowledged) {
                this.pendingEstimateDelivery = chosenDelivery;
                this.pendingEstimateTiming = timing;
                this.pendingEstimateScheduledFor = scheduledFor;
                this.vinWarningOpen = true;
                this.closeDeliveryMenus();

                return;
            }

            if (this.estimateTimingFluidsMissing() && ! this.fluidsAcknowledged) {
                this.pendingEstimateDelivery = chosenDelivery;
                this.pendingEstimateTiming = timing;
                this.pendingEstimateScheduledFor = scheduledFor;
                this.fluidsWarningOpen = true;
                this.closeDeliveryMenus();

                return;
            }

            this.closeDeliveryMenus();
            this.sending = true;
            this.error = '';
            this.pendingEstimateDelivery = null;
            this.pendingEstimateTiming = null;
            this.pendingEstimateScheduledFor = null;

            const isScheduled = timing === 'tomorrow_morning' || !! scheduledFor;
            const busyLabel = isScheduled
                ? 'Scheduling estimate…'
                : estimateDeliveryBusyLabel(chosenDelivery);

            try {
                await withWorksheetBusy(busyLabel, async () => {
                    const { response, data } = await this.postDelivery(url, deliveryPayload(chosenDelivery, this.customerEmail, {
                        acknowledge_missing_vin: this.vinAcknowledged,
                        acknowledge_timing_fluids: this.fluidsAcknowledged,
                        timing: isScheduled ? 'tomorrow_morning' : timing,
                        ...(scheduledFor ? { scheduled_for: scheduledFor } : {}),
                    }));

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            isScheduled
                                ? 'Estimate could not be scheduled.'
                                : 'Estimate could not be sent.',
                        );

                        return;
                    }

                    this.applyEstimateSchedule(data);

                    if (data?.scheduled) {
                        return;
                    }

                    this.prependDeliveries(data);
                    this.toastAwaitingApproval(data);
                }, this.$el);
            } catch {
                this.error = isScheduled
                    ? 'Estimate could not be scheduled. Check your connection and try again.'
                    : 'Estimate could not be sent. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        toastAwaitingApproval(data) {
            const toast = data?.awaiting_approval?.toast;

            if (typeof toast === 'string' && toast.trim() !== '') {
                arkOpsToast(toast, data?.awaiting_approval?.moved ? 2800 : 3600);
            }

            if (data?.awaiting_approval?.moved) {
                window.dispatchEvent(new CustomEvent('ark:repair-order-status-changed', {
                    detail: {
                        status: data.awaiting_approval.to_status,
                        from_status: data.awaiting_approval.from_status,
                        reason: data.awaiting_approval.reason,
                    },
                }));
            }
        },
        async cancelScheduledEstimate() {
            const url = this.resolveRepairOrderActionUrl('cancelScheduledEstimateUrl');

            if (this.sending || url === null || url === '') {
                return;
            }

            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy('Cancelling scheduled send…', async () => {
                    const { response, data } = await this.postDelivery(url, {});

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Scheduled estimate could not be cancelled.',
                        );

                        return;
                    }

                    this.applyEstimateSchedule(data);
                }, this.$el);
            } catch {
                this.error = 'Scheduled estimate could not be cancelled. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        async cancelScheduledSms() {
            const url = this.cancelScheduledSmsUrl;

            if (this.sending || ! url) {
                return;
            }

            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy('Cancelling scheduled reply…', async () => {
                    const { response, data } = await this.postDelivery(url, {});

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Scheduled reply could not be cancelled.',
                        );

                        return;
                    }

                    this.applySmsSchedule(data);
                }, this.$el);
            } catch {
                this.error = 'Scheduled reply could not be cancelled. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        scheduleSmsTomorrowMorning() {
            if (this.sending || this.channel !== 'sms') {
                return;
            }

            this.closeDeliveryMenus();
            this.executeSmsSend('tomorrow_morning');
        },

        chooseSmsSendMorning(scheduledFor) {
            this.closeDeliveryMenus();

            if (this.channel !== 'sms') {
                return;
            }

            if (! this.open && ! this.alwaysOpen) {
                this.open = true;
            }

            if (this.attachment) {
                this.error = 'Remove the attachment to schedule this reply.';
                this.focusReplyInput();

                return;
            }

            this.executeSmsSend('tomorrow_morning', scheduledFor);
        },

        chooseSmsSendTiming(choice) {
            this.closeDeliveryMenus();

            if (this.channel !== 'sms') {
                return;
            }

            if (! this.open && ! this.alwaysOpen) {
                this.open = true;
            }

            if (choice === 'now') {
                this.send();

                return;
            }

            if (this.attachment) {
                this.error = 'Remove the attachment to schedule this reply.';
                this.focusReplyInput();

                return;
            }

            this.scheduleSmsTomorrowMorning();
        },

        async sendPaymentLink(delivery = null) {
            const url = this.resolveRepairOrderActionUrl('sendPaymentUrl');
            const chosenDelivery = delivery ?? this.paymentDelivery;

            if (this.sending) {
                return;
            }

            if (url === null || url === '') {
                this.error = 'Select a repair order to send the payment link.';

                return;
            }

            const blockedReason = this.channelBlockReason('payment', chosenDelivery);

            if (blockedReason !== null) {
                this.error = blockedReason;
                this.closeDeliveryMenus();

                return;
            }

            this.closeDeliveryMenus();
            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy(paymentDeliveryBusyLabel(chosenDelivery), async () => {
                    const { response, data } = await this.postDelivery(url, deliveryPayload(chosenDelivery, this.customerEmail));

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Payment link could not be sent.',
                        );

                        return;
                    }

                    this.prependDeliveries(data);
                }, this.$el);
            } catch {
                this.error = 'Payment link could not be sent. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        async sendDepositRequest(delivery = null) {
            const url = this.resolveRepairOrderActionUrl('sendDepositUrl');
            const chosenDelivery = delivery ?? this.depositDelivery;
            const amount = String(this.depositAmount ?? '').trim();

            if (this.sending) {
                return;
            }

            if (url === null || url === '') {
                this.error = 'Select a repair order to send the deposit request.';

                return;
            }

            if (amount === '' || Number(amount) <= 0) {
                this.error = 'Enter a deposit amount greater than zero.';
                this.closeDeliveryMenus();

                return;
            }

            const blockedReason = this.channelBlockReason('deposit', chosenDelivery);

            if (blockedReason !== null) {
                this.error = blockedReason;
                this.closeDeliveryMenus();

                return;
            }

            this.closeDeliveryMenus();
            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy(depositDeliveryBusyLabel(chosenDelivery), async () => {
                    const { response, data } = await this.postDelivery(
                        url,
                        deliveryPayload(chosenDelivery, this.customerEmail, { amount }),
                    );

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Deposit request could not be sent.',
                        );

                        return;
                    }

                    this.prependDeliveries(data);
                }, this.$el);
            } catch {
                this.error = 'Deposit request could not be sent. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        inspectionBlockReason() {
            const projection = this.resolveSendProjection('inspection');

            return projection?.send_block_reason ?? projection?.sms_block_reason ?? null;
        },

        async sendInspectionLink() {
            const url = this.resolveRepairOrderActionUrl('sendInspectionUrl');

            if (this.sending) {
                return;
            }

            if (url === null || url === '') {
                this.error = 'Select a repair order to send the inspection link.';

                return;
            }

            const blockedReason = this.inspectionBlockReason();

            if (blockedReason !== null || ! this.canSmsInspection) {
                this.error = blockedReason ?? 'SMS is not available for this customer.';

                return;
            }

            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy('Sending inspection link…', async () => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                    });

                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            'Inspection link could not be sent.',
                        );

                        return;
                    }

                    this.prependDeliveries(data);
                }, this.$el);
            } catch {
                this.error = 'Inspection link could not be sent. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        async sendMessageAction(actionKey) {
            const action = (this.messageActions ?? []).find((entry) => entry.key === actionKey);
            const url = action?.url ?? null;

            if (this.sending) {
                return;
            }

            if (url === null || url === '') {
                this.error = 'That message action is not available.';

                return;
            }

            this.sending = true;
            this.error = '';

            try {
                await withWorksheetBusy(`Sending ${action?.label ?? 'message'}…`, async () => {
                    const payload = {};

                    if (this.repairOrderId) {
                        payload.repair_order_id = this.repairOrderId;
                    }

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        this.error = deliveryHttpErrorMessage(
                            response,
                            data,
                            `${action?.label ?? 'Message'} could not be sent.`,
                        );

                        return;
                    }

                    this.prependDeliveries(data);
                }, this.$el);
            } catch {
                this.error = `${action?.label ?? 'Message'} could not be sent. Check your connection and try again.`;
            } finally {
                this.sending = false;
            }
        },

        async sendAddress() {
            return this.sendMessageAction('address');
        },

        async send() {
            if (this.sending || this.sendUrl === '') {
                return;
            }

            if (this.channel === 'sms' && this.shouldPromptAfterHours('sms')) {
                this.afterHoursKind = 'sms';
                this.afterHoursPromptOpen = true;

                return;
            }

            await this.executeSmsSend('now');
        },

        async executeSmsSend(timing = 'now', scheduledFor = null) {
            if (this.sending || this.sendUrl === '') {
                return;
            }

            const trimmedBody = this.body.trim();

            if (trimmedBody === '' && ! this.attachment) {
                this.error = 'Enter a message or attach a file.';

                return;
            }

            if (timing === 'tomorrow_morning' || scheduledFor) {
                if (this.channel !== 'sms') {
                    this.error = 'Only SMS replies can be scheduled.';

                    return;
                }

                if (this.attachment) {
                    this.error = 'Remove the attachment to schedule this reply.';

                    return;
                }

                if (trimmedBody === '') {
                    this.error = 'Enter a message to schedule.';

                    return;
                }
            }

            this.sending = true;
            this.error = '';

            const formData = new FormData();

            if (trimmedBody !== '') {
                formData.append('body', trimmedBody);
            }

            if (timing !== 'now' || scheduledFor) {
                formData.append('timing', 'tomorrow_morning');
            }

            if (scheduledFor) {
                formData.append('scheduled_for', scheduledFor);
            }

            if (this.channel !== 'sms') {
                formData.append('channel', this.channel);
            }

            if (this.channel === 'messenger') {
                const selectedTag = this.messengerMessageTag || this.defaultMessengerMessageTag || '';

                if (selectedTag !== '') {
                    formData.append('messenger_message_tag', selectedTag);
                }
            }

            if (this.repairOrderId) {
                formData.append('repair_order_id', String(this.repairOrderId));
            }

            if (this.attachment && timing === 'now' && ! scheduledFor) {
                formData.append('attachment', this.attachment);
            }

            if (this.nudgeKey !== '') {
                formData.append('nudge_key', this.nudgeKey);
            }

            if (this.entityKey !== '') {
                formData.append('entity_key', this.entityKey);
            }

            const isScheduled = timing === 'tomorrow_morning' || !! scheduledFor;
            const busyLabel = isScheduled
                ? 'Scheduling reply…'
                : conversationSendBusyLabel(this.channel);

            try {
                await withWorksheetBusy(busyLabel, async () => {
                    const response = await fetch(this.sendUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        this.error = data?.message
                            ?? (isScheduled
                                ? 'Reply could not be scheduled.'
                                : 'Message could not be sent.');

                        return;
                    }

                    this.applySmsSchedule(data);

                    if (data?.scheduled) {
                        this.body = '';
                        this.clearAttachment();
                        this.nudgeKey = '';
                        this.entityKey = '';
                        window.ARK?.workspace?.setDirty?.(false);

                        if (! this.keepOpenAfterSend && ! this.alwaysOpen) {
                            this.open = false;
                        } else {
                            this.focusReplyInput();
                        }

                        return;
                    }

                    if (data?.html) {
                        this.prependMessage(data.html, data?.message_id, data?.filter ?? 'text');
                    }

                    this.body = '';
                    this.clearAttachment();
                    this.nudgeKey = '';
                    this.entityKey = '';
                    window.ARK?.workspace?.setDirty?.(false);

                    if (! this.keepOpenAfterSend && ! this.alwaysOpen) {
                        this.open = false;
                    } else {
                        this.focusReplyInput();
                    }
                }, this.$el);
            } catch {
                this.error = isScheduled
                    ? 'Reply could not be scheduled. Check your connection and try again.'
                    : 'Message could not be sent.';
            } finally {
                this.sending = false;
            }
        },
    };
}
