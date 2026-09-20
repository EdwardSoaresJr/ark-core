{{-- RO identity authoring — customer/vehicle/mileage remain available after close; visit posture is open-RO only. --}}
@php
    use App\Ark\Operations\Settings\ShopSettings;

    $isTerminal = $isTerminal ?? false;
    $identityCustomer = $repairOrder->customer;
    $identityVehicle = $repairOrder->vehicle;
    $identityCustomerTypes = ShopSettings::current()->customerTypeRows();
    $visitModes = App\Ark\Operations\RepairOrders\RepairOrderVisitMode::cases();
    $currentVisitMode = App\Ark\Operations\RepairOrders\RepairOrderVisitMode::fromRepairOrder($repairOrder);
@endphp

<div class="ops-workspace-modal__panel" x-show="task === 'customer-identity'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.customers.update', $identityCustomer) }}"
        data-workspace-modal-form="customer-identity"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
        class="space-y-3"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="repair_order_id" value="{{ $repairOrder->repair_order_id }}">
        <input type="hidden" name="notes" value="{{ old('notes', $identityCustomer->notes ?? '') }}">
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="block text-[11px] font-medium text-slate-500">
                First name
                <input name="first_name" value="{{ old('first_name', $identityCustomer->first_name) }}" required class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            </label>
            <label class="block text-[11px] font-medium text-slate-500">
                Last name
                <input name="last_name" value="{{ old('last_name', $identityCustomer->last_name) }}" required class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            </label>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="block text-[11px] font-medium text-slate-500">
                Billing class
                <select name="customer_type" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" aria-label="Billing class">
                    @foreach ($identityCustomerTypes as $type)
                        <option value="{{ $type['name'] }}" @selected(old('customer_type', $identityCustomer->customer_type ?: 'Retail') === $type['name'])>{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-[11px] font-medium text-slate-500">
                Preferred contact
                <select name="contact_preference" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" aria-label="Preferred contact">
                    <option value="">—</option>
                    <option value="text" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'text')>Text</option>
                    <option value="call" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'call')>Call</option>
                    <option value="email" @selected(old('contact_preference', $identityCustomer->contact_preference?->value) === 'email')>Email</option>
                </select>
            </label>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="block text-[11px] font-medium text-slate-500">
                Phone
                <input name="phone" value="{{ old('phone', $identityCustomer->phone) }}" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            </label>
            <label class="block text-[11px] font-medium text-slate-500">
                Email
                <input type="email" name="email" value="{{ old('email', $identityCustomer->email) }}" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            </label>
        </div>
        <label class="block text-[11px] font-medium text-slate-500">
            Address
            <input name="address_line_1" value="{{ old('address_line_1', $identityCustomer->address_line_1) }}" class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
        </label>
        <input name="address_line_2" value="{{ old('address_line_2', $identityCustomer->address_line_2) }}" placeholder="Apt / unit" class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
        <div class="grid gap-2 sm:grid-cols-[1fr_4rem_5rem]">
            <input name="city" value="{{ old('city', $identityCustomer->city) }}" placeholder="City" class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            <input name="state" value="{{ old('state', $identityCustomer->state) }}" placeholder="ST" maxlength="32" class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
            <input name="postal_code" value="{{ old('postal_code', $identityCustomer->postal_code) }}" placeholder="ZIP" maxlength="16" class="w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950" />
        </div>
    </form>
</div>

<div class="ops-workspace-modal__panel" x-show="task === 'vehicle-identity'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.customers.vehicles.update', [$identityCustomer, $identityVehicle]) }}"
        data-workspace-modal-form="vehicle-identity"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
        class="space-y-3"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="repair_order_id" value="{{ $repairOrder->repair_order_id }}">
        <div
            class="space-y-3"
            x-data="arkVehicleDecode(@js([
                'decodeUrl' => route('operations.vehicles.decode-vin'),
                'csrfToken' => csrf_token(),
            ]))"
        >
            @include('operations.vehicles.partials.workspace-vehicle-fields', [
                'vehicle' => $identityVehicle,
            ])
        </div>
    </form>
</div>

@unless ($isTerminal)
<div class="ops-workspace-modal__panel" x-show="task === 'visit-posture'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.repair-orders.visit-posture.update', $repairOrder) }}"
        data-workspace-modal-form="visit-posture"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
        class="space-y-3"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <label class="block text-[11px] font-medium text-slate-500" for="workspace-visit-posture">
            Visit type
            <select
                id="workspace-visit-posture"
                name="visit_mode"
                required
                class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
            >
                <option value="" disabled @selected($currentVisitMode === null)>Choose visit type</option>
                @foreach ($visitModes as $mode)
                    <option value="{{ $mode->value }}" @selected($currentVisitMode === $mode)>{{ $mode->label() }}</option>
                @endforeach
            </select>
        </label>
    </form>
</div>
@endunless

<div class="ops-workspace-modal__panel" x-show="task === 'mileage'" x-cloak>
    <form
        method="POST"
        action="{{ route('operations.repair-orders.mileage.update', $repairOrder) }}"
        data-workspace-modal-form="mileage"
        data-refresh-scope="worksheet"
        data-saving-label="Saving…"
        @submit.prevent="submitWorksheetForm($event)"
        class="space-y-3"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-[11px] font-medium text-slate-500">
                Mileage in
                <input
                    name="mileage_in"
                    value="{{ old('mileage_in', $repairOrder->mileage_in) }}"
                    inputmode="numeric"
                    class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                    @if ($repairOrder->mileage_in === null && $repairOrder->resolvedMileageIn())
                        placeholder="{{ number_format($repairOrder->resolvedMileageIn()) }}"
                    @endif
                />
            </label>
            <label class="block text-[11px] font-medium text-slate-500">
                Mileage out
                <input
                    name="mileage_out"
                    value="{{ old('mileage_out', $repairOrder->mileage_out) }}"
                    inputmode="numeric"
                    class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950"
                />
            </label>
        </div>
    </form>
</div>
