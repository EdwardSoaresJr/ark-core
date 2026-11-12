@php
    use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;
    use App\Ark\Operations\EstimatePricing\TaxSourcingLocationKind;
    use App\Ark\Operations\Settings\ShopSettings;

    $settings = ShopSettings::current();
    $taxProfiles = collect(ShopTaxCatalog::profilesFromShopSettings($settings))
        ->filter(fn (array $profile): bool => (bool) ($profile['is_active'] ?? true))
        ->values();
    $stamp = is_array($repairOrder->tax_profile) ? $repairOrder->tax_profile : [];
    $selectedKey = (string) ($stamp['profile_key'] ?? '');
    $sourcing = is_array($stamp['sourcing'] ?? null) ? $stamp['sourcing'] : [];
    $locationKind = (string) ($sourcing['location_kind'] ?? TaxSourcingLocationKind::Shop->value);
    $ambiguous = (bool) ($sourcing['matcher_ambiguous'] ?? false);
    $matcherNote = (string) ($sourcing['matcher_note'] ?? '');
    $taxEditable = ! $repairOrder->isTerminal() && blank($repairOrder->closed_at);
@endphp

@if ($settings->tax_enabled && $taxProfiles->isNotEmpty())
    <div class="card" data-tax-jurisdiction-card>
        <div class="card-body vstack gap-2">
            <div class="subheader mb-0">Tax jurisdiction</div>
            <div class="small text-secondary">
                Profile selection is authoritative. City/ZIP associations only suggest candidates.
            </div>
            @if ($matcherNote !== '')
                <div @class(['alert mb-0 small', 'alert-warning' => $ambiguous, 'alert-info' => ! $ambiguous]) role="status">
                    {{ $matcherNote }}
                </div>
            @endif
            @if ($taxEditable)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.tax-profile.store', $repairOrder) }}"
                    class="vstack gap-2"
                    data-refresh-scope="financial"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <label class="form-label" for="ro-tax-profile-{{ $repairOrder->id }}">Tax profile</label>
                    <select id="ro-tax-profile-{{ $repairOrder->id }}" name="tax_profile_key" class="form-select" required>
                        @foreach ($taxProfiles as $profile)
                            <option value="{{ $profile['key'] }}" @selected($selectedKey === (string) $profile['key'])>
                                {{ $profile['name'] }}@if (! empty($profile['is_default'])) (default)@endif
                            </option>
                        @endforeach
                    </select>
                    <label class="form-label" for="ro-tax-sourcing-{{ $repairOrder->id }}">Sourcing location</label>
                    <select id="ro-tax-sourcing-{{ $repairOrder->id }}" name="tax_sourcing_location_kind" class="form-select">
                        @foreach (TaxSourcingLocationKind::cases() as $kind)
                            <option value="{{ $kind->value }}" @selected($locationKind === $kind->value)>{{ $kind->label() }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label" for="ro-tax-service-line1-{{ $repairOrder->id }}">Service address (when used)</label>
                            <input id="ro-tax-service-line1-{{ $repairOrder->id }}" name="service_address_line1" class="form-control" value="{{ old('service_address_line1', $sourcing['address']['line1'] ?? '') }}" placeholder="Street">
                        </div>
                        <div class="col-md-5">
                            <input name="service_address_city" class="form-control" value="{{ old('service_address_city', $sourcing['address']['city'] ?? '') }}" placeholder="City">
                        </div>
                        <div class="col-md-2">
                            <input name="service_address_state" class="form-control" maxlength="2" value="{{ old('service_address_state', $sourcing['address']['state'] ?? '') }}" placeholder="ST">
                        </div>
                        <div class="col-md-2">
                            <input name="service_address_postal_code" class="form-control" value="{{ old('service_address_postal_code', $sourcing['address']['postal_code'] ?? '') }}" placeholder="ZIP">
                        </div>
                        <div class="col-md-3">
                            <input name="service_address_county" class="form-control" value="{{ old('service_address_county', $sourcing['address']['county'] ?? '') }}" placeholder="County">
                        </div>
                    </div>
                    <label class="form-check">
                        <input
                            type="checkbox"
                            name="confirm_jurisdiction"
                            value="1"
                            class="form-check-input"
                            required
                            @checked(old('confirm_jurisdiction'))
                        >
                        <span class="form-check-label">I confirm this tax jurisdiction for this repair order</span>
                    </label>
                    <button type="submit" class="btn btn-outline-primary btn-sm align-self-start">Confirm tax jurisdiction</button>
                </form>
            @else
                <div class="fw-medium">{{ $stamp['profile_name'] ?? $stamp['label'] ?? 'Tax' }}</div>
                <div class="small text-secondary">Frozen on this repair order.</div>
            @endif
        </div>
    </div>
@endif
