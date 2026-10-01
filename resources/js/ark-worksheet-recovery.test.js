import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    applySocketEvent,
    heartbeatShouldReconcileVersion,
    noteWorksheetSocketEvent,
    queueWorksheetRecovery,
    flushDeferredWorksheetRecovery,
    SOCKET_LOSS_EVENTS,
    visibilityNeedsRecovery,
    WORKSHEET_HEARTBEAT_WITH_SOCKET_MS,
} from './ark-worksheet-recovery.js';

function recoveryHost(overrides = {}) {
    const scheduled = [];
    const host = {
        socketPhase: 'initial',
        worksheetBusyPending: false,
        worksheetSaving: false,
        worksheetRecoveryTimer: null,
        worksheetRecoveryInFlight: false,
        worksheetRecoveryDeferred: false,
        worksheetRecoveryFollowUp: false,
        renderedEstimateVersion: 4,
        remoteDrift: false,
        versionDriftNotice: '',
        laborDescription: 'Bleed brakes',
        laborOpenedEstimateVersion: 4,
        narrative: 'Customer hears a grind',
        narrativeOpenedEstimateVersion: 4,
        visitReason: 'Brake noise',
        visitReasonOpenedEstimateVersion: 4,
        paymentAmount: '25.00',
        balanceLabel: '$120.00',
        recoveryUrl: 'https://app.test/app/repair-orders/1',
        recoveryAnchor: { id: 'estimate-lines' },
        refreshCalls: 0,
        financialCalls: 0,
        nextVersion: null,
        nextBalance: null,
        worksheetHasDraft() {
            return false;
        },
        async refreshWorksheet() {
            this.refreshCalls += 1;

            if (this.nextVersion !== null) {
                this.renderedEstimateVersion = this.nextVersion;
            }

            if (this.nextBalance !== null) {
                this.balanceLabel = this.nextBalance;
            }
        },
        async refreshLoadedFinancialTab() {
            this.financialCalls += 1;
        },
        scheduleRecoveryTimer(callback) {
            scheduled.push(callback);

            return scheduled.length;
        },
        scheduled,
        ...overrides,
    };

    return host;
}

async function runScheduled(host) {
    const callback = host.scheduled.shift();
    host.worksheetRecoveryTimer = null;

    return callback();
}

test('A. initial socket connection does not trigger recovery refresh', () => {
    const host = recoveryHost();

    noteWorksheetSocketEvent(host, 'disconnected');
    noteWorksheetSocketEvent(host, 'unavailable');
    noteWorksheetSocketEvent(host, 'failed');
    const connected = noteWorksheetSocketEvent(host, 'connected');

    assert.equal(connected.reconcile, false);
    assert.equal(host.socketPhase, 'live');
    assert.equal(host.scheduled.length, 0);
    assert.equal(applySocketEvent('live', 'connected').reconcile, false);
});

test('B. disconnect then reconnect triggers exactly one reconciliation', async () => {
    const host = recoveryHost();
    noteWorksheetSocketEvent(host, 'connected');

    for (const event of SOCKET_LOSS_EVENTS) {
        const lost = recoveryHost();
        noteWorksheetSocketEvent(lost, 'connected');
        noteWorksheetSocketEvent(lost, event);
        assert.equal(lost.socketPhase, 'recovering');
        noteWorksheetSocketEvent(lost, 'connected');
        noteWorksheetSocketEvent(lost, 'connected');
        assert.equal(lost.scheduled.length, 1);
        lost.nextVersion = 5;
        await runScheduled(lost);
        assert.equal(lost.refreshCalls, 1);
        assert.equal(lost.financialCalls, 1);
        assert.equal(lost.scheduled.length, 0);
    }

    assert.equal(host.scheduled.length, 0);
});

test('C. duplicate reconnect and online signals coalesce to one refresh', async () => {
    const host = recoveryHost({ nextVersion: 5 });

    queueWorksheetRecovery(host);
    queueWorksheetRecovery(host);
    queueWorksheetRecovery(host);
    assert.equal(host.scheduled.length, 1);

    let signaledDuringFetch = false;
    host.refreshWorksheet = async function refreshDuringRecovery() {
        this.refreshCalls += 1;
        this.renderedEstimateVersion = 5;

        if (signaledDuringFetch) {
            return;
        }

        signaledDuringFetch = true;
        queueWorksheetRecovery(this);
        queueWorksheetRecovery(this);
        queueWorksheetRecovery(this);
    };

    await runScheduled(host);
    assert.equal(host.refreshCalls, 1);
    assert.equal(host.scheduled.length, 1);

    await runScheduled(host);
    assert.equal(host.refreshCalls, 2);
    assert.equal(host.scheduled.length, 0);
});

test('D. hidden to visible is the recovery boundary', () => {
    assert.equal(visibilityNeedsRecovery(true, false), true);
    assert.equal(visibilityNeedsRecovery(false, false), false);
    assert.equal(visibilityNeedsRecovery(false, true), false);
    assert.equal(visibilityNeedsRecovery(true, true), false);
});

