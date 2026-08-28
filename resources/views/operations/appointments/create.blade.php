<x-operations.app title="Schedule appointment">
    <section class="ops-index mx-auto max-w-2xl space-y-3">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <div>
                    <h2 class="text-base font-black text-slate-950">Schedule appointment</h2>
                    <p class="mt-1 text-xs text-slate-500">Customer first — vehicle is optional until they arrive. Day, time, and length are checked when you save.</p>
                </div>
                <a href="{{ route('operations.appointments.index') }}" class="ops-page-link">Back to schedule</a>
            </div>
        </div>

        @if (session('status'))
            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($customer === null)
            <div class="ops-board-shell space-y-3 p-3">
                <div>
                    @if (($scheduleContext->needsCustomerIdentification ?? false))
                        <p class="text-sm font-bold text-slate-950">Identify the customer first</p>
                        <p class="mt-0.5 text-xs text-slate-600">This conversation isn’t linked yet — search and pick the customer, then finish scheduling. ARK will not guess.</p>
                    @else
                        <p class="text-sm font-bold text-slate-950">Find the customer first</p>
                        <p class="mt-0.5 text-xs text-slate-600">Search by name, phone, email, plate, or VIN — then finish scheduling.</p>
                    @endif
                </div>
                <form method="GET" action="{{ route('operations.schedule') }}" class="flex flex-wrap gap-2">
                    @foreach (request()->except('q') as $key => $value)
                        @if (filled($value) && ! is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input
                        type="search"
                        name="q"
                        value="{{ $searchQuery }}"
                        required
                        autofocus
                        placeholder="Name, phone, plate…"
                        class="h-9 min-w-[12rem] flex-1 rounded-sm border border-slate-300 bg-white px-2 text-sm"
                    >
                    <button type="submit" class="h-9 border border-slate-300 bg-white px-3 text-xs font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-50">Search</button>
                </form>
                @if ($searchQuery !== '')
                    @if ($searchCustomers->isEmpty())
                        <div class="space-y-3 border border-slate-200 bg-white p-3">
                            <p class="text-xs text-slate-600">No match. Add the customer and put them on the schedule — vehicle can wait until they arrive.</p>
                            <form method="POST" action="{{ route('operations.customers.store') }}" class="grid gap-2 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="return_to" value="schedule">
                                @foreach (($scheduleSearchPreserve ?? []) as $key => $value)
                                    @if (filled($value) && ! is_array($value))
                                        <input type="hidden" name="schedule_{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <label class="block sm:col-span-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">First name</span>
                                    <input type="text" name="first_name" required value="{{ old('first_name') }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm" autofocus>
                                </label>
                                <label class="block sm:col-span-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last name</span>
                                    <input type="text" name="last_name" required value="{{ old('last_name') }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Phone</span>
                                    <input type="tel" name="phone" required value="{{ old('phone', $searchQuery) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                                </label>
                                <div class="sm:col-span-2 flex flex-wrap items-center gap-2">
                                    <button type="submit" class="h-9 border border-slate-800 bg-slate-900 px-3 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">Add &amp; schedule</button>
                                    <a href="{{ route('operations.intake.create') }}" class="text-xs font-semibold text-ops-accent-900 hover:underline">Or Check In with a vehicle</a>
                                </div>
                            </form>
                        </div>
                    @else
                        <ul class="divide-y divide-slate-200 border border-slate-200 bg-white">
                            @foreach ($searchCustomers as $match)
                                <li>
                                    <a
                                        href="{{ \App\Ark\Operations\Appointments\ScheduleUrl::to(array_merge($scheduleSearchPreserve ?? [], [
                                            'customer' => $match->id,
                                        ])) }}"
                                        class="block px-3 py-2 hover:bg-slate-50"
                                    >
                                        <p class="text-sm font-bold text-slate-950">{{ $match->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $match->display_phone ?: 'No phone' }}@if ($match->email) · {{ $match->email }}@endif</p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('operations.appointments.store') }}" class="ops-board-shell space-y-3 p-3">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-sm font-bold text-slate-950">{{ $customer->name }}</p>
                    @if (filled($customer->display_phone))
                        <span class="text-xs font-semibold text-slate-600">{{ $customer->display_phone }}</span>
                    @endif
                    <a href="{{ route('operations.customers.show', $customer) }}" class="text-xs font-semibold text-ops-accent-900 hover:underline">Customer hub</a>
                    @if ($repairOrder ?? null)
                        <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="text-xs font-semibold text-ops-accent-900 hover:underline">RO #{{ $repairOrder->repair_order_id }}</a>
                    @endif
                </div>

                @if ($repairOrder ?? null)
                    <input type="hidden" name="repair_order_id" value="{{ $repairOrder->id }}">
                @endif

                <x-operations.appointment-datetime-fields
                    :starts-at="$defaultStartsAt"
                    :ends-at="$defaultEndsAt"
                    :duration-minutes="$defaultDurationMinutes ?? null"
                    :slot-minutes="$slotMinutes"
                />

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Vehicle</span>
                    <select name="vehicle_id" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                        <option value="" @selected(old('vehicle_id', $selectedVehicleId) === null || old('vehicle_id', $selectedVehicleId) === '')>
                            Vehicle not set yet
                        </option>
                        @foreach ($customer->vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $selectedVehicleId) === (string) $vehicle->id)>
                                {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-0.5 block text-[10px] text-slate-500">Optional — leave unset until they arrive if the vehicle isn’t known yet.</span>
                </label>

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Concern</span>
                    <textarea name="concern" rows="2" required maxlength="1000" placeholder="What is the customer coming in for?" class="mt-0.5 w-full rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-sm">{{ old('concern', $defaultConcern ?? '') }}</textarea>
                </label>

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Notes</span>
                    <textarea name="notes" rows="2" maxlength="2000" class="mt-0.5 w-full rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-sm">{{ old('notes') }}</textarea>
                </label>

                <div class="border-t border-slate-200 pt-2.5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Advisor</span>
                            <select name="advisor_user_id" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                                <option value="">—</option>
                                @foreach ($advisors as $advisor)
                                    <option value="{{ $advisor->id }}" @selected(old('advisor_user_id', $defaultAdvisorId) == $advisor->id)>{{ $advisor->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Scheduled work</span>
                            <input type="text" inputmode="decimal" name="estimated_labor_hours" value="{{ old('estimated_labor_hours') }}" placeholder="2.5" data-numeric-only class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm @error('estimated_labor_hours') border-rose-400 @enderror">
                            <span class="mt-0.5 block text-[10px] text-slate-500">labor hours — used for daily shop capacity</span>
                            @error('estimated_labor_hours')
                                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>
                </div>

                @unless ($repairOrder ?? null)
                    <label class="block sm:max-w-[12rem]">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">RO # (optional)</span>
                        <input type="number" name="repair_order_shop_number" value="{{ old('repair_order_shop_number') }}" min="1" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                    </label>
                @endunless

                <button type="submit" class="h-9 rounded-sm border border-slate-800 bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">Schedule</button>
            </form>
        @endif
    </section>
</x-operations.app>
