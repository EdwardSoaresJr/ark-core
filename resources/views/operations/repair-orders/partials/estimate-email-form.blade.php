@php
    use App\Ark\Operations\Leads\LeadContactPreference;

    $customerEmail = trim((string) $repairOrder->customer->email);
    $contactPreference = $repairOrder->customer->contact_preference;
    $canEmail = ! $isTerminal && $repairOrder->lines->isNotEmpty();
    $missingVin = $repairOrder->missingVehicleVin();
    $timingFluids = app(\App\Ark\Operations\RepairOrders\EstimateCompanionCompletenessProjection::class)->for($repairOrder);
    $timingFluidsMissing = (bool) ($timingFluids['needs_attention'] ?? false);
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
                timingFluidsMissing: @js($timingFluidsMissing),
                fluidsWarningOpen: false,
                fluidsAcknowledged: false,
                armConfirm() {
                    if (! this.$refs.emailInput.reportValidity()) {
                        return;
                    }

                    if (this.missingVin && ! this.vinAcknowledged) {
                        this.vinWarningOpen = true;
                        this.fluidsWarningOpen = false;
                        this.confirmSend = false;
                        this.clearConfirmTimer();

                        return;
                    }

                    if (this.timingFluidsMissing && ! this.fluidsAcknowledged) {
                        this.fluidsWarningOpen = true;
                        this.vinWarningOpen = false;
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
                    this.fluidsWarningOpen = false;
                },
                continueWithoutVin() {
                    this.vinAcknowledged = true;
                    this.vinWarningOpen = false;
                    this.armConfirm();
                },
                continueWithoutTimingFluids() {
                    this.fluidsAcknowledged = true;
                    this.fluidsWarningOpen = false;
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
            class="ops-estimate-email-form px-3 py-2.5"
        >
            @csrf
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="acknowledge_missing_vin" :value="vinAcknowledged ? 1 : 0">
            <input type="hidden" name="acknowledge_timing_fluids" :value="fluidsAcknowledged ? 1 : 0">
            <div class="ops-estimate-email-form-intro">
                <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Email estimate</p>
                <p class="mt-0.5 text-xs leading-4 text-slate-500">Sends the current estimate PDF, portal review link, and optional note. Moves the RO to awaiting approval when sent from estimate posture.</p>
                @if ($contactPreference === LeadContactPreference::Email)
                    <p class="mt-1 text-xs font-semibold text-sky-800">Customer prefers email for follow-up.</p>
                @elseif ($contactPreference === LeadContactPreference::Call)
                    <p class="mt-1 text-xs font-semibold text-amber-900">Customer prefers a call — email only when needed.</p>
                @elseif ($contactPreference === LeadContactPreference::Text)
                    <p class="mt-1 text-xs font-semibold text-slate-600">Customer prefers text — email only when needed.</p>
                @endif
            </div>

            <div class="ops-estimate-email-form-fields">
                <label class="block">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Recipient</span>
                    <input
                        x-ref="emailInput"
                        type="email"
                        name="email"
                        value="{{ old('email', $customerEmail) }}"
                        @required($customerEmail === '')
                        placeholder="{{ $customerEmail !== '' ? $customerEmail : 'Customer email required' }}"
                        class="mt-1 h-9 w-full rounded-sm border border-slate-300 bg-white px-2.5 text-sm font-semibold text-slate-800"
                        autocomplete="email"
                        @input="cancelConfirm()"
                    >
                </label>

                <label class="block">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Optional note</span>
                    <textarea
                        name="message"
                        rows="2"
                        maxlength="500"
                        placeholder="Short note included in the email body"
                        class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                        @input="cancelConfirm()"
                    >{{ old('message') }}</textarea>
                </label>

                <div
                    x-show="vinWarningOpen"
                    x-cloak
                    class="ops-estimate-vin-warning"
                >
                    <div class="ops-estimate-vin-warning-head">
                        @include('operations.repair-orders.partials.repair-order-vehicle-identity-pressure-chip', ['repairOrder' => $repairOrder])
                        <p class="ops-estimate-vin-warning-title">VIN missing</p>
                    </div>
                    <p class="ops-estimate-vin-warning-copy">
                        Parts lookup, labor guides, service history accuracy, and OEM information may be affected.
                    </p>
                    <div class="ops-estimate-vin-warning-actions">
                        <a href="#ro-identity-band" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary" @click="cancelVinWarning()">Add VIN</a>
                        <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary" @click="continueWithoutVin()">Continue anyway</button>
                    </div>
                </div>

                <div
                    x-show="fluidsWarningOpen"
                    x-cloak
                    class="ops-estimate-vin-warning"
                >
                    <p class="ops-estimate-vin-warning-title">{{ $timingFluids['headline'] ?? 'This job is missing companions the shop usually includes' }}</p>
                    <p class="ops-estimate-vin-warning-copy">
                        {{ $timingFluids['advisor_detail'] ?? 'Add them before the customer sees the estimate, or continue if they are already covered.' }}
                    </p>
                    <div class="ops-estimate-vin-warning-actions">
                        <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary" @click="cancelVinWarning()">Add fluids</button>
                        <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary" @click="continueWithoutTimingFluids()">Continue anyway</button>
                    </div>
                </div>

                <div class="ops-estimate-email-form-actions">
                    <button
                        type="button"
                        x-show="! confirmSend && ! sending"
                        x-on:click="armConfirm()"
                        class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary"
                        @if ($customerEmail === '') title="Enter customer email to send" @endif
                    >
                        Email estimate
                    </button>

                    <button
                        type="submit"
                        x-show="confirmSend && ! sending"
                        x-cloak
                        class="ops-estimate-email-form-btn ops-estimate-email-form-btn--confirm"
                    >
                        Confirm send
                    </button>

                    <button
                        type="button"
                        x-show="confirmSend && ! sending"
                        x-cloak
                        x-on:click="cancelConfirm()"
                        class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary"
                    >
                        Cancel
                    </button>
                </div>

                <p
                    x-show="confirmSend && ! sending"
                    x-cloak
                    class="text-xs font-semibold leading-4 text-amber-900"
                >
                    Confirm to email the PDF and portal link to <span x-text="$refs.emailInput.value || 'this address'"></span>. Resets in 3 seconds.
                </p>

                @if ($errors->has('email'))
                    <p class="text-xs font-semibold text-rose-700">{{ $errors->first('email') }}</p>
                @endif
            </div>
        </form>
    @elseif (! $isTerminal)
        <p class="px-3 py-2 text-xs leading-4 text-slate-500">Add estimate lines before emailing the customer.</p>
    @endif
@endcan
