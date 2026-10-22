import assert from 'node:assert/strict';
import test from 'node:test';
import { applyTerminalPayment, chooseReader, nextPaymentAmount, normalizePaymentAmount, payableReader, paymentCalendarCells, paymentWorkspaceIsTransactionBound, presentingTerminalPayment, readerButtonLabel, readerDisplay, readerNotice, rememberReaderChoice, rememberedReader, shiftPaymentCalendarMonth, stampPaymentCaptureFields } from './ark-payment-capture.js';

function memoryStorage() {
    const values = new Map();

    return {
        getItem: (key) => (values.has(key) ? values.get(key) : null),
        setItem: (key, value) => values.set(key, String(value)),
        values,
    };
}

const frontCounter = { device_ref: '549CS149C4000527', label: 'Front Counter' };
const bayOne = { device_ref: '549CS149C4000999', label: 'Bay 1' };

test('the only available reader is chosen without a remembered one', () => {
    assert.deepEqual(chooseReader([frontCounter], ''), { deviceRef: frontCounter.device_ref, rememberedUnavailable: false });
});

test('several readers and nothing remembered require a choice', () => {
    assert.deepEqual(chooseReader([frontCounter, bayOne], ''), { deviceRef: '', rememberedUnavailable: false });
});

test('a remembered reader that is still available is restored', () => {
    assert.equal(chooseReader([frontCounter, bayOne], bayOne.device_ref).deviceRef, bayOne.device_ref);
});

test('a remembered reader that is missing is kept and never swapped for the remaining reader', () => {
    assert.deepEqual(chooseReader([bayOne], frontCounter.device_ref), { deviceRef: frontCounter.device_ref, rememberedUnavailable: true });
    assert.deepEqual(chooseReader([], frontCounter.device_ref), { deviceRef: frontCounter.device_ref, rememberedUnavailable: true });
    assert.equal(payableReader([bayOne], frontCounter.device_ref), null);
    assert.equal(readerButtonLabel('566.76', [bayOne], frontCounter.device_ref), 'Saved reader is not available');
    assert.match(readerNotice([bayOne], frontCounter.device_ref), /not available/);
});

test('a remembered reader that is offline stays selected, blocks payment, and returns by itself', () => {
    const offline = [{ ...frontCounter, available: false }, bayOne];

    assert.deepEqual(chooseReader(offline, frontCounter.device_ref), { deviceRef: frontCounter.device_ref, rememberedUnavailable: true });
    assert.equal(payableReader(offline, frontCounter.device_ref), null);
    assert.equal(readerButtonLabel('566.76', offline, frontCounter.device_ref), 'Front Counter is offline');
    assert.match(readerNotice(offline, frontCounter.device_ref), /^Front Counter is offline/);

    const back = [{ ...frontCounter, available: true }, bayOne];
    assert.deepEqual(chooseReader(back, frontCounter.device_ref), { deviceRef: frontCounter.device_ref, rememberedUnavailable: false });
    assert.equal(payableReader(back, frontCounter.device_ref), back[0]);
    assert.equal(readerNotice(back, frontCounter.device_ref), '');
});

test('an offline reader is not chosen as the only reader', () => {
    assert.deepEqual(chooseReader([{ ...frontCounter, available: false }], ''), { deviceRef: '', rememberedUnavailable: false });
    assert.deepEqual(chooseReader([{ ...frontCounter, available: false }, bayOne], ''), { deviceRef: bayOne.device_ref, rememberedUnavailable: false });
});

test('the reader control shows the shop name first and the device id as secondary text', () => {
    assert.deepEqual(readerDisplay([frontCounter, bayOne], frontCounter.device_ref), { primary: 'Front Counter', secondary: '549CS149C4000527' });
    assert.deepEqual(readerDisplay([{ device_ref: '549CS149C4000527', label: '549CS149C4000527' }], '549CS149C4000527'), { primary: '549CS149C4000527', secondary: '' });
    assert.deepEqual(readerDisplay([{ ...frontCounter, available: false }], frontCounter.device_ref), { primary: 'Front Counter (offline)', secondary: '549CS149C4000527' });
    assert.deepEqual(readerDisplay([bayOne], frontCounter.device_ref), { primary: 'Saved reader is not available', secondary: '549CS149C4000527' });
    assert.deepEqual(readerDisplay([frontCounter, bayOne], ''), { primary: 'Choose a reader', secondary: '' });
});

test('the payment button names the chosen reader and waits for one', () => {
    assert.equal(readerButtonLabel('566.76', [frontCounter, bayOne], frontCounter.device_ref), 'Present $566.76 to Front Counter');
    assert.equal(readerButtonLabel('566.76', [{ device_ref: '549CS149C4000527', label: '' }], '549CS149C4000527'), 'Present $566.76 to 549CS149C4000527');
    assert.equal(readerButtonLabel('566.76', [frontCounter, bayOne], ''), 'Choose a reader');
});

test('the browser remembers the reader id, not its name, separately for each shop', () => {
    const storage = memoryStorage();
    rememberReaderChoice(storage, 'ark:payment-reader:shop-a', frontCounter.device_ref);

    assert.equal(rememberedReader(storage, 'ark:payment-reader:shop-a'), frontCounter.device_ref);
    assert.equal(rememberedReader(storage, 'ark:payment-reader:shop-b'), '');

    const renamed = { ...frontCounter, label: 'Reception' };
    assert.equal(chooseReader([renamed, bayOne], rememberedReader(storage, 'ark:payment-reader:shop-a')).deviceRef, frontCounter.device_ref);
});

test('blocked browser storage never breaks reader choice', () => {
    const blocked = { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } };

    assert.equal(rememberedReader(blocked, 'ark:payment-reader:shop-a'), '');
    assert.doesNotThrow(() => rememberReaderChoice(blocked, 'ark:payment-reader:shop-a', frontCounter.device_ref));
    assert.equal(chooseReader([frontCounter], rememberedReader(blocked, 'key')).deviceRef, frontCounter.device_ref);
});

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
