export const WORKSHEET_HEARTBEAT_WITH_SOCKET_MS = 90_000;

export const WORKSHEET_RECOVERY_COALESCE_MS = 300;

export const SOCKET_LOSS_EVENTS = ['disconnected', 'unavailable', 'failed'];

const DRIFT_MESSAGE = 'This estimate changed while you were working. Refresh the worksheet before saving.';

export function applySocketEvent(phase, event) {
    if (event === 'connected') {
        if (phase === 'initial') {
            return { phase: 'live', reconcile: false };
        }

        if (phase === 'recovering') {
            return { phase: 'live', reconcile: true };
        }

        return { phase, reconcile: false };
    }

    if (SOCKET_LOSS_EVENTS.includes(event) && phase === 'live') {
        return { phase: 'recovering', reconcile: false };
    }

    return { phase, reconcile: false };
}

export function visibilityNeedsRecovery(wasHidden, hiddenNow) {
    return Boolean(wasHidden) && ! hiddenNow;
}

export function acceptRecoverySignal(gate) {
    if ((gate.busy || gate.saving) && ! gate.inFlight) {
        return { ...gate, deferred: true, schedule: false };
    }

    if (gate.timerPending || gate.inFlight) {
        return {
            ...gate,
            followUp: gate.inFlight ? true : gate.followUp,
            schedule: false,
        };
    }

    return { ...gate, schedule: true };
}

export function flushRecoveryGate(gate) {
    if (gate.inFlight || gate.timerPending || gate.busy || gate.saving) {
        return { ...gate, schedule: false };
    }

    if (! gate.deferred && ! gate.followUp) {
        return { ...gate, schedule: false };
    }

    return {
        ...gate,
        deferred: false,
        followUp: false,
        schedule: true,
    };
}

export function heartbeatShouldReconcileVersion(host) {
    return ! (host.worksheetRecoveryTimer || host.worksheetRecoveryInFlight || host.worksheetRecoveryDeferred);
}

function gateFrom(host) {
    return {
        busy: Boolean(host.worksheetBusyPending),
        saving: Boolean(host.worksheetSaving),
        inFlight: Boolean(host.worksheetRecoveryInFlight),
        timerPending: Boolean(host.worksheetRecoveryTimer),
        deferred: Boolean(host.worksheetRecoveryDeferred),
        followUp: Boolean(host.worksheetRecoveryFollowUp),
    };
}

function writeGate(host, gate) {
    host.worksheetRecoveryDeferred = Boolean(gate.deferred);
    host.worksheetRecoveryFollowUp = Boolean(gate.followUp);
}

export function queueWorksheetRecovery(host) {
    const gate = acceptRecoverySignal(gateFrom(host));
    writeGate(host, gate);

    if (! gate.schedule || host.worksheetRecoveryTimer) {
        return;
    }

    const schedule = typeof host.scheduleRecoveryTimer === 'function'
        ? host.scheduleRecoveryTimer.bind(host)
        : (callback) => setTimeout(callback, WORKSHEET_RECOVERY_COALESCE_MS);

    host.worksheetRecoveryTimer = schedule(() => {
        host.worksheetRecoveryTimer = null;

        return runWorksheetRecovery(host);
    });
}

export function flushDeferredWorksheetRecovery(host) {
    const gate = flushRecoveryGate(gateFrom(host));
    writeGate(host, gate);

    if (gate.schedule) {
        queueWorksheetRecovery(host);
    }
}

export async function runWorksheetRecovery(host) {
    if ((host.worksheetBusyPending || host.worksheetSaving) && ! host.worksheetRecoveryInFlight) {
        host.worksheetRecoveryDeferred = true;

        return { ran: false };
    }

    host.worksheetRecoveryInFlight = true;

    try {
        return await reconcileWorksheet(host);
    } finally {
        host.worksheetRecoveryInFlight = false;
        flushDeferredWorksheetRecovery(host);
    }
}

export function noteWorksheetSocketEvent(host, event) {
    const next = applySocketEvent(host.socketPhase ?? 'initial', event);
    host.socketPhase = next.phase;

    if (next.reconcile) {
        queueWorksheetRecovery(host);
    }

    return next;
}

export async function reconcileWorksheet(host) {
    const before = Number.parseInt(String(host.renderedEstimateVersion ?? ''), 10);
    const url = String(host.recoveryUrl || (typeof window !== 'undefined' ? window.location.href : '')).split('#')[0];
    const anchor = host.recoveryAnchor
        ?? (typeof document !== 'undefined'
            ? document.getElementById(host.worksheetScopeId ?? host.conflictFragment ?? 'estimate-lines')
            : null);

    if (typeof host.refreshWorksheet === 'function') {
        await host.refreshWorksheet(url, anchor);
    }

    if (typeof host.refreshLoadedFinancialTab === 'function') {
        await host.refreshLoadedFinancialTab();
    }

    const after = Number.parseInt(String(host.renderedEstimateVersion ?? ''), 10);
    const draftRemains = typeof host.worksheetHasDraft === 'function' && host.worksheetHasDraft();

    if (draftRemains && ! Number.isNaN(before) && ! Number.isNaN(after) && after > before) {
        host.remoteDrift = true;
        host.versionDriftNotice = host.versionDriftNotice || DRIFT_MESSAGE;
    }

    return {
        refreshed: typeof host.refreshWorksheet === 'function',
        financial: typeof host.refreshLoadedFinancialTab === 'function',
        drifted: Boolean(host.remoteDrift) && draftRemains && after > before,
    };
}
