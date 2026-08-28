import { getArkEcho, arkEchoEnabled } from './ark-echo';

function interruptUrl() {
    return document.querySelector('meta[name="ark-comms-interrupt-url"]')?.content ?? '';
}

function markReadUrlTemplate() {
    return document.querySelector('meta[name="ark-comms-mark-read-url"]')?.content ?? '';
}

function markWorkedUrlTemplate() {
    return document.querySelector('meta[name="ark-call-queue-mark-worked-url"]')?.content ?? '';
}

function dismissCallUrl() {
    return document.querySelector('meta[name="ark-incoming-call-dismiss-url"]')?.content ?? '';
}

function dismissWebsiteLeadUrl() {
    return document.querySelector('meta[name="ark-website-lead-interrupt-dismiss-url"]')?.content ?? '';
}

function dismissPortalUrl() {
    return document.querySelector('meta[name="ark-portal-interrupt-dismiss-url"]')?.content ?? '';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function currentUserId() {
    return Number(document.querySelector('meta[name="ark-current-user-id"]')?.content ?? 0);
}

function browserNotificationsEnabled() {
    return document.querySelector('meta[name="ark-comms-browser-notifications"]')?.content === '1';
}

function readCallQueueBootstrap() {
    const node = document.getElementById('ark-call-queue-bootstrap');

    if (! node || node.textContent.trim() === '') {
        return {};
    }

    try {
        return JSON.parse(node.textContent);
    } catch {
        return {};
    }
}

function isCacheBackedInterrupt(message) {
    return message?.kind === 'portal' || message?.kind === 'website_lead';
}

function unreadMessageId(row) {
    if (row?.state !== 'unread') {
        return 0;
    }

    if (row?.kind === 'portal') {
        return row?.portal_interrupt_key ?? '';
    }

    if (row?.kind === 'website_lead') {
        return row?.lead_interrupt_key ?? (row?.lead_id ? `lead:${row.lead_id}` : '');
    }

    if (row?.kind !== 'sms' && row?.kind !== 'mms') {
        return 0;
    }

    return Number(row.conversation_message_id ?? row.message_id ?? 0);
}

function commsAttentionGateEnabled() {
    return document.querySelector('meta[name="ark-comms-attention-gate-enabled"]')?.content === '1';
}

function workstationCommsSuppressedByMeta() {
    return document.querySelector('meta[name="ark-workstation-privacy-active"]')?.content === '1';
}

function workstationPresenceGateActive() {
    if (workstationCommsSuppressedByMeta()) {
        return true;
    }

    const presence = document.querySelector('[data-ark-workstation-presence]');

    if (! presence) {
        return false;
    }

    if (presence.classList.contains('ws-presence--open')) {
        return true;
    }

    if (presence.dataset.locked === '1' || presence.dataset.needsPinSetup === '1') {
        return true;
    }

    return false;
}

function ownedPopupTimeoutSeconds() {
    const raw = Number(document.querySelector('meta[name="ark-owned-popup-timeout-seconds"]')?.content ?? 3);

    if (! Number.isFinite(raw)) {
        return 3;
    }

    return Math.max(3, Math.min(60, raw));
}

function isOwnedByOtherCall(call) {
    const ownerId = Number(call?.owned_by_user_id ?? 0);
    const viewerId = currentUserId();

    return ownerId > 0 && viewerId > 0 && ownerId !== viewerId;
}

function isLiveCall(call) {
    if (! call?.call_session_id) {
        return false;
    }

    if (call.is_actively_live === false) {
        return false;
    }

    if (call.is_actively_live === true) {
        return true;
    }

    // Legacy payloads without server authority: only treat ringing as live.
    return String(call.status ?? '') === 'ringing';
}

function isInboundCallInterrupt(call) {
    return String(call?.direction ?? 'inbound') === 'inbound';
}

function playCommsChime(repeats = 1) {
    const count = Math.max(1, Math.min(3, Number(repeats) || 1));

    for (let index = 0; index < count; index += 1) {
        window.setTimeout(() => {
            try {
                const context = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.value = index === 0 ? 880 : 988;
                gain.gain.value = 0.04;
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();

                window.setTimeout(() => {
                    oscillator.stop();
                    context.close();
                }, 180);
            } catch {
                // Audio is optional when the browser blocks autoplay.
            }
        }, index * 220);
    }
}

function maybeNotifyBrowser(title, body) {
    if (! browserNotificationsEnabled() || ! ('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    try {
        new Notification(title, {
            body,
            tag: 'ark-comms-interrupt',
            requireInteraction: commsAttentionGateEnabled(),
        });
    } catch {
        // Notification API unavailable in this context.
    }
}

function requestBrowserNotificationPermission() {
    if (! browserNotificationsEnabled() || ! ('Notification' in window)) {
        return;
    }

    if (Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }
}

export function arkCommsInterrupt() {
    return {
        activeCall: null,
        activeMessage: null,
        pollTimer: null,
        dismissedCallSessionIds: [],
        lastFocusedInterruptKey: '',
        ownedByOtherDismissTimer: null,
        ownedByOtherDismissSessionId: 0,

        attentionGateEnabled() {
            return commsAttentionGateEnabled();
        },

        init() {
            requestBrowserNotificationPermission();
            this.bindRealtime();
            this.bindQueueWatch();
            this.bindWorkstationPresenceGate();
            this.startPolling();

            this.$watch('activeCall', () => this.scheduleInterruptFocus());
            this.$watch('activeMessage', () => this.scheduleInterruptFocus());

            this.bootstrapPendingInterrupts();
            this.scheduleInterruptFocus();
        },

        bindWorkstationPresenceGate() {
            document.addEventListener('ark:workstation-presence-gate', (event) => {
                if (event.detail?.active) {
                    this.clearOwnedByOtherDismissTimer();
                    this.activeCall = null;
                    this.activeMessage = null;
                    this.lastFocusedInterruptKey = '';
                }
            });
        },

        shouldSuppressInterrupt() {
            return workstationPresenceGateActive();
        },

        rememberDismissedCall(callSessionId) {
            const id = Number(callSessionId);

            if (id > 0 && ! this.dismissedCallSessionIds.includes(id)) {
                this.dismissedCallSessionIds.push(id);
            }
        },

        wasCallDismissed(callSessionId) {
            return this.dismissedCallSessionIds.includes(Number(callSessionId));
        },

        bootstrapPendingInterrupts() {
            if (this.shouldSuppressInterrupt()) {
                return;
            }

            const bootstrap = readCallQueueBootstrap();
            const calls = Array.isArray(bootstrap.calls) ? bootstrap.calls : [];

            for (const call of calls) {
                if (isLiveCall(call) && isInboundCallInterrupt(call) && ! this.wasCallDismissed(call.call_session_id)) {
                    this.showCall(call, { announce: false });

                    return;
                }
            }

            const messages = Array.isArray(bootstrap.messages) ? bootstrap.messages : [];

            for (const row of messages) {
                if (unreadMessageId(row)) {
                    this.presentMessage({
                        message_id: unreadMessageId(row),
                        ...row,
                    }, { announce: false });

                    return;
                }
            }
        },

        bindQueueWatch() {
            document.addEventListener('ark:call-queue-changed', (event) => {
                const calls = Array.isArray(event.detail?.calls) ? event.detail.calls : [];
                const activeSessionId = Number(this.activeCall?.call_session_id ?? 0);
                const liveCall = calls.find((row) => isLiveCall(row) && isInboundCallInterrupt(row));
                const trackedCall = activeSessionId > 0
                    ? calls.find((row) => Number(row.call_session_id ?? 0) === activeSessionId)
                    : null;

                if (liveCall && ! this.wasCallDismissed(liveCall.call_session_id)) {
                    this.showCall(liveCall, { announce: false });

                    return;
                }

                if (trackedCall) {
                    this.updateCall(trackedCall);

                    return;
                }

                if (this.activeCall !== null && ! isLiveCall(this.activeCall)) {
                    this.activeCall = null;
                }

                const messages = event.detail?.messages;

                if (Array.isArray(messages)) {
                    this.processMessages(messages, { announce: false });
                }
            });
        },

        bindRealtime() {
            if (! arkEchoEnabled()) {
                return;
            }

            const echo = getArkEcho();

            if (! echo) {
                return;
            }

            echo.private('operations.comms-interrupts')
                .listen('.comms.interrupt', (payload) => {
                    this.handleInterruptEvent(payload);
                });

            echo.private('operations.incoming-calls')
                .listen('.call.incoming', (payload) => {
                    this.showCall(payload);
                })
                .listen('.call.updated', (payload) => {
                    this.updateCall(payload);
                });

            echo.private('operations.conversations')
                .listen('.conversation.message.received', (payload) => {
                    if (payload?.interrupt) {
                        this.showMessage(payload.interrupt, Number(payload?.message_id ?? 0));
                    }
                });
        },

        startPolling() {
            const url = interruptUrl();

            if (url === '') {
                return;
            }

            const poll = async () => {
                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        return;
                    }

                    const data = await response.json();
                    this.applySnapshot(data);
                } catch {
                    // Polling is the authoritative backup when websocket delivery fails.
                }
            };

            poll();
            const intervalMs = arkEchoEnabled() ? 15000 : 5000;
            this.pollTimer = window.setInterval(poll, intervalMs);
        },

        handleInterruptEvent(payload) {
            const action = String(payload?.action ?? 'show');
            const kind = String(payload?.kind ?? '');

            if (action === 'clear') {
                this.clearKind(kind);

                return;
            }

            const interrupt = payload?.interrupt;

            if (! interrupt) {
                return;
            }

            if (kind === 'call') {
                if (action === 'update') {
                    this.updateCall(interrupt);
                } else {
                    this.showCall(interrupt);
                }

                return;
            }

            if (kind === 'sms' || kind === 'mms' || kind === 'portal' || kind === 'website_lead') {
                this.showMessage(interrupt, kind === 'portal'
                    ? (interrupt.portal_interrupt_key ?? '')
                    : kind === 'website_lead'
                        ? (interrupt.lead_interrupt_key ?? '')
                        : Number(interrupt.conversation_message_id ?? 0));
            }
        },

        applySnapshot(data) {
            const call = data?.call;
            const activeSessionId = Number(this.activeCall?.call_session_id ?? 0);

            if (isLiveCall(call)) {
                if (! isInboundCallInterrupt(call)) {
                    if (Number(this.activeCall?.call_session_id ?? 0) === Number(call?.call_session_id ?? 0)) {
                        this.activeCall = null;
                    }
                } else if (isOwnedByOtherCall(call)) {
                    const snapshotSessionId = Number(call?.call_session_id ?? 0);

                    if (activeSessionId === snapshotSessionId) {
                        this.activeCall = { ...this.activeCall, ...call };
                        this.scheduleOwnedByOtherDismiss(call);
                    }
                } else {
                    this.clearOwnedByOtherDismissTimer();
                    this.showCall(call, { announce: false });
                }
            } else if (call !== null && call !== undefined) {
                const snapshotSessionId = Number(call?.call_session_id ?? 0);

                if (activeSessionId > 0 && snapshotSessionId === activeSessionId) {
                    this.updateCall(call);
                }
            } else if (this.activeCall !== null) {
                if (isOwnedByOtherCall(this.activeCall) || ! isLiveCall(this.activeCall)) {
                    this.clearOwnedByOtherDismissTimer();
                    this.activeCall = null;
                }
            }

            const messages = Array.isArray(data?.messages) ? data.messages : [];

            if (messages.length === 0) {
                if (this.activeCall === null && ! isCacheBackedInterrupt(this.activeMessage)) {
                    this.activeMessage = null;
                }
            } else {
                this.processMessages(messages, { announce: false });
            }
        },

        processMessages(messages, options = {}) {
            if (this.shouldSuppressInterrupt()) {
                return;
            }

            if (this.activeCall !== null) {
                return;
            }

            for (const row of messages) {
                const messageId = unreadMessageId(row);

                if (! messageId) {
                    continue;
                }

                const activeKey = this.activeMessage?.kind === 'portal'
                    ? String(this.activeMessage.portal_interrupt_key ?? this.activeMessage.message_id ?? '')
                    : this.activeMessage?.kind === 'website_lead'
                        ? String(this.activeMessage.lead_interrupt_key ?? this.activeMessage.message_id ?? '')
                        : String(Number(this.activeMessage?.message_id ?? 0));
                const rowKey = row?.kind === 'portal'
                    ? String(messageId)
                    : row?.kind === 'website_lead'
                        ? String(messageId)
                        : String(Number(messageId));

                if (activeKey === rowKey && activeKey !== '0' && activeKey !== '') {
                    return;
                }

                this.presentMessage({
                    message_id: messageId,
                    ...row,
                }, options);

                return;
            }

            if (isCacheBackedInterrupt(this.activeMessage)) {
                return;
            }

            this.activeMessage = null;
        },

        showCall(payload, options = {}) {
            if (this.shouldSuppressInterrupt()) {
                return;
            }

            const callSessionId = Number(payload?.call_session_id);

            if (! callSessionId || ! isLiveCall(payload) || ! isInboundCallInterrupt(payload) || this.wasCallDismissed(callSessionId)) {
                return;
            }

            if (isOwnedByOtherCall(payload)) {
                if (Number(this.activeCall?.call_session_id ?? 0) === callSessionId) {
                    this.activeCall = { ...this.activeCall, ...payload };
                    this.scheduleOwnedByOtherDismiss(payload);
                }

                return;
            }

            this.clearOwnedByOtherDismissTimer();

            const isNew = Number(this.activeCall?.call_session_id ?? 0) !== callSessionId;
            const merged = isNew
                ? payload
                : { ...this.activeCall, ...payload };
            this.activeCall = merged;

            if (isNew && options.announce !== false) {
                const directionLabel = String(payload.direction_label ?? 'Incoming');
                this.announceInterrupt(
                    `${directionLabel} call`,
                    payload.customer_name ?? payload.display_phone ?? 'Customer calling',
                );
            }

            if (isNew) {
                this.scheduleInterruptFocus({ force: true });
            }
        },

        updateCall(payload) {
            const callSessionId = Number(payload?.call_session_id);

            if (! callSessionId || this.wasCallDismissed(callSessionId) || ! isInboundCallInterrupt(payload)) {
                if (Number(this.activeCall?.call_session_id ?? 0) === callSessionId) {
                    this.clearOwnedByOtherDismissTimer();
                    this.activeCall = null;
                }

                return;
            }

            const merged = Number(this.activeCall?.call_session_id ?? 0) === callSessionId
                ? { ...this.activeCall, ...payload }
                : payload;

            if (! isLiveCall(merged)) {
                if (Number(this.activeCall?.call_session_id ?? 0) === callSessionId) {
                    this.clearOwnedByOtherDismissTimer();
                    this.activeCall = null;
                }

                return;
            }

            if (isOwnedByOtherCall(merged)) {
                if (Number(this.activeCall?.call_session_id ?? 0) === callSessionId) {
                    this.activeCall = merged;
                    this.scheduleOwnedByOtherDismiss(merged);
                } else {
                    void this.autoDismissOwnedByOtherCall(callSessionId);
                }

                return;
            }

            this.clearOwnedByOtherDismissTimer();
            this.activeCall = merged;
        },

        showMessage(interrupt, messageId = 0) {
            const isPortal = interrupt?.kind === 'portal';
            const isWebsiteLead = interrupt?.kind === 'website_lead';
            const resolvedId = isPortal
                ? String(messageId || interrupt?.portal_interrupt_key || '')
                : isWebsiteLead
                    ? String(messageId || interrupt?.lead_interrupt_key || '')
                    : Number(messageId || (interrupt?.conversation_message_id ?? 0));

            if (interrupt?.state !== 'unread') {
                return;
            }

            if (isPortal || isWebsiteLead ? resolvedId === '' : ! resolvedId) {
                return;
            }

            if (this.activeCall !== null) {
                return;
            }

            this.presentMessage({
                message_id: isPortal ? resolvedId : resolvedId,
                ...interrupt,
            });
        },

        presentMessage(message, options = {}) {
            if (this.shouldSuppressInterrupt()) {
                return;
            }

            const messageKey = message.kind === 'portal'
                ? String(message.portal_interrupt_key ?? message.message_id ?? '')
                : message.kind === 'website_lead'
                    ? String(message.lead_interrupt_key ?? message.message_id ?? '')
                    : String(Number(message.message_id ?? 0));
            const activeKey = this.activeMessage?.kind === 'portal'
                ? String(this.activeMessage.portal_interrupt_key ?? this.activeMessage.message_id ?? '')
                : this.activeMessage?.kind === 'website_lead'
                    ? String(this.activeMessage.lead_interrupt_key ?? this.activeMessage.message_id ?? '')
                    : String(Number(this.activeMessage?.message_id ?? 0));
            const isNew = activeKey !== messageKey;
            this.activeMessage = message;

            if (isNew && options.announce !== false) {
                const title = message.channel_label ?? this.channelLabel();
                const body = message.headline ?? message.display_phone ?? 'Customer portal activity';
                this.announceInterrupt(title, body, message.priority === 'high');
            }

            if (isNew) {
                this.scheduleInterruptFocus({ force: true });
            }
        },

        announceInterrupt(title, body, urgent = false) {
            playCommsChime(urgent ? 2 : 1);
            maybeNotifyBrowser(title, body);
        },

        interruptFocusKey() {
            if (this.activeCall !== null) {
                return `call:${Number(this.activeCall.call_session_id ?? 0)}`;
            }

            if (this.activeMessage !== null) {
                if (this.activeMessage.kind === 'portal') {
                    return `portal:${this.activeMessage.portal_interrupt_key ?? ''}`;
                }

                if (this.activeMessage.kind === 'website_lead') {
                    return `website_lead:${this.activeMessage.lead_interrupt_key ?? ''}`;
                }

                return `message:${Number(this.activeMessage.message_id ?? 0)}`;
            }

            return '';
        },

        scheduleInterruptFocus(options = {}) {
            const key = this.interruptFocusKey();

            if (key === '') {
                this.lastFocusedInterruptKey = '';

                return;
            }

            if (! this.attentionGateEnabled()) {
                return;
            }

            if (! options.force && key === this.lastFocusedInterruptKey) {
                return;
            }

            this.lastFocusedInterruptKey = key;
            this.focusActiveInterrupt(0);
        },

        isFocusableVisible(element) {
            if (! element || element.disabled) {
                return false;
            }

            const style = window.getComputedStyle(element);

            if (style.visibility === 'hidden' || style.display === 'none') {
                return false;
            }

            const rect = element.getBoundingClientRect();

            return rect.width > 0 && rect.height > 0;
        },

        resolveInterruptFocusTarget(root) {
            const primary = root.querySelector('[data-comms-interrupt-primary]');

            if (primary && this.isFocusableVisible(primary)) {
                return primary;
            }

            for (const element of root.querySelectorAll('button:not([disabled]), a[href]')) {
                if (this.isFocusableVisible(element)) {
                    return element;
                }
            }

            if (this.isFocusableVisible(root)) {
                return root;
            }

            return null;
        },

        focusActiveInterrupt(attempt = 0) {
            const maxAttempts = 40;
            const root = this.activeCall !== null
                ? this.$refs.callInterruptDialog
                : (this.activeMessage !== null ? this.$refs.messageInterruptDialog : null);

            if (! root) {
                if (attempt < maxAttempts) {
                    window.setTimeout(() => this.focusActiveInterrupt(attempt + 1), attempt < 10 ? 16 : 50);
                }

                return;
            }

            const target = this.resolveInterruptFocusTarget(root);

            if (target && this.isFocusableVisible(target)) {
                target.focus({ preventScroll: true });

                return;
            }

            if (attempt < maxAttempts) {
                window.setTimeout(() => this.focusActiveInterrupt(attempt + 1), attempt < 10 ? 16 : 50);
            }
        },

        steerInterruptFocus(event) {
            if (event.target.closest('button, a, input, textarea, select, video, audio, [contenteditable="true"]')) {
                return;
            }

            const primary = event.currentTarget.querySelector('[data-comms-interrupt-primary]');

            if (primary) {
                primary.focus({ preventScroll: true });
            }
        },

        activateInterruptPrimary(event) {
            const primary = event.currentTarget.querySelector('[data-comms-interrupt-primary]');

            if (primary && this.isFocusableVisible(primary)) {
                primary.click();
            }
        },

        clearKind(kind) {
            if (kind === 'call') {
                this.activeCall = null;
            }

            if (kind === 'sms' || kind === 'mms' || kind === 'website_lead' || kind === 'portal') {
                this.activeMessage = null;
            }
        },

        notifyQueueRefresh() {
            document.dispatchEvent(new CustomEvent('ark:call-queue-refresh', { bubbles: true }));
        },

        ownedByOther() {
            return isOwnedByOtherCall(this.activeCall);
        },

        clearOwnedByOtherDismissTimer() {
            if (this.ownedByOtherDismissTimer !== null) {
                window.clearTimeout(this.ownedByOtherDismissTimer);
                this.ownedByOtherDismissTimer = null;
            }

            this.ownedByOtherDismissSessionId = 0;
        },

        scheduleOwnedByOtherDismiss(call) {
            if (! isOwnedByOtherCall(call)) {
                this.clearOwnedByOtherDismissTimer();

                return;
            }

            const callSessionId = Number(call?.call_session_id ?? 0);

            if (! callSessionId) {
                return;
            }

            if (this.ownedByOtherDismissSessionId === callSessionId && this.ownedByOtherDismissTimer !== null) {
                return;
            }

            this.clearOwnedByOtherDismissTimer();
            this.ownedByOtherDismissSessionId = callSessionId;

            this.ownedByOtherDismissTimer = window.setTimeout(() => {
                this.ownedByOtherDismissTimer = null;
                this.ownedByOtherDismissSessionId = 0;

                if (Number(this.activeCall?.call_session_id ?? 0) !== callSessionId) {
                    return;
                }

                if (! isOwnedByOtherCall(this.activeCall)) {
                    return;
                }

                void this.autoDismissOwnedByOtherCall(callSessionId);
            }, ownedPopupTimeoutSeconds() * 1000);
        },

        async autoDismissOwnedByOtherCall(callSessionId) {
            this.rememberDismissedCall(callSessionId);
            this.activeCall = null;
            this.clearOwnedByOtherDismissTimer();

            await this.clearIncomingCallCache(callSessionId);
        },

        ownedByMe() {
            const ownerId = Number(this.activeCall?.owned_by_user_id ?? 0);
            const viewerId = currentUserId();

            return ownerId > 0 && viewerId > 0 && ownerId === viewerId;
        },

        ownerLabel() {
            if (! this.activeCall?.owned_by_name) {
                return '';
            }

            return this.ownedByMe()
                ? 'Owned by me'
                : `Owned by ${this.activeCall.owned_by_name}`;
        },

        callEyebrow() {
            if (this.activeCall?.is_actively_live === false) {
                return 'Call Ended';
            }

            const directionLabel = String(this.activeCall?.direction_label ?? 'Incoming');
            const status = String(this.activeCall?.status ?? '');

            if (status === 'answered') {
                return `Active ${directionLabel} Call`;
            }

            return `${directionLabel} Call`;
        },

        async dismissCall() {
            const callSessionId = Number(this.activeCall?.call_session_id ?? 0);
            this.clearOwnedByOtherDismissTimer();
            this.rememberDismissedCall(callSessionId);
            this.activeCall = null;

            if (! callSessionId) {
                return;
            }

            await this.clearIncomingCallCache(callSessionId);
            this.notifyQueueRefresh();
        },

        async markCallHandled() {
            const callSessionId = Number(this.activeCall?.call_session_id ?? 0);
            this.activeCall = null;

            if (! callSessionId) {
                return;
            }

            await this.markCallHandledForId(callSessionId);
        },

        async markCallHandledForId(callSessionId) {
            const template = markWorkedUrlTemplate();

            if (template !== '') {
                const url = template.replace('__CALL_SESSION__', String(callSessionId));

                try {
                    await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        credentials: 'same-origin',
                    });
                } catch {
                    // Queue refresh will reconcile state.
                }
            }

            await this.clearIncomingCallCache(callSessionId);
            this.notifyQueueRefresh();
        },

        async dismissWebsiteLead() {
            const leadId = Number(this.activeMessage?.lead_id ?? 0);
            this.activeMessage = null;

            if (! leadId) {
                return;
            }

            const url = dismissWebsiteLeadUrl();

            if (url === '') {
                return;
            }

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ lead_id: leadId }),
                });
            } catch {
                // Local dismiss still clears the modal.
            }
        },

        async dismissPortal() {
            const portalInterruptKey = String(this.activeMessage?.portal_interrupt_key ?? '');
            this.activeMessage = null;

            if (portalInterruptKey === '') {
                return;
            }

            const url = dismissPortalUrl();

            if (url === '') {
                return;
            }

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ portal_interrupt_key: portalInterruptKey }),
                });
            } catch {
                // Local dismiss still clears the modal.
            }
        },

        async openWebsiteLeadIntake(event) {
            event?.preventDefault?.();

            const intakeUrl = this.activeMessage?.intake_url ?? '';

            if (intakeUrl === '') {
                return;
            }

            await this.dismissWebsiteLead();
            window.location.href = intakeUrl;
        },

        async markMessageRead() {
            const conversationId = Number(this.activeMessage?.conversation_id ?? 0);

            if (! conversationId) {
                this.activeMessage = null;

                return;
            }

            await this.markConversationRead(conversationId);
            this.activeMessage = null;
            this.notifyQueueRefresh();
        },

        shouldFocusReplyInPlace(target, current) {
            if (target.origin !== current.origin || target.pathname !== current.pathname) {
                return false;
            }

            return document.querySelector('[data-ark-conversation-composer]') !== null;
        },

        focusReplyComposerInPlace(target) {
            const nextUrl = target.pathname + target.search + target.hash;

            if (window.location.pathname + window.location.search + window.location.hash !== nextUrl) {
                window.history.replaceState(null, '', nextUrl);
            }

            if (target.hash) {
                document.querySelector(target.hash)?.scrollIntoView({ block: 'nearest' });
            }

            document.dispatchEvent(new CustomEvent('ark:focus-comms-composer'));
        },

        replyToMessage(event) {
            event?.preventDefault?.();

            const replyUrl = this.activeMessage?.reply_url ?? '';

            if (replyUrl === '') {
                return;
            }

            const conversationId = Number(this.activeMessage?.conversation_id ?? 0);

            if (conversationId) {
                void this.markConversationRead(conversationId);
            }

            this.activeMessage = null;
            this.notifyQueueRefresh();

            const target = new URL(replyUrl, window.location.origin);
            const current = new URL(window.location.href);

            if (this.shouldFocusReplyInPlace(target, current)) {
                this.focusReplyComposerInPlace(target);

                return;
            }

            sessionStorage.setItem('ark:focus-comms-composer', '1');
            window.location.href = replyUrl;
        },

        async markConversationRead(conversationId) {
            const template = markReadUrlTemplate();

            if (! conversationId || template === '') {
                return;
            }

            const url = template.replace('__CONVERSATION__', String(conversationId));

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                });
            } catch {
                // Queue refresh will reconcile state.
            }
        },

        async clearIncomingCallCache(callSessionId) {
            const url = dismissCallUrl();

            if (url === '' || ! callSessionId) {
                return;
            }

            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ call_session_id: callSessionId }),
                });
            } catch {
                // Local dismiss still clears the modal.
            }
        },

        previewOpenRos() {
            return (this.activeCall?.open_repair_orders ?? []).slice(0, 2);
        },

        extraOpenRoCount() {
            const total = this.activeCall?.open_repair_orders?.length ?? 0;

            return total > 2 ? total - 2 : 0;
        },

        vehicleSummary() {
            const vehicles = this.activeCall?.vehicles ?? [];

            if (vehicles.length === 0) {
                return this.activeCall?.customer_type || 'Customer';
            }

            if (vehicles.length === 1) {
                return vehicles[0].display_name;
            }

            return `${vehicles[0].display_name} · ${vehicles.length} active vehicles`;
        },

        openRoSummary() {
            const count = this.activeCall?.open_repair_orders?.length ?? 0;

            if (count === 0) {
                return 'No open repair orders';
            }

            return `${count} open ${count === 1 ? 'RO' : 'ROs'} — pick on Customer Hub or below`;
        },

        lastConversationSnippet() {
            const messages = this.activeCall?.recent_conversation ?? [];

            if (messages.length === 0) {
                return null;
            }

            const latest = messages[0];

            return `${latest.participant}: "${latest.body}"`;
        },

        channelLabel() {
            if (! this.activeMessage) {
                return 'Text';
            }

            if (this.activeMessage.channel_label) {
                return this.activeMessage.channel_label;
            }

            if (this.activeMessage.kind === 'portal') {
                return 'Customer portal';
            }

            if (this.activeMessage.kind === 'website_lead') {
                return 'Website Lead';
            }

            if (this.activeMessage.kind === 'mms' || this.activeMessage.has_attachment) {
                return 'MMS';
            }

            return 'SMS';
        },

        messageAttachments() {
            return Array.isArray(this.activeMessage?.attachments)
                ? this.activeMessage.attachments.filter((attachment) => Boolean(attachment?.url))
                : [];
        },

        snippetPreview() {
            const snippet = this.activeMessage?.snippet ?? this.activeMessage?.message?.body ?? '';

            if (snippet === '') {
                if (this.messageAttachments().some((attachment) => attachment.is_image || attachment.is_video || attachment.is_audio)) {
                    return '';
                }

                return this.activeMessage?.has_attachment ? 'Attachment' : 'New message';
            }

            return snippet;
        },
    };
}
