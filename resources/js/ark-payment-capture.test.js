import assert from 'node:assert/strict';
import test from 'node:test';
import { applyTerminalPayment, nextPaymentAmount, normalizePaymentAmount, paymentCalendarCells, paymentWorkspaceIsTransactionBound, presentingTerminalPayment, shiftPaymentCalendarMonth, stampPaymentCaptureFields } from './ark-payment-capture.js';

function form(values) {
    const inputs = Object.fromEntries(
        Object.entries(values).map(([name, value]) => [name, { value }]),
    );

    return {
        querySelector(selector) {
            const match = String(selector).match(/name="([^"]+)"/);

            return match ? inputs[match[1]] ?? null : null;
        },
        inputs,
    };
}

test('first keyed click posts the new card token, not the empty field', () => {
    const captureForm = form({
        capture_method: 'terminal',
        source_token: '',
        device_ref: 'device-1',
    });

    stampPaymentCaptureFields(captureForm, {
        method: 'keyed',
        sourceToken: 'cnon:first-click',
        deviceRef: 'device-1',
    });

    assert.equal(captureForm.inputs.capture_method.value, 'keyed');
    assert.equal(captureForm.inputs.source_token.value, 'cnon:first-click');
    assert.equal(captureForm.inputs.device_ref.value, '');
});

test('a later keyed click posts that click\'s token', () => {
    const captureForm = form({
        capture_method: 'keyed',
        source_token: 'cnon:first-click',
        device_ref: '',
    });

    stampPaymentCaptureFields(captureForm, {
        method: 'keyed',
        sourceToken: 'cnon:second-click',
        deviceRef: '',
    });

    assert.equal(captureForm.inputs.source_token.value, 'cnon:second-click');
});

test('terminal submit posts the device and no card token', () => {
    const captureForm = form({
        capture_method: 'keyed',
        source_token: 'cnon:left-over',
        device_ref: '',
    });

    stampPaymentCaptureFields(captureForm, {
        method: 'terminal',
        sourceToken: 'cnon:left-over',
        deviceRef: 'front-counter',
    });

    assert.equal(captureForm.inputs.capture_method.value, 'terminal');
    assert.equal(captureForm.inputs.source_token.value, '');
    assert.equal(captureForm.inputs.device_ref.value, 'front-counter');
});

test('presenting a terminal payment shows the amount before the reader accepts it', () => {
    const view = presentingTerminalPayment('435.38');

    assert.equal(view.phase, 'presenting');
    assert.equal(view.headline, 'Presenting to reader');
    assert.equal(view.note, 'Sending this payment to the reader.');
    assert.equal(view.amount, '$435.38');
    assert.equal(view.spinning, true);
    assert.equal(view.blocksForm, true);
});

test('an accepted terminal checkout is the customer wait, not processing', () => {
    const view = applyTerminalPayment({
        phase: 'waiting',
        headline: 'Waiting for customer',
        note: 'Payment sent to reader',
        detail: 'Waiting for the customer to complete payment.',
        amount: '$435.38',
        spinning: true,
        blocks_form: true,
        refresh_url: '/refresh',
        cancel_url: '/cancel',
    });

    assert.equal(view.phase, 'waiting');
    assert.equal(view.headline, 'Waiting for customer');
    assert.equal(view.amount, '$435.38');
    assert.equal(view.blocksForm, true);
    assert.equal(view.refreshUrl, '/refresh');
    assert.equal(view.cancelUrl, '/cancel');
});

test('the keypad edits the typed amount and does not invent a balance', () => {
    assert.equal(normalizePaymentAmount('$425.38'), '425.38');
    assert.equal(nextPaymentAmount('425.38', '1', true), '1');
    assert.equal(nextPaymentAmount('1', '2', false), '12');
    assert.equal(nextPaymentAmount('12', '.', false), '12.');
    assert.equal(nextPaymentAmount('12.', '5', false), '12.5');
    assert.equal(nextPaymentAmount('12.5', '9', false), '12.59');
    assert.equal(nextPaymentAmount('12.59', '9', false), '12.59');
    assert.equal(nextPaymentAmount('12.59', 'backspace', false), '12.5');
});

test('the paid date calendar stops at today', () => {
    assert.equal(shiftPaymentCalendarMonth('2026-09', 1, '2026-09-26'), '2026-09');
    assert.equal(shiftPaymentCalendarMonth('2026-09', -1, '2026-09-26'), '2026-08');

    const cells = paymentCalendarCells('2026-09', '2026-09-26', '');
    const day26 = cells.find((cell) => cell.iso === '2026-09-26');
    const day27 = cells.find((cell) => cell.iso === '2026-09-27');

    assert.equal(day26.selected, true);
    assert.equal(day26.disabled, false);
    assert.equal(day27.disabled, true);
});

test('a live reader phase cannot be dismissed casually', () => {
    assert.equal(paymentWorkspaceIsTransactionBound('presenting'), true);
    assert.equal(paymentWorkspaceIsTransactionBound('waiting'), true);
    assert.equal(paymentWorkspaceIsTransactionBound('processing'), true);
    assert.equal(paymentWorkspaceIsTransactionBound('reconciliation'), true);
    assert.equal(paymentWorkspaceIsTransactionBound('approved'), false);
    assert.equal(paymentWorkspaceIsTransactionBound(''), false);
});
