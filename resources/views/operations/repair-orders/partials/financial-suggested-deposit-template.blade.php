@php
    $breakdown = $financial['suggestedDepositBreakdown'] ?? [];
    $partLines = collect($breakdown)->where('line_kind', 'part')->values();
    $laborLines = collect($breakdown)->where('line_kind', 'labor')->values();
@endphp

@if (count($breakdown) > 0)
    <template id="{{ $depositBreakdownTemplateId }}">
        <div
            class="card shadow-lg position-relative"
            style="width: min(96vw, 44rem); max-height: min(88vh, 42rem)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $depositBreakdownTemplateId }}-title"
            data-deposit-breakdown-dialog
            data-repair-order-id="{{ $repairOrder->repair_order_id }}"
        >
            <div class="card-header align-items-start">
                <div style="min-width: 0">
                    <h2 id="{{ $depositBreakdownTemplateId }}-title" class="card-title">Deposit line breakdown</h2>
                    <p class="small text-secondary mb-0 mt-1">Customer sell by line. Uncheck parts to exclude; check labor to add. Tax and shop fees included when they apply.</p>
                </div>
                <button type="button" class="btn-close ms-auto" aria-label="Close" data-ops-deposit-breakdown-close></button>
            </div>

            <div class="card-body p-0 overflow-auto" style="min-height: 0">
                <table class="table table-vcenter card-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="w-1"><span class="visually-hidden">Include</span></th>
                            <th scope="col">Line</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Sell</th>
                            <th scope="col" class="text-end">Tax</th>
                            <th scope="col" class="text-end">Shop fee</th>
                            <th scope="col" class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($partLines->isNotEmpty())
                            <tr class="bg-secondary-lt">
                                <td colspan="7" class="subheader">Parts</td>
                            </tr>
                            @foreach ($partLines as $line)
                                @include('operations.repair-orders.partials.financial-suggested-deposit-template-line', ['line' => $line])
                            @endforeach
                        @endif

                        @if ($laborLines->isNotEmpty())
                            <tr class="bg-secondary-lt">
                                <td colspan="7" class="subheader">Labor</td>
                            </tr>
                            @foreach ($laborLines as $line)
                                @include('operations.repair-orders.partials.financial-suggested-deposit-template-line', ['line' => $line])
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-between">
                <span class="fw-bold">Deposit total</span>
                <span class="fw-bold ark-money" data-deposit-breakdown-total>{{ $financial['suggestedDeposit'] }}</span>
            </div>
        </div>
    </template>
@endif
