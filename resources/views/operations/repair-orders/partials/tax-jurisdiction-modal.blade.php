@php
    use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;
    use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
    use App\Ark\Operations\Settings\ShopSettings;
    use App\Ark\Runtime\Authorization\ArkCapability;

    $settings = ShopSettings::current();
    $taxProfiles = collect(ShopTaxCatalog::profilesFromShopSettings($settings))
        ->filter(fn (array $profile): bool => (bool) ($profile['is_active'] ?? true))
        ->values();
    $stamp = is_array($repairOrder->tax_profile) ? $repairOrder->tax_profile : [];
    $selectedKey = (string) old('tax_profile_key', $stamp['profile_key'] ?? '');
    $sourcing = is_array($stamp['sourcing'] ?? null) ? $stamp['sourcing'] : [];
    $locationKind = (string) old('tax_sourcing_location_kind', $sourcing['location_kind'] ?? TaxSourcingLocationKind::Shop->value);
    $ambiguous = (bool) ($sourcing['matcher_ambiguous'] ?? false);
    $matcherNote = (string) ($sourcing['matcher_note'] ?? '');
    $taxEditable = ! $repairOrder->isTerminal()
        && blank($repairOrder->closed_at)
        && (auth()->user()?->can(ArkCapability::RepairOrdersManage->value) ?? false);
    $taxModalAvailable = $settings->tax_enabled && $taxProfiles->isNotEmpty() && $taxEditable;
    $needsAddress = in_array($locationKind, [
        TaxSourcingLocationKind::ServiceAddress->value,
        TaxSourcingLocationKind::Manual->value,
    ], true);
    $taxFieldErrors = collect([
        'tax_profile_key',
        'tax_sourcing_location_kind',
        'service_address_line1',
        'service_address_city',
        'service_address_state',
        'service_address_postal_code',
        'service_address_county',
        'confirm_jurisdiction',
        App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD,
    ])->filter(fn (string $field): bool => $errors->has($field));
    $openTaxModalOnLoad = $taxModalAvailable && $taxFieldErrors->isNotEmpty();
@endphp

@if ($taxModalAvailable)
    <div
        class="modal modal-blur"
        id="ro-tax-jurisdiction-modal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="ro-tax-jurisdiction-title"
        aria-modal="true"
        data-tax-jurisdiction-modal
        x-data="{
            open: @js($openTaxModalOnLoad),
            sourcing: @js($locationKind),
            needsAddress() {
                return this.sourcing === 'service_address' || this.sourcing === 'manual';
            },
            show() { this.open = true; $nextTick(() => this.$refs.dialog?.focus()); },
            hide() { this.open = false; },
        }"
        x-show="open"
        x-cloak
        :class="{ 'd-block': open }"
        @keydown.escape.window="if (open) hide()"
        @ark-open-tax-jurisdiction.window="show()"
    >
        <div class="modal-backdrop show" @click="hide()"></div>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div
                class="modal-content"
                x-ref="dialog"
                tabindex="-1"
            >
                <div class="modal-header">
                    <h2 class="modal-title" id="ro-tax-jurisdiction-title">Tax jurisdiction</h2>
                    <button type="button" class="btn-close" aria-label="Close" @click="hide()"></button>
                </div>
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.tax-profile.store', $repairOrder) }}"
                    data-refresh-scope="financial"
                    data-tax-jurisdiction-form
                    @submit.prevent="
                        const root = $el.closest('[data-worksheet-root]');
                        const worksheet = root && (window.Alpine?.$data?.(root) ?? root._x_dataStack?.[0]);
                        if (typeof worksheet?.submitWorksheetForm === 'function') {
                            worksheet.submitWorksheetForm($event);
                        } else {
                            $event.target.submit();
                        }
                    "
                >
                    <div class="modal-body vstack gap-3">
                        <p class="small text-secondary mb-0">
                            Profile selection is authoritative. City/ZIP associations only suggest candidates.
                        </p>
                        @if ($matcherNote !== '')
                            <div @class(['alert mb-0 small', 'alert-warning' => $ambiguous, 'alert-info' => ! $ambiguous]) role="status">
                                {{ $matcherNote }}
                            </div>
                        @endif

                        @csrf
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ old(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD, $estimateVersion) }}">

                        @foreach ($taxFieldErrors as $field)
                            @foreach ($errors->get($field) as $message)
                                <div class="invalid-feedback d-block" data-tax-jurisdiction-error="{{ $field }}">{{ $message }}</div>
                            @endforeach
                        @endforeach

                        <div>
                            <label class="form-label" for="ro-tax-profile-{{ $repairOrder->id }}">Tax profile</label>
                            <select id="ro-tax-profile-{{ $repairOrder->id }}" name="tax_profile_key" class="form-select @error('tax_profile_key') is-invalid @enderror" required>
                                @foreach ($taxProfiles as $profile)
                                    <option value="{{ $profile['key'] }}" @selected($selectedKey === (string) $profile['key'])>
                                        {{ $profile['name'] }}@if (! empty($profile['is_default'])) (default)@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="ro-tax-sourcing-{{ $repairOrder->id }}">Sourcing location</label>
                            <select
                                id="ro-tax-sourcing-{{ $repairOrder->id }}"
                                name="tax_sourcing_location_kind"
                                class="form-select @error('tax_sourcing_location_kind') is-invalid @enderror"
                                x-model="sourcing"
                            >
                                @foreach (TaxSourcingLocationKind::cases() as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2" x-show="needsAddress()" x-cloak data-tax-jurisdiction-address>
                            <div class="col-12">
                                <label class="form-label" for="ro-tax-service-line1-{{ $repairOrder->id }}">Service address</label>
                                <input id="ro-tax-service-line1-{{ $repairOrder->id }}" name="service_address_line1" class="form-control" value="{{ old('service_address_line1', $sourcing['address']['line1'] ?? '') }}" placeholder="Street">
                            </div>
                            <div class="col-md-5">
                                <input name="service_address_city" class="form-control" value="{{ old('service_address_city', $sourcing['address']['city'] ?? '') }}" placeholder="City" aria-label="City">
                            </div>
                            <div class="col-md-2">
                                <input name="service_address_state" class="form-control" maxlength="2" value="{{ old('service_address_state', $sourcing['address']['state'] ?? '') }}" placeholder="ST" aria-label="State">
                            </div>
                            <div class="col-md-2">
                                <input name="service_address_postal_code" class="form-control" value="{{ old('service_address_postal_code', $sourcing['address']['postal_code'] ?? '') }}" placeholder="ZIP" aria-label="ZIP">
                            </div>
                            <div class="col-md-3">
                                <input name="service_address_county" class="form-control" value="{{ old('service_address_county', $sourcing['address']['county'] ?? '') }}" placeholder="County" aria-label="County">
                            </div>
                        </div>

                        <label class="form-check">
                            <input
                                type="checkbox"
                                name="confirm_jurisdiction"
                                value="1"
                                class="form-check-input @error('confirm_jurisdiction') is-invalid @enderror"
                                required
                                @checked(old('confirm_jurisdiction'))
                            >
                            <span class="form-check-label">I confirm this tax jurisdiction for this repair order</span>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost-secondary" data-tax-jurisdiction-cancel @click="hide()">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-tax-jurisdiction-save>Save tax jurisdiction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