test('E. reconnect while the worksheet is clean updates authoritative surfaces', async () => {
    const host = recoveryHost({
        nextVersion: 5,
        nextBalance: '$80.00',
    });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'disconnected');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.refreshCalls, 1);
    assert.equal(host.financialCalls, 1);
    assert.equal(host.renderedEstimateVersion, 5);
    assert.equal(host.balanceLabel, '$80.00');
    assert.equal(host.remoteDrift, false);
    assert.equal(host.versionDriftNotice, '');
});

test('F. reconnect while Add Labor is dirty preserves the draft token', async () => {
    const host = recoveryHost({
        nextVersion: 5,
        worksheetHasDraft() {
            return true;
        },
    });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'disconnected');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.laborDescription, 'Bleed brakes');
    assert.equal(host.laborOpenedEstimateVersion, 4);
    assert.equal(host.renderedEstimateVersion, 5);
    assert.equal(host.remoteDrift, true);
    assert.match(host.versionDriftNotice, /Refresh the worksheet before saving/);
});

test('G. reconnect while concern narrative is dirty preserves it', async () => {
    const host = recoveryHost({
        nextVersion: 5,
        worksheetHasDraft() {
            return true;
        },
    });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'failed');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.narrative, 'Customer hears a grind');
    assert.equal(host.narrativeOpenedEstimateVersion, 4);
    assert.equal(host.remoteDrift, true);
});

test('H. reconnect while Visit Reason is dirty preserves it', async () => {
    const host = recoveryHost({
        nextVersion: 5,
        worksheetHasDraft() {
            return true;
        },
    });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'unavailable');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.visitReason, 'Brake noise');
    assert.equal(host.visitReasonOpenedEstimateVersion, 4);
    assert.equal(host.renderedEstimateVersion, 5);
});

test('I. reconnect after a missed financial change updates safe financial state', async () => {
    const host = recoveryHost({ nextBalance: '$40.00' });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'disconnected');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.renderedEstimateVersion, 4);
    assert.equal(host.balanceLabel, '$40.00');
    assert.equal(host.refreshCalls, 1);
    assert.equal(host.financialCalls, 1);
    assert.equal(host.remoteDrift, false);
});

test('J. a dirty financial form survives reconnect', async () => {
    const host = recoveryHost({
        worksheetHasDraft() {
            return true;
        },
    });
    noteWorksheetSocketEvent(host, 'connected');
    noteWorksheetSocketEvent(host, 'disconnected');
    noteWorksheetSocketEvent(host, 'connected');

    await runScheduled(host);

    assert.equal(host.paymentAmount, '25.00');
    assert.equal(host.renderedEstimateVersion, 4);
    assert.equal(host.versionDriftNotice, '');
    assert.equal(host.refreshCalls, 1);
    assert.equal(host.financialCalls, 1);
});

test('K. reconciliation during an in-flight save waits until the save finishes', async () => {
    const host = recoveryHost({
        worksheetBusyPending: true,
        laborDescription: 'Still typing',
        nextVersion: 5,
    });

    queueWorksheetRecovery(host);
    assert.equal(host.scheduled.length, 0);
    assert.equal(host.refreshCalls, 0);
    assert.equal(host.worksheetRecoveryDeferred, true);
    assert.equal(host.laborDescription, 'Still typing');

    host.worksheetBusyPending = false;
    flushDeferredWorksheetRecovery(host);
    assert.equal(host.scheduled.length, 1);

    await runScheduled(host);

    assert.equal(host.refreshCalls, 1);
    assert.equal(host.laborDescription, 'Still typing');
    assert.equal(host.laborOpenedEstimateVersion, 4);
    assert.equal(host.scheduled.length, 0);
});

test('L. heartbeat stays the fallback and does not refresh beside recovery', () => {
    assert.equal(WORKSHEET_HEARTBEAT_WITH_SOCKET_MS, 90_000);
    assert.equal(heartbeatShouldReconcileVersion(recoveryHost()), true);
    assert.equal(heartbeatShouldReconcileVersion(recoveryHost({
        worksheetRecoveryTimer: 1,
    })), false);
    assert.equal(heartbeatShouldReconcileVersion(recoveryHost({
        worksheetRecoveryInFlight: true,
    })), false);
    assert.equal(heartbeatShouldReconcileVersion(recoveryHost({
        worksheetRecoveryDeferred: true,
    })), false);

    const source = readFileSync(new URL('./ark-worksheet-collaboration.js', import.meta.url), 'utf8');
    assert.match(source, /connection\.bind\('connected'/);
    assert.match(source, /SOCKET_LOSS_EVENTS/);
    assert.match(source, /connection\.state === 'connected'/);
    assert.match(source, /addEventListener\('online'/);
    assert.match(source, /visibilityNeedsRecovery/);
    assert.match(source, /heartbeatShouldReconcileVersion/);
    assert.match(source, /WORKSHEET_HEARTBEAT_WITH_SOCKET_MS/);
    assert.deepEqual(SOCKET_LOSS_EVENTS, ['disconnected', 'unavailable', 'failed']);
});
