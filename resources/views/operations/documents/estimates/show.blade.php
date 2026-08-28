<x-operations.app title="RO #{{ $snapshot['repair_order']['repair_order_id'] }} Estimate">
    @php
        $shopName = $snapshot['shop']['name'] ?: config('app.name');
        $shopInitial = mb_strtoupper(mb_substr($shopName, 0, 1));
        $shopCityLine = trim(collect([$snapshot['shop']['city'], $snapshot['shop']['state'], $snapshot['shop']['postal_code']])->filter()->implode(' '));
        $isTerminal = $repairOrder->isTerminal();
        $snapshotConcerns = collect($snapshot['concerns']);
        $approvedCount = $snapshotConcerns->where('disposition', 'approved')->count();
        $deferredCount = $snapshotConcerns->where('disposition', 'deferred')->count();
        $declinedCount = $snapshotConcerns->where('disposition', 'declined')->count();
        $recommendedCount = $snapshotConcerns->where('disposition', 'recommended')->count();
        $staff = $snapshot['staff'] ?? [];
        $staffExecution = $staff['execution'] ?? [
            'technician_name' => $snapshot['repair_order']['assigned_technician_name'] ?? 'Unassigned tech',
            'posture' => $snapshot['repair_order']['execution_posture'] ?? 'Execution posture not recorded',
            'next_action' => $snapshot['repair_order']['execution_next_action'] ?? 'Review repair order status',
        ];
        $approvalEvents = collect($staff['approval_events'] ?? $snapshot['approval_events'] ?? []);
        $staffCommunications = collect($staff['communications'] ?? $snapshot['communications'] ?? []);
        $staffTimeline = collect($staff['timeline'] ?? $snapshot['timeline'] ?? []);
        $staffCommunication = $staff['communication'] ?? [];
        $staffCommunicationPosture = $staffCommunication['posture']
            ?? $snapshot['repair_order']['communication_posture']
            ?? 'No customer communication logged';
        $staffCommunicationNextAction = $staffCommunication['next_action']
            ?? $snapshot['repair_order']['communication_next_action']
            ?? 'No communication action pending';
        $lastApprovalEvent = $approvalEvents->first();
        $nextAction = match (true) {
            ($snapshot['repair_order']['status'] ?? null) === 'ready_pickup' && ($snapshot['repair_order']['payment_status'] ?? 'unpaid') === 'unpaid' => 'Collect balance before vehicle release',
            ($snapshot['repair_order']['status'] ?? null) === 'ready_pickup' && ($snapshot['repair_order']['payment_status'] ?? 'unpaid') === 'paid' => 'Vehicle ready for release',
            ($snapshot['repair_order']['status'] ?? null) === 'closed' => 'Operationally closed',
            in_array(($snapshot['repair_order']['status'] ?? null), ['waiting_approval', 'awaiting_approval'], true) => 'Waiting customer authorization',
            $approvedCount > 0 => 'Approved work can move forward',
            $recommendedCount > 0 => 'Authorization needed before production',
            $deferredCount > 0 => 'Deferred work retained for follow-up',
            $declinedCount > 0 => 'Declined work recorded — no follow-up',
            default => 'No authorization gate recorded',
        };
    @endphp

    <section class="space-y-4">
        <div class="flex flex-wrap gap-2 print:hidden">
            <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                Back to RO Review
            </a>

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                @unless ($isTerminal)
                    <a href="{{ route('operations.repair-orders.show', $repairOrder) }}" class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                        Edit Estimate
                    </a>
                @endunless
            @endcan

            <a
                href="{{ route('operations.repair-orders.estimate.pdf', $repairOrder) }}"
                target="_blank"
                rel="noopener"
                class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                @click="($event.currentTarget.href = '{{ route('operations.repair-orders.estimate.pdf', $repairOrder) }}?t=' + Date.now())"
            >
                View PDF
            </a>
            <a
                href="{{ route('operations.repair-orders.estimate.pdf.download', $repairOrder) }}"
                class="inline-flex min-h-9 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                @click="($event.currentTarget.href = '{{ route('operations.repair-orders.estimate.pdf.download', $repairOrder) }}?t=' + Date.now())"
            >
                Download PDF
            </a>
        </div>

        <article class="bg-white px-4 pb-4 pt-2 ring-1 ring-slate-300 print:p-0 print:ring-0">
            <header class="border-b border-slate-300 px-3 pb-2 pt-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="inline-flex h-14 w-36 items-center justify-start bg-white text-sm font-semibold leading-tight text-slate-700">
                            @if ($snapshot['shop']['logo_url'] ?? null)
                                <img src="{{ $snapshot['shop']['logo_url'] }}" alt="{{ $shopName }} logo" class="max-h-full max-w-full object-contain">
                            @else
                                {{ $shopInitial }}<span class="ml-1 text-[10px] uppercase tracking-wide">Auto</span>
                            @endif
                        </div>
                        <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Repair Estimate</p>
                        <h1 class="text-2xl font-black tracking-tight text-slate-950">RO #{{ $snapshot['repair_order']['repair_order_id'] }} Estimate</h1>
                    </div>

                    <div class="text-left sm:text-right">
                        <h1 class="text-lg font-semibold tracking-tight text-slate-950">{{ $shopName }}</h1>
                        <div class="mt-1 space-y-0.5 text-xs leading-4 text-slate-600">
                            <p>
                                @if ($snapshot['shop']['phone'])
                                    {{ $snapshot['shop']['phone'] }}
                                @endif
                                @if ($snapshot['shop']['email'])
                                    {{ $snapshot['shop']['phone'] ? ' | ' : '' }}{{ $snapshot['shop']['email'] }}
                                @endif
                            </p>
                            @if ($snapshot['shop']['address_line_1'] || $snapshot['shop']['address_line_2'])
                                <p>
                                    {{ $snapshot['shop']['address_line_1'] }}
                                    @if ($snapshot['shop']['address_line_2'])
                                        {{ $snapshot['shop']['address_line_1'] ? ', ' : '' }}{{ $snapshot['shop']['address_line_2'] }}
                                    @endif
                                </p>
                            @endif
                            @if ($shopCityLine)
                                <p>{{ $shopCityLine }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            @include('operations.documents.estimates._operational-identity-band', [
                'snapshot' => $snapshot,
                'repairOrder' => $repairOrder,
                'variant' => 'show',
            ])

            <div class="grid gap-px border-b border-slate-200 bg-slate-200 py-2 text-xs md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Execution Owner</p>
                    <p class="mt-0.5 font-semibold text-slate-950">{{ $staffExecution['technician_name'] }}</p>
                    <p class="mt-0.5 text-slate-600">{{ $staffExecution['posture'] }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Execution Next Step</p>
                    <p class="mt-0.5 font-semibold text-slate-950">{{ $staffExecution['next_action'] }}</p>
                    <p class="mt-0.5 text-slate-600">Execution context is stored with this snapshot.</p>
                </div>
            </div>

            <div class="grid gap-px border-b border-slate-200 bg-slate-200 py-2 text-xs md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Communication status</p>
                    <p class="mt-0.5 font-semibold text-slate-950">{{ $staffCommunicationPosture }}</p>
                    <p class="mt-0.5 text-slate-600">{{ $staffCommunicationNextAction }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Recent Communication</p>
                    @forelse ($staffCommunications->take(2) as $communication)
                        <p class="mt-0.5 font-semibold text-slate-950">{{ $communication['communication_type_label'] }} · {{ $communication['channel_label'] }}</p>
                        <p class="text-slate-600">{{ $communication['summary'] }}</p>
                    @empty
                        <p class="mt-0.5 text-slate-600">No communication events stored in this snapshot.</p>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-px border-b border-slate-200 bg-slate-200 py-2 text-xs md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Authorization Gate</p>
                    <p class="mt-0.5 font-semibold text-slate-950">{{ $nextAction }}</p>
                    <p class="mt-0.5 text-slate-600">
                        @if ($lastApprovalEvent)
                            Last authorization {{ $lastApprovalEvent['approved_at_display'] ?? 'time not recorded' }}
                        @else
                            No authorization event recorded in this snapshot.
                        @endif
                    </p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Authorization History</p>
                    @forelse ($approvalEvents->take(2) as $approvalEvent)
                        <p class="mt-0.5 font-semibold text-slate-950">
                            {{ $approvalEvent['approval_type_label'] }} · {{ $approvalEvent['approved_amount'] }}
                            @if ($approvalEvent['revoked'] ?? false)
                                <span class="text-amber-800">· Revoked</span>
                            @endif
                        </p>
                        <p class="text-slate-600">{{ $approvalEvent['source_label'] }} · {{ $approvalEvent['approved_by'] ?: 'Customer' }}</p>
                    @empty
                        <p class="mt-0.5 text-slate-600">Pending customer response or disposition-only authorization.</p>
                    @endforelse
                </div>
            </div>

            @if ($staffTimeline->isNotEmpty())
                <div class="border-b border-slate-200 bg-white px-3 py-2 text-xs">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Operational Timeline</p>
                    <div class="mt-1.5 grid gap-1.5 md:grid-cols-2">
                        @foreach ($staffTimeline->take(4) as $entry)
                            <div class="border border-slate-200 bg-slate-50 px-2 py-1.5">
                                <p class="font-semibold text-slate-950">{{ $entry['title'] }}</p>
                                <p class="mt-0.5 text-slate-600">{{ $entry['detail'] }}</p>
                                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $entry['actor'] }} · {{ $entry['occurred_at_display'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($snapshot['concerns']) === 0)
                <div class="border-b border-slate-100 py-2.5">
                    <h2 class="text-sm font-semibold text-slate-950">Check In Concern</h2>
                    <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $snapshot['intake']['concern_summary'] }}</p>
                </div>
            @endif

            <div class="mt-2.5 space-y-2">
                @php
                    $concernDisplayEntries = App\Ark\Operations\RepairOrders\RecommendationIntent::displayEntriesForSnapshot($snapshot['concerns']);
                @endphp
                @forelse ($concernDisplayEntries as $entry)
                    @php
                        $concern = $entry['concern'];
                        $customerStates = trim((string) $concern['customer_states']);
                        $duplicateCustomerStates = $customerStates !== ''
                            && in_array(mb_strtolower($customerStates), [
                                mb_strtolower(trim((string) $concern['summary'])),
                                mb_strtolower(trim((string) $snapshot['intake']['concern_summary'])),
                            ], true);
                        $intent = App\Ark\Operations\RepairOrders\RecommendationIntent::fromStored($concern['recommendation_intent'] ?? null);
                    @endphp
                    <section class="ops-review-concern {{ $intent->reviewScopeClass() }}">
                        <x-operations.scope-header
                            class="ops-review-concern-header"
                            :title="$concern['summary']"
                            :total="$concern['subtotal']"
                            :eyebrow="$intent->staffLabel()"
                            :eyebrow-class="$intent->intentLabelClass()"
                        >
                            <x-slot:status>
                                @include('operations.repair-orders.partials.repair-order-concern-disposition-decision', [
                                    'disposition' => App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::from($concern['disposition']),
                                ])
                            </x-slot:status>
                        </x-operations.scope-header>

                        @if (($concern['customer_states'] && ! $duplicateCustomerStates) || $concern['verified_findings'] || $concern['dtcs_summary'] || $concern['recommendation'])
                            <div class="border-b border-slate-100 px-3 py-2 text-xs">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Why / Evidence</p>
                                <div class="mt-1.5 grid gap-x-4 gap-y-2 md:grid-cols-2">
                                    @if ($concern['customer_states'] && ! $duplicateCustomerStates)
                                        <div>
                                            <p class="text-[11px] font-medium text-slate-400">Customer states</p>
                                            <p class="mt-0.5 whitespace-pre-line leading-5 text-slate-600">{{ $concern['customer_states'] }}</p>
                                        </div>
                                    @endif
                                    @if ($concern['verified_findings'] || $concern['dtcs_summary'])
                                        <div>
                                            <p class="text-[11px] font-medium text-slate-400">Verified findings</p>
                                            @if ($concern['verified_findings'])
                                                <p class="mt-0.5 whitespace-pre-line leading-5 text-slate-600">{{ $concern['verified_findings'] }}</p>
                                            @endif
                                            @if ($concern['dtcs_summary'])
                                                <p class="mt-0.5 whitespace-pre-line text-xs leading-4 text-slate-500">Codes: {{ $concern['dtcs_summary'] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($concern['recommendation'])
                                        <div>
                                            <p class="text-[11px] font-medium text-slate-400">Recommendation</p>
                                            <p class="mt-0.5 whitespace-pre-line leading-5 text-slate-700">{{ $concern['recommendation'] }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (count($concern['lines']) > 0)
                            <div class="ops-worksheet-lines">
                                <div class="ops-worksheet-lines-head hidden grid-cols-[minmax(0,1fr)_52px_78px_64px_64px_64px_88px] gap-2 text-[10px] font-bold uppercase tracking-wide text-slate-400 md:grid">
                                    <span aria-hidden="true"></span>
                                    <span class="text-right">Qty</span>
                                    <span class="text-right">Price</span>
                                    <span class="text-right">Subtotal</span>
                                    <span class="text-right">Fees</span>
                                    <span class="text-right">Tax</span>
                                    <span class="text-right">Total</span>
                                </div>
                                @foreach ($concern['lines'] as $line)
                                    @if ($line['type'] === 'note')
                                        <div class="ops-line-row ops-line-row--note text-sm">
                                            <div class="mb-1 flex min-w-0 flex-wrap items-center gap-2">
                                                <span class="ops-line-type ops-line-type--note">{{ $line['type_label'] }}</span>
                                                @include('operations.repair-orders.partials.repair-order-note-visibility-badge', [
                                                    'audience' => [
                                                        'advisor' => (bool) ($line['visible_to_advisor'] ?? true),
                                                        'technician' => (bool) ($line['visible_to_technician'] ?? false),
                                                        'customer' => (bool) ($line['visible_to_customer'] ?? ! ($line['is_private'] ?? false)),
                                                    ],
                                                ])
                                            </div>
                                            <x-operations.note-body :text="$line['description']" class="ops-note-body--estimate-line" />
                                            <div class="ops-line-meta mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <span>{{ ($line['visible_to_customer'] ?? ! ($line['is_private'] ?? false)) ? 'Visible on customer estimate' : 'Hidden from customer PDF' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="ops-line-row grid gap-2 text-sm md:grid-cols-[minmax(0,1fr)_52px_78px_64px_64px_64px_88px] md:items-start">
                                            <div class="min-w-0">
                                                <div class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                                    <span class="ops-line-type">{{ $line['type_label'] }}</span>
                                                    <p class="ops-line-title">{{ $line['description'] }}</p>
                                                </div>
                                                <div class="ops-line-meta mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                    <span>Qty {{ $line['quantity'] }}</span>
                                                    @if ($line['type'] === 'part' && $line['vendor_name'])
                                                        <span class="text-slate-300">·</span>
                                                        <span>{{ $line['vendor_name'] }}</span>
                                                    @endif
                                                    @if ($line['type'] === 'part' && $line['part_number'])
                                                        <span class="text-slate-300">·</span>
                                                        <span>Part # {{ $line['part_number'] }}</span>
                                                    @endif
                                                    @if ($line['type'] === 'part' && ($line['procurement_state_label'] ?? null))
                                                        <span class="text-slate-300">·</span>
                                                        <span>{{ $line['procurement_state_label'] }}</span>
                                                    @endif
                                                    @if ($line['type'] === 'part' && ($line['sourcing_notes'] ?? null))
                                                        <span class="text-slate-300">·</span>
                                                        <span>{{ $line['sourcing_notes'] }}</span>
                                                    @endif
                                                    @if ($line['type'] === 'part' && $line['matrix_suggested_price_cents'] !== null)
                                                        <span class="text-slate-300">·</span>
                                                        <span>{{ $line['pricing_matrix_name'] ?: 'Matrix' }} suggested ${{ number_format($line['matrix_suggested_price_cents'] / 100, 2) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="hidden text-right md:block">
                                                <p class="font-bold tabular-nums text-slate-950">{{ $line['quantity'] }}</p>
                                            </div>
                                            <div class="hidden text-right md:block">
                                                <p class="font-bold tabular-nums text-slate-950">{{ $line['unit_price'] }}</p>
                                            </div>
                                            <div class="hidden text-right md:block">
                                                <p class="font-bold tabular-nums text-slate-950">{{ $line['subtotal'] }}</p>
                                            </div>
                                            <div class="hidden text-right md:block">
                                                <p class="font-semibold tabular-nums text-slate-500">{{ ($line['shop_fee_cents'] ?? 0) > 0 ? $line['shop_fee'] : '—' }}</p>
                                            </div>
                                            <div class="hidden text-right md:block">
                                                <p class="font-semibold tabular-nums text-slate-500">{{ ($line['tax_cents'] ?? 0) > 0 ? $line['tax'] : '—' }}</p>
                                            </div>
                                            <div class="md:text-right">
                                                <p class="font-black tabular-nums text-slate-950">{{ $line['total'] }}</p>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @include('operations.documents.partials._concern-customer-approval', [
                            'snapshot' => $snapshot,
                            'variant' => 'browser',
                            'disposition' => $concern['disposition'] ?? '',
                        ])
                    </section>
                @empty
                    <div class="border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-600">
                        This snapshot has no grouped concerns.
                    </div>
                @endforelse

            </div>

            @include('operations.documents.partials._document-footer', [
                'snapshot' => $snapshot,
                'variant' => 'browser',
            ])
        </article>
    </section>
</x-operations.app>
