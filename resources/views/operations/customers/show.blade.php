<x-operations.app :title="$customer->name" ui="tabler">
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

        $referralLabel = $customer->referral_source
            ? (\App\Ark\Operations\Encounters\EncounterSource::tryFrom($customer->referral_source)?->label() ?? $customer->referral_source)
            : null;
        $hubCallHref = \App\Ark\Operations\PhoneNumber::telUri($customer->phone);
        $hubTextHref = route('operations.customers.show', $customer).'?compose=text#customer-communication';
        $hubTabs = [
            'work' => ['label' => 'Work', 'count' => $openRepairOrderCount > 0 ? $openRepairOrderCount : null],
            'vehicles' => ['label' => 'Vehicles', 'count' => $customer->vehicles->count()],
            'comms' => ['label' => 'Comms', 'count' => null],
            'visits' => ['label' => 'History', 'count' => $customer->repairOrders->count()],
            'documents' => ['label' => 'Documents', 'count' => ($customerDocuments ?? collect())->isNotEmpty() ? ($customerDocuments ?? collect())->count() : null],
        ];
    @endphp

    <div x-data="arkCustomerHubTabs(@js($initialHubTab))" x-init="init()">
        <x-tabler.page layout="fluid" :title="$customer->name" pretitle="Customer Service Hub" actions-label="Customer commands" stack-actions>
            @can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
                <x-slot:actions>
                    @if ($hubCallHref)
                        <x-tabler.page-action :href="$hubCallHref" icon="phone" label="Call" />
                    @endif
                    @if (filled($customer->phone))
                        <x-tabler.page-action :href="$hubTextHref" icon="message" label="Text" />
                    @endif
                    @if (\App\Ark\Operations\OperationsFeatures::appointmentsEnabled())
                        <x-tabler.page-action
                            :href="\App\Ark\Operations\Appointments\ScheduleUrl::to(array_filter([
                                'customer' => $customer->id,
                                'vehicle' => request()->integer('vehicle') ?: null,
                            ]))"
                            icon="calendar"
                            label="Schedule"
                        />
                    @endif
                    <x-tabler.page-action
                        icon="pencil"
                        label="Edit Customer"
                        x-on:click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-customer', invokeEl: $event.currentTarget } }))"
                    />
                    <x-tabler.page-action :href="route('operations.intake.create', ['customer_id' => $customer->id])" icon="plus" label="Create RO" primary />
                </x-slot:actions>
            @endcan

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            <div class="row g-3">
                <div class="col-12 col-xl-4 col-xxl-3">
                    <div class="row g-3">
                        <div class="col-12 col-md col-xl-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="text-secondary small">
                                        {{ $customer->customer_type ?: 'Retail' }}
                                        @if ($referralLabel)
                                            · Referral: {{ $referralLabel }}
                                        @endif
                                        · {{ $customer->vehicles->count() }} {{ Str::plural('vehicle', $customer->vehicles->count()) }}
                                        · {{ $activeRepairOrders->count() }} active {{ Str::plural('RO', $activeRepairOrders->count()) }}
                                        · {{ $customer->repairOrders->count() }} recent {{ Str::plural('visit', $customer->repairOrders->count()) }}
                                    </div>

                                    <div class="mt-3 d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <x-tabler.icon name="phone" class="text-secondary" />
                                            @if (filled($customer->display_phone))
                                                <span class="fw-bold">{{ $customer->display_phone }}</span>
                                            @else
                                                <span class="text-secondary">No phone</span>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2 text-truncate">
                                            <x-tabler.icon name="mail" class="text-secondary" />
                                            @if ($customer->email)
                                                <a href="mailto:{{ $customer->email }}" class="text-truncate">{{ $customer->email }}</a>
                                            @else
                                                <span class="text-secondary">No email</span>
                                            @endif
                                        </div>
                                        @if (filled($customer->messenger_psid))
                                            <div class="text-secondary small">Messenger linked</div>
                                        @endif
                                    </div>

                                    @if ($customer->contact_preference || $activeRepairOrders->count() > 0)
                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            @if ($customer->contact_preference)
                                                <span class="badge bg-azure-lt">{{ $customer->contact_preference->outreachLabel() }}</span>
                                            @endif
                                            @if ($activeRepairOrders->count() > 0)
                                                <span class="badge bg-orange-lt">{{ $activeRepairOrders->count() }} active {{ Str::plural('RO', $activeRepairOrders->count()) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if ($customer->notes)
                                    <div class="card-body border-top" x-data="{ expanded: false }">
                                        <div class="subheader mb-1">Customer notes</div>
                                        @if (strlen($customer->notes) > 320)
                                            <p class="mb-0" x-show="! expanded">{!! nl2br(e(Str::limit($customer->notes, 320))) !!}</p>
                                            <p class="mb-0" x-show="expanded" x-cloak>{!! nl2br(e($customer->notes)) !!}</p>
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm px-0 mt-1"
                                                x-on:click="expanded = ! expanded"
                                                x-text="expanded ? 'Show less' : 'Show all notes'"
                                            >Show all notes</button>
                                        @else
                                            <p class="mb-0">{!! nl2br(e($customer->notes)) !!}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($callContext->openRepairOrders->isNotEmpty())
                            <div class="col-12 col-md col-xl-12">
                                @include('operations.customers.partials.hub-active-work-strip')
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-12 col-xl-8 col-xxl-9">
                    @if (($operationalJourney ?? null) !== null)
                        @if ($operationalJourneyRepairOrder ?? null)
                            <div class="subheader mb-1">Journey for RO #{{ $operationalJourneyRepairOrder->repair_order_id }}</div>
                        @endif
                        <div class="ark-legacy mb-3">
                            @include('operations.repair-orders.partials.operational-journey-card', [
                                'operationalJourney' => $operationalJourney,
                                'journeyComparison' => $journeyComparison ?? null,
                            ])
                        </div>
                    @endif

                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" aria-label="Customer hub sections">
                                @foreach ($hubTabs as $tabKey => $hubTab)
                                    <li class="nav-item">
                                        <button
                                            type="button"
                                            class="nav-link"
                                            :class="{ active: tab === '{{ $tabKey }}' }"
                                            x-on:click="selectTab('{{ $tabKey }}')"
                                            :aria-current="tab === '{{ $tabKey }}' ? 'page' : false"
                                        >
                                            {{ $hubTab['label'] }}
                                            @if ($hubTab['count'] !== null)
                                                <span class="badge bg-secondary-lt ms-2">{{ $hubTab['count'] }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div x-show="tab === 'work'" x-cloak id="customer-work">
                            @include('operations.customers.partials.hub-tab-work')
                        </div>

                        <div x-show="tab === 'vehicles'">
                            @include('operations.customers.partials.hub-tab-vehicles')
                        </div>

                        <div x-show="tab === 'comms'" x-cloak>
                            @include('operations.customers.partials.hub-tab-comms')
                        </div>

                        <div x-show="tab === 'visits'" x-cloak id="customer-timeline">
                            @if ($timelineEntryCount > 0)
                                <div class="border-bottom">
                                    <button
                                        type="button"
                                        class="btn btn-ghost-secondary w-100 justify-content-start rounded-0"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#customer-operational-timeline"
                                        aria-expanded="true"
                                        aria-controls="customer-operational-timeline"
                                    >
                                        Operational Timeline
                                        <span class="text-secondary ms-2">{{ $timelineEntryCount }} events</span>
                                    </button>
                                    <div class="collapse show" id="customer-operational-timeline">
                                        @include('operations.customers.partials.hub-tab-timeline')
                                    </div>
                                </div>
                            @endif
                            <div class="card-body border-bottom py-2">
                                <h3 class="card-title mb-0">Service History</h3>
                                <div class="text-secondary small">Every repair order for this customer, newest first.</div>
                            </div>
                            @include('operations.customers.partials.hub-tab-visits')
                        </div>

                        <div x-show="tab === 'documents'" x-cloak>
                            @include('operations.customers.partials.hub-tab-documents')
                        </div>
                    </div>
                </div>
            </div>
        </x-tabler.page>
    </div>

    <div class="ark-legacy">
        @include('operations.customers.partials.hub-workspace-modal', [
            'customer' => $customer,
            'customerTypes' => $customerTypes,
            'initialTask' => $hubInitialTask,
            'initialContext' => $hubInitialContext,
        ])
    </div>
</x-operations.app>
