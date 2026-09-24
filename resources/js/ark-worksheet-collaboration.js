import { arkEchoEnabled, getArkEcho } from './ark-echo';
import { blockedPanelIds, dirtyFormsIn, draftMarkersIn, inputIsInsideDraft, versionToSubmit } from './ark-worksheet-draft';
import {
    flushDeferredWorksheetRecovery,
    heartbeatShouldReconcileVersion,
    noteWorksheetSocketEvent as applyWorksheetSocketEvent,
    queueWorksheetRecovery,
    SOCKET_LOSS_EVENTS,
    visibilityNeedsRecovery,
    WORKSHEET_HEARTBEAT_WITH_SOCKET_MS,
    WORKSHEET_RECOVERY_COALESCE_MS,
} from './ark-worksheet-recovery';

const sessionStorageKey = (repairOrderId) => `ark:ro:${repairOrderId}:session`;

export const resolveWorksheetSessionToken = (repairOrderId) => {
    const key = sessionStorageKey(repairOrderId);
    let token = sessionStorage.getItem(key);

    if (! token) {
        token = crypto.randomUUID();
        sessionStorage.setItem(key, token);
    }

    return token;
};

export const syncEstimateVersionInputs = (fieldName, version, root = document) => {
    if (! fieldName || version === undefined || version === null) {
        return;
    }

    const scope = root ?? document;

    scope.querySelectorAll(`input[name="${CSS.escape(fieldName)}"]`).forEach((input) => {
        if (inputIsInsideDraft(input)) {
            return;
        }

        input.value = String(version);
    });
};

