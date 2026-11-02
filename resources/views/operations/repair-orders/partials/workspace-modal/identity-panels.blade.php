{{-- RO identity authoring - customer/vehicle/mileage remain available after close; visit posture is open-RO only. --}}
@php
    use App\Ark\Operations\Settings\ShopSettings;

    $isTerminal = $isTerminal ?? false;
    $identityCustomer = $repairOrder->customer;
    $identityVehicle = $repairOrder->vehicle;
    $identityCustomerTypes = ShopSettings::current()->customerTypeRows();
    $visitModes = App\Ark\Operations\RepairOrders\RepairOrderVisitMode::cases();
    $currentVisitMode = App\Ark\Operations\RepairOrders\RepairOrderVisitMode::fromRepairOrder($repairOrder);
@endphp

<div data-workspace-modal-panel x-show="task === 'customer-identity'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.customers.update', $identityCustomer) }}"
        data-workspace-modal-form="customer-identity"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="repair_order_id" value="{{ $repairOrder->repair_order_id }}">
        <input type="hidden" name="notes" value="{{ old('notes', $identityCustomer->notes ?? '') }}">
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-first-name">First name</label>
                <input id="workspace-customer-first-name" name="first_name" value="{{ old('first_name', $identityCustomer->first_name) }}" required class="form-control" />
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-last-name">Last name</label>
                <input id="workspace-customer-last-name" name="last_name" value="{{ old('last_name', $identityCustomer->last_name) }}" required class="form-control" />
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-type">Billing class</label>
                <select id="workspace-customer-type" name="customer_type" class="form-select">
                    @foreach ($identityCustomerTypes as $type)
                        <option value="{{ $type['name'] }}" @selected(old('customer_type', $identityCustomer->customer_type ?: 'Retail') === $type['name'])>{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-contact-preference">Preferred contact</label>
                <select id="workspace-customer-contact-preference" name="contact_preference" class="form-select">
                    <option value="">-</option>
                    <option value="text" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'text')>Text</option>
                    <option value="call" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'call')>Call</option>
                    <option value="email" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'email')>Email</option>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-phone">Phone</label>
                <input id="workspace-customer-phone" name="phone" value="{{ old('phone', $identityCustomer->phone) }}" class="form-control" />
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-customer-email">Email</label>
                <input id="workspace-customer-email" type="email" name="email" value="{{ old('email', $identityCustomer->email) }}" class="form-control" />
            </div>
            <div class="col-12">
                <label class="form-label" for="workspace-customer-address-1">Address</label>
                <input id="workspace-customer-address-1" name="address_line_1" value="{{ old('address_line_1', $identityCustomer->address_line_1) }}" class="form-control" />
            </div>
            <div class="col-12">
                <input name="address_line_2" value="{{ old('address_line_2', $identityCustomer->address_line_2) }}" placeholder="Apt / unit" aria-label="Apt / unit" class="form-control" />
            </div>
            <div class="col-12 col-sm-6">
                <input name="city" value="{{ old('city', $identityCustomer->city) }}" placeholder="City" aria-label="City" class="form-control" />
            </div>
            <div class="col-6 col-sm-3">
                <input name="state" value="{{ old('state', $identityCustomer->state) }}" placeholder="ST" maxlength="32" aria-label="State" class="form-control" />
            </div>
            <div class="col-6 col-sm-3">
                <input name="postal_code" value="{{ old('postal_code', $identityCustomer->postal_code) }}" placeholder="ZIP" maxlength="16" aria-label="ZIP" class="form-control" />
            </div>
        </div>
    </form>
</div>

<div data-workspace-modal-panel x-show="task === 'vehicle-identity'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.customers.vehicles.update', [$identityCustomer, $identityVehicle]) }}"
        data-workspace-modal-form="vehicle-identity"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="repair_order_id" value="{{ $repairOrder->repair_order_id }}">
        <div
            x-data="arkVehicleDecode(@js([
                'decodeUrl' => route('operations.vehicles.decode-vin'),
                'csrfToken' => csrf_token(),
            ]))"
        >
            @include('operations.vehicles.partials.workspace-vehicle-fields-tabler', [
                'vehicle' => $identityVehicle,
                'idPrefix' => 'workspace-vehicle',
            ])
        </div>
    </form>
</div>

@unless ($isTerminal)
<div data-workspace-modal-panel x-show="task === 'visit-posture'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.repair-orders.visit-posture.update', $repairOrder) }}"
        data-workspace-modal-form="visit-posture"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <label class="form-label" for="workspace-visit-posture">Visit type</label>
        <select
            id="workspace-visit-posture"
            name="visit_mode"
            required
            class="form-select"
        >
            <option value="" disabled @selected($currentVisitMode === null)>Choose visit type</option>
            @foreach ($visitModes as $mode)
                <option value="{{ $mode->value }}" @selected($currentVisitMode === $mode)>{{ $mode->label() }}</option>
            @endforeach
        </select>
    </form>
</div>
@endunless

<div data-workspace-modal-panel x-show="task === 'mileage'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.repair-orders.mileage.update', $repairOrder) }}"
        data-workspace-modal-form="mileage"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-mileage-in">Mileage in</label>
                <input
                    id="workspace-mileage-in"
                    name="mileage_in"
                    value="{{ old('mileage_in', $repairOrder->mileage_in) }}"
                    inputmode="numeric"
                    class="form-control"
                    @if ($repairOrder->mileage_in === null && $repairOrder->resolvedMileageIn())
                        placeholder="{{ number_format($repairOrder->resolvedMileageIn()) }}"
                    @endif
                />
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="workspace-mileage-out">Mileage out</label>
                <input
                    id="workspace-mileage-out"
                    name="mileage_out"
                    value="{{ old('mileage_out', $repairOrder->mileage_out) }}"
                    inputmode="numeric"
                    class="form-control"
                />
            </div>
        </div>
    </form>
</div>
