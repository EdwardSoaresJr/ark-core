@php
    use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
    use App\Ark\Operations\Settings\ShopSettings;

    $shopSettings = ShopSettings::current();
    $primaryBillingClasses = $shopSettings->primaryBillingClassRows();
    $selectedBillingClass = old('billing_class', $customer->customer_type ?: 'Retail');
    $selectedVisitMode = old('visit_mode', $shopSettings->defaultVisitMode()->value);
    $defaultsVisitSummary = RepairOrderVisitMode::tryFrom($selectedVisitMode)?->label() ?? 'Drop Off';
    $defaultsAccountSummary = collect($primaryBillingClasses)
        ->first(fn (array $row): bool => strcasecmp($row['name'], (string) $selectedBillingClass) === 0)['name']
        ?? $selectedBillingClass;
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Reason for visit</h2>
                    <div class="card-subtitle">What the customer said - not the estimate. Optional now; you can edit it on the RO.</div>
                </div>
            </div>
            <div class="card-body p-2 p-md-3">
                <div class="ark-ro-mention" x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))">
                    <label class="visually-hidden" for="visit_reason">Reason for visit</label>
                    <textarea
                        id="visit_reason"
                        name="visit_reason"
                        x-ref="field"
                        rows="4"
                        placeholder="e.g. Brakes make noise on hard stops. Comeback from @RO1677."
                        @class(['form-control', 'is-invalid' => $errors->has('visit_reason')])
                        @input="onInput()"
                        @keydown="onKeydown($event)"
                    >{{ old('visit_reason', $initialVisitReason ?? '') }}</textarea>
                    <div class="ark-legacy">
                        @include('operations.repair-orders.partials.repair-order-mention-suggest')
                    </div>
                    @error('visit_reason')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <section class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Visit &amp; defaults</h2>
                    <div class="card-subtitle">Visit posture, account class, and internal advisor context.</div>
                </div>
                <div class="card-actions small text-secondary text-nowrap">{{ $defaultsVisitSummary }} · {{ $defaultsAccountSummary }}</div>
            </div>
            <div class="card-body p-2 p-md-3">
                <div class="mb-3">
                    <div class="form-label required">Visit</div>
                    <div class="form-selectgroup" role="radiogroup" aria-label="Visit posture" aria-required="true">
                        @foreach ([
                            'waiting_here' => 'Waiting',
                            'drop_off' => 'Drop Off',
                            'needs_shuttle' => 'Shuttle',
                            'tow_incoming' => 'Tow-In',
                        ] as $value => $label)
                            <label class="form-selectgroup-item">
                                <input
                                    name="visit_mode"
                                    value="{{ $value }}"
                                    type="radio"
                                    class="form-selectgroup-input"
                                    @checked($selectedVisitMode === $value)
                                    @if ($loop->first && $selectedVisitMode === '') required @endif
                                >
                                <span class="form-selectgroup-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('visit_mode')
                        <div class="small text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <div class="form-label">Billing class</div>
                    <div class="form-selectgroup" role="radiogroup" aria-label="Billing class for this visit">
                        @foreach ($primaryBillingClasses as $billingClassRow)
                            <label class="form-selectgroup-item">
                                <input
                                    name="billing_class"
                                    value="{{ $billingClassRow['name'] }}"
                                    type="radio"
                                    class="form-selectgroup-input"
                                    @checked(strcasecmp($selectedBillingClass, $billingClassRow['name']) === 0)
                                    required
                                >
                                <span class="form-selectgroup-label">{{ $billingClassRow['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('billing_class')
                        <div class="small text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 d-grid d-sm-flex justify-content-sm-end">
        <button type="submit" class="btn btn-primary btn-lg">Open Repair Order</button>
    </div>
</div>