export const arkWorksheetCollaboration = (config = {}) => ({
    worksheetSurface: config.surface ?? 'repair_order',
    worksheetSessionToken: resolveWorksheetSessionToken(config.repairOrderId),
    openedEstimateVersion: config.estimateVersion ?? 1,
    presenceMessage: '',
    versionDriftNotice: '',
    worksheetLeaseValid: true,
    heartbeatTimer: null,
    estimateVersionField: config.estimateVersionField ?? 'opened_estimate_version',
    worksheetHeartbeatUrl: config.heartbeatUrl ?? '',
    worksheetReleaseUrl: config.releaseUrl ?? '',
    broadcastChannel: config.broadcastChannel ?? '',
    broadcastEnabled: config.broadcastEnabled ?? false,
    realtimeChannel: null,
    renderedEstimateVersion: config.estimateVersion ?? 1,
    pendingRemoteVersion: null,
    remoteRefreshTimer: null,
    financialRefreshTimer: null,
    staleNoticeStorageKey: `ark:repair-order:${config.repairOrderId}:stale-notice`,
    staleNotice: '',
    remoteDrift: false,
    forceRefreshDrafts: false,
    discardingDraft: null,
    conflictFragment: config.conflictFragment ?? 'estimate-lines',
    currentUserId: config.currentUserId ?? null,
    localEstimateWrite: false,
    socketPhase: 'initial',
    socketRecoveryBound: false,
    worksheetDocumentHidden: false,
    worksheetRecoveryTimer: null,
    worksheetRecoveryInFlight: false,
    worksheetRecoveryDeferred: false,
    worksheetRecoveryFollowUp: false,
    worksheetRecoveryBusyWrapped: false,

    isSelfAuthoredEstimateChange(payload) {
        const actorId = Number.parseInt(String(payload?.actor_id ?? payload?.conflict?.actor_id ?? ''), 10);
        const userId = Number.parseInt(String(this.currentUserId ?? ''), 10);

        return ! Number.isNaN(actorId)
            && ! Number.isNaN(userId)
            && actorId === userId;
    },

    syncSelfAuthoredEstimateVersion(payload) {
        const version = Number.parseInt(String(payload?.estimate_version ?? ''), 10);

        if (Number.isNaN(version)) {
            return false;
        }

        if (version > this.renderedEstimateVersion) {
            this.markEstimateRendered(version);
        }

        this.clearStaleNotice();

        return true;
    },

    initWorksheetCollaboration() {
        sessionStorage.removeItem(this.staleNoticeStorageKey);
        this.staleNotice = '';
        this.versionDriftNotice = '';
        this.markEstimateRendered(this.openedEstimateVersion);

        window.requestAnimationFrame(() => {
            this.sendWorksheetHeartbeat();
        });

        const liveSocket = this.broadcastEnabled && this.broadcastChannel && arkEchoEnabled();

        this.heartbeatTimer = window.setInterval(
            () => this.sendWorksheetHeartbeat(),
            liveSocket ? WORKSHEET_HEARTBEAT_WITH_SOCKET_MS : 10_000,
        );

        this.wrapWorksheetBusyForRecovery();
        this.worksheetDocumentHidden = document.hidden;

        document.addEventListener('visibilitychange', () => {
            const wasHidden = this.worksheetDocumentHidden;
            this.worksheetDocumentHidden = document.hidden;

            if (! visibilityNeedsRecovery(wasHidden, document.hidden)) {
                return;
            }

            this.requestWorksheetRecovery();
            this.sendWorksheetHeartbeat();
        });

        window.addEventListener('online', () => {
            this.requestWorksheetRecovery();
        });

        window.addEventListener('pagehide', () => {
            this.releaseWorksheetSession();
            this.leaveWorksheetRealtime();
        });

        this.initWorksheetRealtime();
    },

    initWorksheetRealtime() {
        if (! this.broadcastEnabled || ! this.broadcastChannel || ! arkEchoEnabled()) {
            return;
        }

        const echo = getArkEcho();

        if (! echo) {
            return;
        }

        this.realtimeChannel = echo.private(this.broadcastChannel);
        this.realtimeChannel.listen('.estimate.changed', (payload) => {
            this.handleRemoteEstimateChange(payload);
        });
        this.realtimeChannel.listen('.financial.changed', (payload) => {
            this.handleRemoteFinancialChange(payload);
        });
        this.bindWorksheetSocketRecovery(echo);
    },

    wrapWorksheetBusyForRecovery() {
        if (this.worksheetRecoveryBusyWrapped || typeof this.endWorksheetBusy !== 'function') {
            return;
        }

        const endBusy = this.endWorksheetBusy.bind(this);
        this.worksheetRecoveryBusyWrapped = true;
        this.endWorksheetBusy = (...args) => {
            const result = endBusy(...args);
            flushDeferredWorksheetRecovery(this);

            return result;
        };
    },

    scheduleRecoveryTimer(callback) {
        return window.setTimeout(callback, WORKSHEET_RECOVERY_COALESCE_MS);
    },

    bindWorksheetSocketRecovery(echo) {
        const connection = echo?.connector?.pusher?.connection;

        if (! connection || this.socketRecoveryBound) {
            return;
        }

        this.socketRecoveryBound = true;

        if (connection.state === 'connected') {
            this.socketPhase = 'live';
        }

        connection.bind('connected', () => {
            this.noteWorksheetSocketEvent('connected');
        });

        SOCKET_LOSS_EVENTS.forEach((event) => {
            connection.bind(event, () => {
                this.noteWorksheetSocketEvent(event);
            });
        });
    },

    noteWorksheetSocketEvent(event) {
        applyWorksheetSocketEvent(this, event);
    },

    requestWorksheetRecovery() {
        queueWorksheetRecovery(this);
    },

    async handleRemoteFinancialChange(payload) {
        if (typeof this.refreshScope !== 'function') {
            return;
        }

        if (this.worksheetRecoveryTimer || this.worksheetRecoveryInFlight) {
            this.worksheetRecoveryFollowUp = true;

            return;
        }

        if (this.financialRefreshTimer) {
            window.clearTimeout(this.financialRefreshTimer);
        }

        this.financialRefreshTimer = window.setTimeout(async () => {
            this.financialRefreshTimer = null;

            if (this.worksheetBusyPending || this.worksheetSaving) {
                return;
            }

            await this.refreshScope('rail');
            await this.refreshLoadedFinancialTab();
            window.ARK?.workspace?.refreshActivity?.();
        }, 150);
    },

    leaveWorksheetRealtime() {
        if (this.remoteRefreshTimer) {
            window.clearTimeout(this.remoteRefreshTimer);
            this.remoteRefreshTimer = null;
        }

        if (this.financialRefreshTimer) {
            window.clearTimeout(this.financialRefreshTimer);
            this.financialRefreshTimer = null;
        }

        if (this.worksheetRecoveryTimer) {
            window.clearTimeout(this.worksheetRecoveryTimer);
            this.worksheetRecoveryTimer = null;
        }

        if (! this.broadcastChannel) {
            return;
        }

        getArkEcho()?.leave(this.broadcastChannel);
        this.realtimeChannel = null;
    },

    worksheetHasDraft() {
        return blockedPanelIds(document, this.continuityPanelIds ?? []).size > 0
            || blockedPanelIds(document, [this.worksheetScopeId ?? 'estimate-lines']).size > 0;
    },

    isWorksheetEditActive() {
        return Boolean(this.worksheetBusyPending || this.worksheetSaving || this.worksheetHasDraft());
    },

    markEstimateRendered(version) {
        const parsed = Number.parseInt(String(version ?? ''), 10);

        if (Number.isNaN(parsed)) {
            return;
        }

        this.renderedEstimateVersion = parsed;
        this.openedEstimateVersion = parsed;
        syncEstimateVersionInputs(this.estimateVersionField, parsed);
    },

    async handleRemoteEstimateChange(payload) {
        const version = Number.parseInt(String(payload?.estimate_version ?? ''), 10);

        if (Number.isNaN(version) || version <= this.renderedEstimateVersion) {
            return;
        }

        // This tab is writing the change. The save response paints the page.
        // Another tab or computer for the same person still needs the refresh.
        if (
            this.isSelfAuthoredEstimateChange(payload)
            && (this.worksheetBusyPending || this.worksheetSaving || this.localEstimateWrite)
        ) {
            this.syncSelfAuthoredEstimateVersion(payload);

            return;
        }

        const message = payload?.message
            || 'This estimate changed while you were working. Refresh the worksheet before saving.';

        window.ARK?.workspace?.refreshActivity?.();

        if (this.worksheetRecoveryTimer || this.worksheetRecoveryInFlight) {
            this.worksheetRecoveryFollowUp = true;

            if (this.worksheetHasDraft()) {
                this.remoteDrift = true;
                this.versionDriftNotice = message;
            }

            return;
        }

        if (this.worksheetBusyPending || this.worksheetSaving) {
            this.remoteDrift = true;
            this.versionDriftNotice = message;

            return;
        }

        if (this.worksheetHasDraft()) {
            this.remoteDrift = true;
            this.versionDriftNotice = message;
        }

        if (typeof this.refreshWorksheet !== 'function') {
            this.versionDriftNotice = message;
            this.remoteDrift = true;

            return;
        }

        if (this.remoteRefreshTimer) {
            window.clearTimeout(this.remoteRefreshTimer);
        }

        this.pendingRemoteVersion = Math.max(this.pendingRemoteVersion ?? 0, version);

        this.remoteRefreshTimer = window.setTimeout(async () => {
            this.remoteRefreshTimer = null;

            const latestVersion = this.pendingRemoteVersion;
            this.pendingRemoteVersion = null;

            if (latestVersion === null || latestVersion <= this.renderedEstimateVersion) {
                return;
            }

            if (this.worksheetBusyPending || this.worksheetSaving) {
                this.remoteDrift = true;
                this.versionDriftNotice = message;

                return;
            }

            if (this.worksheetHasDraft()) {
                this.remoteDrift = true;
                this.versionDriftNotice = message;
            }

            await this.refreshWorksheet(
                window.location.href.split('#')[0],
                document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
            );
            await this.refreshLoadedFinancialTab();

            if (this.worksheetHasDraft()) {
                this.remoteDrift = true;
                this.versionDriftNotice = message;
            }
        }, 150);
    },

    async reconcileAfterDraftDiscard(discarded = null) {
        this.discardingDraft = discarded;
        this.forceRefreshDrafts = discarded == null;

        try {
            if (typeof this.refreshWorksheet === 'function') {
                await this.refreshWorksheet(
                    window.location.href.split('#')[0],
                    document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
                );
            }

            await this.refreshLoadedFinancialTab();
        } finally {
            this.discardingDraft = null;
            this.forceRefreshDrafts = false;
        }

        if (! this.worksheetHasDraft()) {
            this.remoteDrift = false;
            this.clearStaleNotice();
        }
    },

    reconcileIfDraftCleared() {
        if (! this.remoteDrift || typeof this.refreshWorksheet !== 'function') {
            return;
        }

        this.refreshWorksheet(
            window.location.href.split('#')[0],
            document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
        );
    },

    async refreshLoadedFinancialTab() {
        const root = document.getElementById('repair-order-workspace-tabs');
        const panel = root?.querySelector('[data-workspace-tab-panel="financial"]');

        if (! this.forceRefreshDrafts && panel && (dirtyFormsIn(panel).length > 0 || draftMarkersIn(panel).length > 0)) {
            this.remoteDrift = true;

            return;
        }

        const tabs = window.Alpine?.$data?.(root);

        if (! tabs?.loadedTabs?.financial || typeof tabs.reloadTab !== 'function') {
            return;
        }

        await tabs.reloadTab('financial');
    },

    clearStaleNotice() {
        this.staleNotice = '';
        this.versionDriftNotice = '';
        sessionStorage.removeItem(this.staleNoticeStorageKey);
    },

    resolveEstimateVersionFromDocument(doc) {
        if (! doc || ! this.estimateVersionField) {
            return null;
        }

        const versions = [];

        const collectVersions = (root) => {
            root?.querySelectorAll?.(`input[name="${CSS.escape(this.estimateVersionField)}"]`)
                ?.forEach((input) => {
                    const parsed = Number.parseInt(String(input.value ?? ''), 10);

                    if (! Number.isNaN(parsed)) {
                        versions.push(parsed);
                    }
                });
        };

        collectVersions(doc.getElementById?.(this.worksheetScopeId ?? 'estimate-lines'));
        collectVersions(doc);

        if (versions.length === 0) {
            return null;
        }

        return Math.max(...versions);
    },

    applyEstimateVersion(version) {
        this.markEstimateRendered(version);
    },

    syncEstimateVersion(doc) {
        const freshVersion = this.resolveEstimateVersionFromDocument(doc);

        if (freshVersion === null) {
            return;
        }

        this.markEstimateRendered(freshVersion);
    },

    async sendWorksheetHeartbeat() {
        if (! this.worksheetHeartbeatUrl || document.hidden) {
            return;
        }

        try {
            const response = await fetch(this.worksheetHeartbeatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    session_token: this.worksheetSessionToken,
                    surface: this.worksheetSurface,
                    opened_estimate_version: this.openedEstimateVersion,
                }),
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            this.presenceMessage = payload.presence_message || '';
            this.worksheetLeaseValid = payload.lease_valid !== false;

            if (! heartbeatShouldReconcileVersion(this)) {
                return;
            }

            if (payload.version_drifted) {
                await this.handleRemoteEstimateChange({
                    estimate_version: payload.estimate_version,
                    message: payload.conflict?.message,
                    actor_id: payload.conflict?.actor_id,
                });
            } else {
                const heartbeatVersion = Number.parseInt(String(payload.estimate_version ?? ''), 10);

                if (! Number.isNaN(heartbeatVersion) && heartbeatVersion === this.renderedEstimateVersion) {
                    if (! this.worksheetHasDraft()) {
                        this.versionDriftNotice = '';
                        this.remoteDrift = false;
                    }
                } else if (! Number.isNaN(heartbeatVersion) && heartbeatVersion > this.renderedEstimateVersion) {
                    await this.handleRemoteEstimateChange({
                        estimate_version: heartbeatVersion,
                        actor_id: this.currentUserId,
                        message: 'This repair order was updated.',
                    });
                }
            }
        } catch {
            // Heartbeat is advisory; save guard remains authoritative.
        }
    },

    releaseWorksheetSession() {
        if (! this.worksheetReleaseUrl || ! this.worksheetSessionToken) {
            return;
        }

        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const formData = new FormData();
        formData.append('_token', csrf);
        formData.append('session_token', this.worksheetSessionToken);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(this.worksheetReleaseUrl, formData);

            return;
        }

        fetch(this.worksheetReleaseUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        }).catch(() => {});
    },

    async applyWorksheetConflict(response) {
        const payload = await response.json().catch(() => ({}));

        if (this.isSelfAuthoredEstimateChange(payload) && ! this.worksheetHasDraft()) {
            this.syncSelfAuthoredEstimateVersion(payload);

            if (typeof this.refreshWorksheet === 'function') {
                await this.refreshWorksheet(
                    window.location.href.split('#')[0],
                    document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
                );
            }

            return;
        }

        const message = payload?.message
            || 'This estimate changed while you were working. Refresh the worksheet before saving.';

        const preserveDraft = this.worksheetHasDraft();

        if (! preserveDraft && payload?.estimate_version) {
            this.applyEstimateVersion(payload.estimate_version);
        }

        this.staleNotice = message;

        if (preserveDraft) {
            this.remoteDrift = true;
            this.versionDriftNotice = message;
        }

        if (typeof this.refreshWorksheet === 'function') {
            await this.refreshWorksheet(
                window.location.href.split('#')[0],
                document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
            );

            return;
        }

        const fragment = this.conflictFragment ? `#${this.conflictFragment}` : '';

        window.location.href = `${window.location.href.split('#')[0]}${fragment}`;
    },
});
