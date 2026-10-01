@php
    $breakdown = $financial['suggestedDepositBreakdown'] ?? [];
    $partLines = collect($breakdown)->where('line_kind', 'part')->values();
    $laborLines = collect($breakdown)->where('line_kind', 'labor')->values();
@endphp

@if (count($breakdown) > 0)
    <template id="{{ $depositBreakdownTemplateId }}">
        <div
            class="ops-deposit-breakdown-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $depositBreakdownTemplateId }}-title"
            data-repair-order-id="{{ $repairOrder->repair_order_id }}"
        >
            <div class="ops-deposit-breakdown-modal__header">
                <div class="min-w-0">
                    <p id="{{ $depositBreakdownTemplateId }}-title" class="ops-deposit-breakdown-modal__title">Deposit line breakdown</p>
                    <p class="ops-deposit-breakdown-modal__intro">Customer sell by line. Uncheck parts to exclude; check labor to add. Tax and shop fees included when they apply.</p>
                </div>
                <button type="button" class="ops-deposit-breakdown-modal__close" data-ops-deposit-breakdown-close>
                    Close
                </button>
            </div>

            <div class="ops-deposit-breakdown-modal__body">
                <table class="ops-deposit-breakdown-modal__table">
                    <thead>
                        <tr>
                            <th scope="col" class="ops-deposit-breakdown-modal__include-col">
                                <span class="sr-only">Include</span>
                            </th>
                            <th scope="col">Line</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-right">Sell</th>
                            <th scope="col" class="text-right">Tax</th>
                            <th scope="col" class="text-right">Shop fee</th>
                            <th scope="col" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($partLines->isNotEmpty())
                            <tr class="ops-deposit-breakdown-modal__section">
                                <td colspan="7">Parts</td>
                            </tr>
                            @foreach ($partLines as $line)
                                @include('operations.repair-orders.partials.financial-suggested-deposit-template-line', ['line' => $line])
                            @endforeach
                        @endif

                        @if ($laborLines->isNotEmpty())
                            <tr class="ops-deposit-breakdown-modal__section">
                                <td colspan="7">Labor</td>
                            </tr>
                            @foreach ($laborLines as $line)
                                @include('operations.repair-orders.partials.financial-suggested-deposit-template-line', ['line' => $line])
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="ops-deposit-breakdown-modal__footer">
                <p>Deposit total</p>
                <p data-deposit-breakdown-total>{{ $financial['suggestedDeposit'] }}</p>
            </div>
        </div>
    </template>
@endif
