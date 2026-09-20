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

<section class="ops-intake-open-stack">
    <div class="ops-intake-open-columns">
        <div class="ops-intake-open-panel ops-intake-open-panel--hero">
            <div class="ops-intake-open-panel-head">
                <div class="min-w-0">
                    <h2 class="ops-intake-open-panel-title">Reason for visit</h2>
                    <p class="ops-intake-open-panel-lead">What the customer said — not the estimate. Optional now; you can edit it on the RO.</p>
                </div>
            </div>

            <div
                class="ops-intake-open-field ark-ro-mention"
                x-data="arkRoMention(@js(($priorVisitMentions['suggestions'] ?? [])))"
            >
                <label class="sr-only" for="visit_reason">Reason for visit</label>
                <textarea
                    id="visit_reason"
                    name="visit_reason"
                    x-ref="field"
                    rows="4"
                    placeholder="e.g. Brakes make noise on hard stops. Comeback from @RO1677."
                    class="ops-intake-control ops-intake-why-here-states"
                    @input="onInput()"
                    @keydown="onKeydown($event)"
                >{{ old('visit_reason', $initialVisitReason ?? '') }}</textarea>
                @include('operations.repair-orders.partials.repair-order-mention-suggest')
                @error('visit_reason')
                    <p class="text-sm font-semibold text-rose-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <section class="ops-intake-open-panel">
            <div class="ops-intake-open-panel-head">
                <div class="min-w-0">
                    <h2 class="ops-intake-open-panel-title">Visit &amp; defaults</h2>
                    <p class="ops-intake-open-panel-lead">Visit posture, account class, and internal advisor context.</p>
                </div>
                <span class="ops-intake-open-panel-meta">{{ $defaultsVisitSummary }} · {{ $defaultsAccountSummary }}</span>
            </div>

            <div class="ops-intake-open-panel-body">
                <div class="ops-intake-open-field">
                    <span class="ops-intake-open-field-label">Visit <span class="text-rose-600">*</span></span>
                    <div class="ops-intake-flags-row" role="radiogroup" aria-label="Visit posture" aria-required="true">
                        @foreach ([
                            'waiting_here' => 'Waiting',
                            'drop_off' => 'Drop Off',
                            'needs_shuttle' => 'Shuttle',
                            'tow_incoming' => 'Tow-In',
                        ] as $value => $label)
                            <label class="ops-intake-flag ops-intake-flag--compact ops-intake-flag--radio">
                                <input
                                    name="visit_mode"
                                    value="{{ $value }}"
                                    type="radio"
                                    @checked($selectedVisitMode === $value)
                                    @if ($loop->first && $selectedVisitMode === '') required @endif
                                >
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('visit_mode')
                        <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ops-intake-open-field">
                    <span class="ops-intake-open-field-label">Billing class</span>
                    <div class="ops-intake-flags-row" role="radiogroup" aria-label="Billing class for this visit">
                        @foreach ($primaryBillingClasses as $billingClassRow)
                            <label class="ops-intake-flag ops-intake-flag--compact ops-intake-flag--radio">
                                <input
                                    name="billing_class"
                                    value="{{ $billingClassRow['name'] }}"
                                    type="radio"
                                    @checked(strcasecmp($selectedBillingClass, $billingClassRow['name']) === 0)
                                    required
                                >
                                {{ $billingClassRow['name'] }}
                            </label>
                        @endforeach
                    </div>
                    @error('billing_class')
                        <p class="text-sm font-semibold text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>
    </div>

    <div class="ops-intake-submit">
        <button type="submit" class="ops-index-btn ops-index-btn--primary ops-intake-submit-btn">
            Open Repair Order
        </button>
    </div>
</section>
