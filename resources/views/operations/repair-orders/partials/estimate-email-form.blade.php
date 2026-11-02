@php
    use App\Ark\Operations\Leads\LeadContactPreference;

    $customerEmail = trim((string) $repairOrder->customer->email);
    $contactPreference = $repairOrder->customer->contact_preference;
    $canEmail = $repairOrder->lines->isNotEmpty();
    $missingVin = $repairOrder->missingVehicleVin();
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($canEmail)
        <form
            method="POST"
            action="{{ route('operations.repair-orders.estimate.email', $repairOrder) }}"
            data-refresh-scope="send"
            data-saving-label="Sending estimate email…"
            x-data="{
                confirmSend: false,
                sending: false,
                confirmTimer: null,
                missingVin: @js($missingVin),
                vinWarningOpen: false,
                vinAcknowledged: false,
                armConfirm() {
                    if (! this.$refs.emailInput.reportValidity()) {
                        return;
                    }

                    if (this.missingVin && ! this.vinAcknowledged) {
                        this.vinWarningOpen = true;
                        this.confirmSend = false;
                        this.clearConfirmTimer();

                        return;
                    }

                    this.vinWarningOpen = false;
                    this.confirmSend = true;
                    this.clearConfirmTimer();
                    this.confirmTimer = setTimeout(() => {
                        this.confirmSend = false;
                        this.confirmTimer = null;
                    }, 3000);
                },
                cancelVinWarning() {
                    this.vinWarningOpen = false;
                },
                continueWithoutVin() {
                    this.vinAcknowledged = true;
                    this.vinWarningOpen = false;
                    this.armConfirm();
                },
                cancelConfirm() {
                    this.clearConfirmTimer();
                    this.confirmSend = false;
                },
                clearConfirmTimer() {
                    if (this.confirmTimer) {
                        clearTimeout(this.confirmTimer);
                        this.confirmTimer = null;
                    }
                },
                async submitConfirmed(event) {
                    this.clearConfirmTimer();

                    if (! this.confirmSend || this.sending) {
                        return;
                    }

                    this.sending = true;

                    try {
                        await window.arkWorksheetFormSubmit(event);
                        this.confirmSend = false;
                    } finally {
                        this.sending = false;
                    }
                },
            }"
            @submit.prevent="submitConfirmed($event)"
            class="card-body"
            data-estimate-email-form
        >
            @csrf
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="acknowledge_missing_vin" :value="vinAcknowledged ? 1 : 0">
            <div class="mb-3">
                <div class="subheader">Email estimate</div>
                <p class="small text-secondary mb-0">Sends the current estimate PDF, portal review link, and optional note. Moves the RO to awaiting approval when sent from estimate posture.</p>
                @if ($contactPreference === LeadContactPreference::Email)
                    <div class="small fw-bold text-azure mt-1">Customer prefers email for follow-up.</div>
                @elseif ($contactPreference === LeadContactPreference::Call)
                    <div class="small fw-bold text-orange mt-1">Customer prefers a call - email only when needed.</div>
                @elseif ($contactPreference === LeadContactPreference::Text)
                    <div class="small fw-bold text-secondary mt-1">Customer prefers text - email only when needed.</div>
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label" for="estimate-email-{{ $repairOrder->repair_order_id }}">Recipient</label>
                <input
                    id="estimate-email-{{ $repairOrder->repair_order_id }}"
                    x-ref="emailInput"
                    type="email"
                    name="email"
                    value="{{ old('email', $customerEmail) }}"
                    @required($customerEmail === '')
                    placeholder="{{ $customerEmail !== '' ? $customerEmail : 'Customer email required' }}"
                    @class(['form-control', 'is-invalid' => $errors->has('email')])
                    autocomplete="email"
                    @input="cancelConfirm()"
                >
                @if ($errors->has('email'))
                    <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label" for="estimate-email-message-{{ $repairOrder->repair_order_id }}">Optional note</label>
                <textarea
                    id="estimate-email-message-{{ $repairOrder->repair_order_id }}"
                    name="message"
                    rows="2"
                    maxlength="500"
                    placeholder="Short note included in the email body"
                    class="form-control"
                    @input="cancelConfirm()"
                >{{ old('message') }}</textarea>
            </div>

            <div x-show="vinWarningOpen" x-cloak class="alert alert-warning" data-estimate-vin-warning>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if ($repairOrder->vehicle)
                        <x-tabler.vehicle-identity-chip :vehicle="$repairOrder->vehicle" />
                    @endif
                    <span class="fw-bold">VIN missing</span>
                </div>
                <div class="small">
                    Parts lookup, labor guides, service history accuracy, and OEM information may be affected.
                </div>
                <div class="btn-list mt-2">
                    <a href="#ro-identity-band" class="btn btn-sm" @click="cancelVinWarning()">Add VIN</a>
                    <button type="button" class="btn btn-sm btn-warning" @click="continueWithoutVin()">Continue anyway</button>
                </div>
            </div>

            <div class="btn-list">
                <button
                    type="button"
                    x-show="! confirmSend && ! sending"
                    x-on:click="armConfirm()"
                    class="btn btn-primary"
                    @if ($customerEmail === '') title="Enter customer email to send" @endif
                >
                    Email estimate
                </button>

                <button
                    type="submit"
                    x-show="confirmSend && ! sending"
                    x-cloak
                    class="btn btn-warning"
                >
                    Confirm send
                </button>

                <button
                    type="button"
                    x-show="confirmSend && ! sending"
                    x-cloak
                    x-on:click="cancelConfirm()"
                    class="btn"
                >
                    Cancel
                </button>
            </div>

            <div
                x-show="confirmSend && ! sending"
                x-cloak
                class="small fw-bold text-orange mt-2"
            >
                Confirm to email the PDF and portal link to <span x-text="$refs.emailInput.value || 'this address'"></span>. Resets in 3 seconds.
            </div>
        </form>
    @elseif (! $isTerminal)
        <p class="card-body small text-secondary mb-0">Add estimate lines before emailing the customer.</p>
    @endif
@endcan
