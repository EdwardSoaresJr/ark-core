@php
    $platformPaymentsCapture = (bool) ($platformPaymentsCapture ?? false);
    $readerNames = $settings->paymentReaderNames();
    $readers = [];
    if ($platformPaymentsCapture) {
        foreach (app(\App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection::class)->current()['devices'] ?? [] as $device) {
            $readers[$device['device_ref']] = ['available' => (bool) ($device['ready'] ?? true)];
        }
    }
    foreach (array_keys($readerNames) as $namedRef) {
        $readers[$namedRef] ??= ['available' => false];
    }
@endphp

<div class="card-header">
    <div>
        <div class="subheader">Square Payments</div>
        <h2 class="card-title">Card capture inside ARK</h2>
        @if ($platformPaymentsCapture)
            <div class="card-subtitle">
                Card capture is connected for this shop. Choose where a card payment may be offered.
                Take Payment and Record Payment stay on the repair order.
            </div>
        @else
            <div class="card-subtitle">
                Card capture inside ARK is not connected. Record a card, cash, or check taken at the counter.
                A card button appears only when a processor can settle the charge.
            </div>
        @endif
    </div>
</div>

<div class="card-body">
    @if ($platformPaymentsCapture)
        <div class="alert alert-success" role="status" data-card-capture-status="connected">
            Card capture is connected for this shop.
        </div>
    @else
        <div class="alert alert-warning" role="status" data-card-capture-status="not-connected">
            Card capture is not connected. Record Payment still works on the repair order.
        </div>
    @endif

    <form method="POST" action="{{ route('operations.settings.shop.payments.update') }}" style="max-width: 48rem" data-settings-card-payments>
        @csrf
        @method('PATCH')

        <h3 class="mb-1">Where card payments are offered</h3>
        <p class="text-secondary small">
            These are shop rules for where a card payment may be offered. They do not store a processor account.
        </p>

        <div class="mb-3">
            <label for="settings-square-terminal-device" class="form-label">Preferred terminal</label>
            <input
                id="settings-square-terminal-device"
                type="text"
                name="square_terminal_device_id"
                value="{{ old('square_terminal_device_id', $settings->square_terminal_device_id) }}"
                @class(['form-control font-monospace', 'is-invalid' => $errors->has('square_terminal_device_id')])
                placeholder="Default counter reader"
                autocomplete="off"
            >
            @error('square_terminal_device_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-hint">Used for counter Take Payment when this shop has a reader. Leave blank if none is assigned yet.</div>
        </div>

        @if ($readers !== [])
            <div class="mb-3" data-payment-reader-names>
                <div class="form-label mb-1">Card readers</div>
                <p class="text-secondary small">Name each reader the way staff know it, such as Front Counter or Bay 1. Everyone in the shop sees these names. Leave a name blank to show the device ID.</p>
                <div class="list-group">
                    @foreach ($readers as $deviceRef => $reader)
                        <div class="list-group-item">
                            <label for="settings-payment-reader-{{ $loop->index }}" class="form-label small mb-1">
                                <span class="font-monospace">{{ $deviceRef }}</span>
                                @unless ($reader['available'])
                                    <span class="badge bg-secondary-lt ms-1">not currently available</span>
                                @endunless
                            </label>
                            <input
                                id="settings-payment-reader-{{ $loop->index }}"
                                type="text"
                                name="payment_reader_names[{{ $deviceRef }}]"
                                value="{{ old('payment_reader_names.'.$deviceRef, $readerNames[$deviceRef] ?? '') }}"
                                maxlength="60"
                                class="form-control"
                                placeholder="Front Counter"
                                autocomplete="off"
                            >
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <fieldset>
            <legend class="form-label">Offer card payment at</legend>
            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <label class="form-check form-switch mb-0">
                        <input type="hidden" name="square_terminal_enabled" value="0">
                        <input type="checkbox" name="square_terminal_enabled" value="1" @checked(old('square_terminal_enabled', $settings->square_terminal_enabled)) class="form-check-input">
                        <span class="form-check-label">Counter terminal</span>
                    </label>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-check form-switch mb-0">
                        <input type="hidden" name="square_keyed_enabled" value="0">
                        <input type="checkbox" name="square_keyed_enabled" value="1" @checked(old('square_keyed_enabled', $settings->square_keyed_enabled)) class="form-check-input">
                        <span class="form-check-label">Counter keyed entry</span>
                    </label>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-check form-switch mb-0">
                        <input type="hidden" name="square_portal_pay_enabled" value="0">
                        <input type="checkbox" name="square_portal_pay_enabled" value="1" @checked(old('square_portal_pay_enabled', $settings->square_portal_pay_enabled)) class="form-check-input">
                        <span class="form-check-label">Customer portal pay</span>
                    </label>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-check form-switch mb-0">
                        <input type="hidden" name="square_email_pay_enabled" value="0">
                        <input type="checkbox" name="square_email_pay_enabled" value="1" @checked(old('square_email_pay_enabled', $settings->square_email_pay_enabled)) class="form-check-input">
                        <span class="form-check-label">Emailed invoice pay link</span>
                    </label>
                </div>
            </div>
        </fieldset>

        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">
                Save card payment options
            </button>
        </div>
    </form>
</div>

@php
    $manualPaymentMethods = \App\Ark\Operations\Financial\ManualPaymentMethods::current();
@endphp

<div class="card-body">
    <form
        method="POST"
        action="{{ route('operations.settings.shop.manual-payment-methods.update') }}"
        x-data="{ methods: @js($manualPaymentMethods) }"
        style="max-width: 48rem"
        data-settings-manual-payment-methods
    >
        @csrf
        @method('PATCH')
        <div class="subheader">Take payment</div>
        <h3 class="mb-1">Other ways to pay</h3>
        <p class="text-secondary small">These buttons sit under Cash. Check, External Card, and EFT start on every shop. Rename one, add one, or remove one.</p>

        @error('methods')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror

        <div class="list-group">
            <template x-for="(method, index) in methods" :key="index">
                <div class="list-group-item">
                    <div class="d-flex align-items-center gap-2">
                        <input type="hidden" :name="'methods[' + index + '][key]'" x-model="method.key">
                        <label class="visually-hidden" :for="'manual-payment-method-' + index">Name</label>
                        <input :id="'manual-payment-method-' + index" class="form-control fw-medium" :name="'methods[' + index + '][label]'" x-model="method.label" maxlength="24" required>
                        <button type="button" class="btn btn-ghost-danger" @click="methods.splice(index, 1)">Delete</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="btn-list justify-content-end border-top pt-3 mt-3">
            <button type="button" class="btn" :disabled="methods.length >= 8" @click="methods.push({ key: '', label: '' })">Add</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
