<x-operations.app :title="$customer->name">
    @php
        $timelineEntryCount = $vehicleTimelineEntries->sum(fn ($entries) => $entries->count());
        $openRepairOrderCount = $callContext->openRepairOrders->count();
        $hubInitialTask = $hubInitialTask ?? null;
        $hubInitialContext = $hubInitialContext ?? [];

        if ($hubInitialTask === null) {
            if ($errors->hasAny(['vin', 'plate', 'year', 'make', 'model']) && ! old('_vehicle_id')) {
                $hubInitialTask = 'hub-vehicle-create';
            } elseif ($errors->hasAny(['first_name', 'last_name', 'phone', 'email', 'customer_type', 'notes', 'messenger_psid'])) {
                $hubInitialTask = 'hub-customer';
            }
        }
    @endphp

    <section class="ops-hub-layout">
        <div
            class="ops-hub-main space-y-3"
            x-data="arkCustomerHubTabs(@js($initialHubTab))"
            x-init="init()"
        >
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="border-b border-slate-300 pb-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="ops-eyebrow">Customer Service Hub</p>
                        <h1 class="mt-0.5 text-2xl font-black leading-7 tracking-tight text-slate-950">{{ $customer->name }}</h1>
                        <p class="ops-meta mt-1">
                            {{ $customer->customer_type ?: 'Retail' }}
                            @php
                                $referralLabel = $customer->referral_source
                                    ? (\App\Ark\Operations\Encounters\EncounterSource::tryFrom($customer->referral_source)?->label() ?? $customer->referral_source)
                                    : null;
                            @endphp
                            @if ($referralLabel)
                                <span class="mx-1 text-slate-300">·</span>
                                Referral: {{ $referralLabel }}
                            @endif
                            <span class="mx-1 text-slate-300">·</span>
                            {{ $customer->vehicles->count() }} {{ Str::plural('vehicle', $customer->vehicles->count()) }}
                            <span class="mx-1 text-slate-300">·</span>
                            {{ $activeRepairOrders->count() }} active {{ Str::plural('RO', $activeRepairOrders->count()) }}
                            <span class="mx-1 text-slate-300">·</span>
                            {{ $customer->repairOrders->count() }} recent {{ Str::plural('visit', $customer->repairOrders->count()) }}
                        </p>
                    </div>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
                        @php
                            $hubCommandChip = 'min-h-10 rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 sm:min-h-8 inline-flex items-center';
                            $hubCallHref = \App\Ark\Operations\PhoneNumber::telUri($customer->phone);
                            $hubTextHref = route('operations.customers.show', $customer).'?compose=text#customer-communication';
                        @endphp
                        <div class="flex flex-wrap items-center justify-end gap-1.5" aria-label="Customer commands">
                            @if ($hubCallHref)
                                <a href="{{ $hubCallHref }}" class="{{ $hubCommandChip }}">Call</a>
                            @endif
                            @if (filled($customer->phone))
                                <a href="{{ $hubTextHref }}" class="{{ $hubCommandChip }}">Text</a>
                            @endif
                            @if (\App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
                                <a href="{{ \App\Ark\Operations\Appointments\ScheduleUrl::to(array_filter([
                                    'customer' => $customer->id,
                                    'vehicle' => request()->integer('vehicle') ?: null,
                                ])) }}" class="{{ $hubCommandChip }}">
                                    Schedule
                                </a>
                            @endif
                            <a href="{{ route('operations.intake.create', ['customer_id' => $customer->id]) }}" class="min-h-10 rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800 sm:min-h-8 inline-flex items-center">
                                Create RO
                            </a>
                            <button
                                type="button"
                                class="{{ $hubCommandChip }}"
                                title="Open to edit customer"
                                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-customer', invokeEl: $event.currentTarget } }))"
                            >
                                Edit Customer
                            </button>
                        </div>
                    @endcan
                </div>

                <div class="mt-2">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="font-bold text-slate-950">{{ $customer->display_phone ?: 'No phone' }}</span>
                        @if (filled($customer->messenger_psid))
                            <span class="text-slate-600">Messenger linked</span>
                        @endif
                        @if ($customer->email)
                            <a href="mailto:{{ $customer->email }}" class="truncate font-semibold text-slate-700 underline-offset-2 hover:underline">{{ $customer->email }}</a>
                        @else
                            <span class="text-slate-500">No email</span>
                        @endif
                        @if ($customer->contact_preference)
                            <span class="ops-state-pill border-sky-200 bg-sky-50 text-sky-900">{{ $customer->contact_preference->outreachLabel() }}</span>
                        @endif
                        @if ($activeRepairOrders->count() > 0)
                            <span class="ops-state-pill border-amber-300 bg-amber-50 text-amber-800">{{ $activeRepairOrders->count() }} active {{ Str::plural('RO', $activeRepairOrders->count()) }}</span>
                        @endif
                    </div>

                    @if ($customer->notes)
                        <div
                            class="mt-2 border-l-4 border-slate-300 bg-slate-50 px-3 py-2"
                            x-data="{ expanded: false }"
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Customer notes</p>
                            <p
                                class="mt-1 whitespace-pre-line text-sm leading-5 text-slate-700"
                                :class="expanded ? '' : 'line-clamp-6'"
                            >{{ $customer->notes }}</p>
                            @if (strlen($customer->notes) > 320)
                                <button
                                    type="button"
                                    class="mt-1 text-xs font-semibold text-slate-600 underline-offset-2 hover:text-slate-950 hover:underline"
                                    @click="expanded = ! expanded"
                                    x-text="expanded ? 'Show less' : 'Show all notes'"
                                ></button>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @include('operations.customers.partials.hub-active-work-strip')

            @if (($operationalJourney ?? null) !== null)
                <div>
                    @if ($operationalJourneyRepairOrder ?? null)
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                            Journey for RO #{{ $operationalJourneyRepairOrder->repair_order_id }}
                        </p>
                    @endif
                    @include('operations.repair-orders.partials.operational-journey-card', [
                        'operationalJourney' => $operationalJourney,
                        'journeyComparison' => $journeyComparison ?? null,
                    ])
                </div>
            @endif

            <div class="ops-workspace overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <nav class="ops-report-tabs" aria-label="Customer hub sections">
                        <button
                            type="button"
                            :class="tabClass('work')"
                            @click="selectTab('work')"
                            :aria-current="tab === 'work' ? 'page' : false"
                        >
                            Work
                            @if ($openRepairOrderCount > 0)
                                <span class="font-normal opacity-75">· {{ $openRepairOrderCount }}</span>
                            @endif
                        </button>
                        <button
                            type="button"
                            :class="tabClass('vehicles')"
                            @click="selectTab('vehicles')"
                            :aria-current="tab === 'vehicles' ? 'page' : false"
                        >
                            Vehicles
                            <span class="font-normal opacity-75">· {{ $customer->vehicles->count() }}</span>
                        </button>
                        <button
                            type="button"
                            :class="tabClass('comms')"
                            @click="selectTab('comms')"
                            :aria-current="tab === 'comms' ? 'page' : false"
                        >
                            Comms
                        </button>
                        <button
                            type="button"
                            :class="tabClass('visits')"
                            @click="selectTab('visits')"
                            :aria-current="tab === 'visits' ? 'page' : false"
                        >
                            History
                            <span class="font-normal opacity-75">· {{ $customer->repairOrders->count() }}</span>
                        </button>
                        <button
                            type="button"
                            :class="tabClass('documents')"
                            @click="selectTab('documents')"
                            :aria-current="tab === 'documents' ? 'page' : false"
                        >
                            Documents
                            @if (($customerDocuments ?? collect())->isNotEmpty())
                                <span class="font-normal opacity-75">· {{ ($customerDocuments ?? collect())->count() }}</span>
                            @endif
                        </button>
                    </nav>
                </div>

                <div x-show="tab === 'work'" x-cloak id="customer-work" class="scroll-mt-6">
                    @include('operations.customers.partials.hub-tab-work')
                </div>

                <div x-show="tab === 'vehicles'" class="scroll-mt-6">
                    @include('operations.customers.partials.hub-tab-vehicles')
                </div>

                <div
                    x-show="tab === 'comms'"
                    x-cloak
                    class="scroll-mt-6"
                >
                    @include('operations.customers.partials.hub-tab-comms')
                </div>

                <div x-show="tab === 'visits'" x-cloak id="customer-timeline" class="scroll-mt-6">
                    @if ($timelineEntryCount > 0)
                        <details class="border-b border-slate-100" open>
                            <summary class="cursor-pointer px-3 py-2 text-xs font-bold uppercase tracking-[0.08em] text-slate-600 hover:bg-slate-50">
                                Operational Timeline
                                <span class="font-normal normal-case tracking-normal text-slate-400">· {{ $timelineEntryCount }} events</span>
                            </summary>
                            @include('operations.customers.partials.hub-tab-timeline')
                        </details>
                    @endif
                    <div class="border-b border-slate-100 px-3 py-2">
                        <p class="ops-eyebrow">Service History</p>
                        <p class="ops-meta mt-0.5">Every repair order for this customer, newest first.</p>
                    </div>
                    @include('operations.customers.partials.hub-tab-visits')
                </div>

                <div x-show="tab === 'documents'" x-cloak class="scroll-mt-6">
                    @include('operations.customers.partials.hub-tab-documents')
                </div>
            </div>
        </div>

        @include('operations.customers.partials.hub-workspace-modal', [
            'customer' => $customer,
            'customerTypes' => $customerTypes,
            'initialTask' => $hubInitialTask,
            'initialContext' => $hubInitialContext,
        ])
    </section>
</x-operations.app>
