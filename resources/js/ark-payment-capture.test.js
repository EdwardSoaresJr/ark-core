import assert from 'node:assert/strict';
import test from 'node:test';
import { stampPaymentCaptureFields } from './ark-payment-capture.js';

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
