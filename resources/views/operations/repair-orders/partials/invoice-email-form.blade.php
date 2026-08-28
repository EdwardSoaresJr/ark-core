@php
    $customerEmail = trim((string) $repairOrder->customer->email);
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($financial['canEmailInvoice'] ?? false)
        <form
            method="POST"
            action="{{ route('operations.repair-orders.invoice.email', $repairOrder) }}"
            data-refresh-scope="rail"
            data-saving-label="Sending invoice email…"
            class="ops-estimate-email-form px-0 py-0"
            @submit.prevent="window.arkWorksheetFormSubmit($event)"
        >
            @csrf
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">

            <div class="grid gap-2 border border-slate-200 bg-white p-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Email invoice</p>
                    <p class="mt-0.5 text-xs leading-4 text-slate-500">
                        Sends the final invoice PDF to the customer
                        @if ($financial['square']['emailPayEnabled'] ?? false)
                            with a secure pay link when balance is due.
                        @endif
                    </p>
                </div>

                <label class="block">
                    <span class="sr-only">Customer email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $customerEmail) }}"
                        placeholder="Customer email"
                        class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-700"
                    >
                </label>

                <label class="block">
                    <span class="sr-only">Message</span>
                    <input
                        type="text"
                        name="message"
                        value="{{ old('message') }}"
                        placeholder="Optional note"
                        class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-700"
                    >
                </label>

                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:border-slate-400">
                    Email invoice
                </button>
            </div>
        </form>
    @endif
@endcan
