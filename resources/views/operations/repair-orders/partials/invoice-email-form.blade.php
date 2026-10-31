@php
    $customerEmail = trim((string) $repairOrder->customer->email);
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($financial['canEmailInvoice'] ?? false)
        <form
            method="POST"
            action="{{ route('operations.repair-orders.invoice.email', $repairOrder) }}"
            data-refresh-scope="financial"
            data-saving-label="Sending invoice email…"
            class="border rounded p-3 d-flex flex-column gap-2"
            @submit.prevent="window.arkWorksheetFormSubmit($event)"
        >
            @csrf
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

            <div>
                <div class="subheader">Email invoice</div>
                <div class="small text-secondary">
                    Sends the final invoice PDF to the customer
                    @if ($financial['square']['emailPayEnabled'] ?? false)
                        with a secure pay link when balance is due.
                    @endif
                </div>
            </div>

            <label class="visually-hidden" for="invoice-email-{{ $repairOrder->repair_order_id }}">Customer email</label>
            <input
                id="invoice-email-{{ $repairOrder->repair_order_id }}"
                type="email"
                name="email"
                value="{{ old('email', $customerEmail) }}"
                placeholder="Customer email"
                class="form-control"
            >

            <label class="visually-hidden" for="invoice-email-message-{{ $repairOrder->repair_order_id }}">Message</label>
            <input
                id="invoice-email-message-{{ $repairOrder->repair_order_id }}"
                type="text"
                name="message"
                value="{{ old('message') }}"
                placeholder="Optional note"
                class="form-control"
            >

            <button type="submit" class="btn btn-outline-secondary w-100">Email invoice</button>
        </form>
    @endif
@endcan
