import { arkEchoEnabled, getArkEcho } from './ark-echo';

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

export const syncEstimateVersionInputs = (fieldName, version) => {
    if (! fieldName || version === undefined || version === null) {
        return;
    }

    document
        .querySelectorAll(`input[name="${CSS.escape(fieldName)}"]`)
        .forEach((input) => {
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
    conflictFragment: config.conflictFragment ?? 'estimate-lines',
    currentUserId: config.currentUserId ?? null,

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

        this.heartbeatTimer = window.setInterval(() => this.sendWorksheetHeartbeat(), 90_000);

        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                this.sendWorksheetHeartbeat();
            }
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
    },

    async handleRemoteFinancialChange(payload) {
        if (typeof this.refreshScope !== 'function') {
            return;
        }

        if (this.financialRefreshTimer) {
            window.clearTimeout(this.financialRefreshTimer);
        }

        this.financialRefreshTimer = window.setTimeout(async () => {
            this.financialRefreshTimer = null;

            if (this.isWorksheetEditActive()) {
                return;
            }

            await this.refreshScope('rail');
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

        if (! this.broadcastChannel) {
            return;
        }

        getArkEcho()?.leave(this.broadcastChannel);
        this.realtimeChannel = null;
    },

    isWorksheetEditActive() {
        if (this.worksheetBusyPending || this.worksheetSaving) {
            return true;
        }

        if (new URL(window.location.href).searchParams.has('editing_line')) {
            return true;
        }

        return Boolean(document.querySelector('form[id^="line-update-"]'));
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

        if (this.isSelfAuthoredEstimateChange(payload)) {
            this.syncSelfAuthoredEstimateVersion(payload);

            return;
        }

        if (Number.isNaN(version) || version <= this.renderedEstimateVersion) {
            return;
        }

        const message = payload?.message
            || 'This estimate changed while you were working. Refresh the worksheet before saving.';

        window.ARK?.workspace?.refreshActivity?.();

        if (this.isWorksheetEditActive()) {
            this.versionDriftNotice = message;

            return;
        }

        if (typeof this.refreshWorksheet !== 'function') {
            this.versionDriftNotice = message;

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

            if (this.isWorksheetEditActive()) {
                this.versionDriftNotice = message;

                return;
            }

            await this.refreshWorksheet(
                window.location.href.split('#')[0],
                document.getElementById(this.worksheetScopeId ?? this.conflictFragment ?? 'estimate-lines'),
            );

            this.clearStaleNotice();
        }, 150);
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

            if (payload.version_drifted) {
                if (this.isSelfAuthoredEstimateChange(payload.conflict ?? payload)) {
                    this.syncSelfAuthoredEstimateVersion(payload);

                    return;
                }

                await this.handleRemoteEstimateChange({
                    estimate_version: payload.estimate_version,
                    message: payload.conflict?.message,
                    actor_id: payload.conflict?.actor_id,
                });
            } else {
                const heartbeatVersion = Number.parseInt(String(payload.estimate_version ?? ''), 10);

                if (! Number.isNaN(heartbeatVersion) && heartbeatVersion === this.renderedEstimateVersion) {
                    this.versionDriftNotice = '';
                } else if (! Number.isNaN(heartbeatVersion)) {
                    this.markEstimateRendered(heartbeatVersion);
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

        if (this.isSelfAuthoredEstimateChange(payload)) {
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

        if (payload?.estimate_version) {
            this.applyEstimateVersion(payload.estimate_version);
        }

        this.staleNotice = message;

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
