@php
    $captureAttempts = $financial['paymentCaptureAttempts'] ?? [];
    $readiness = $financial['paymentCaptureReadiness'] ?? [
        'ready' => false,
        'supports_terminal' => false,
        'supports_keyed' => false,
        'devices' => [],
        'public_config' => null,
        'status' => 'unknown',
        'message' => null,
        'provider' => null,
    ];
    $defaultContext = ($financial['canRecordPayment'] ?? false) ? 'payment' : 'deposit';
    $defaultAmount = $defaultContext === 'payment'
        ? ($financial['settlementBalanceDueDecimal'] ?? number_format(($financial['settlementBalanceDueCents'] ?? 0) / 100, 2, '.', ''))
        : ($financial['remainingCollectableDepositDecimal'] ?? '');
    $devices = $readiness['devices'] ?? [];
    $publicConfig = $readiness['public_config'] ?? null;
    $defaultMethod = ($readiness['supports_terminal'] ?? false) && $devices !== [] ? 'terminal' : 'keyed';
    $defaultDevice = $devices[0]['device_ref'] ?? '';
@endphp

@if ($financial['canTakePaymentCapture'] ?? false)
    <div
        class="border border-slate-200 bg-white p-3"
        x-data="arkPaymentCapture({
            publicConfig: @js($publicConfig),
            defaultMethod: @js($defaultMethod),
            defaultDevice: @js($defaultDevice),
            cardContainerId: 'ark-payment-capture-card-{{ $repairOrder->id }}'
        })"
    >
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Take payment</p>
        <p class="mt-1 text-xs leading-4 text-slate-600">
            Card capture via Platform. Record Payment remains available for cash, check, or external card.
        </p>

        @if (! ($readiness['ready'] ?? false))
            <p class="mt-2 text-xs font-semibold text-amber-900">
                {{ $readiness['message'] ?? 'Card capture is not ready. Connect payments in Platform or use Record Payment.' }}
            </p>
        @else
            <form
                method="POST"
                action="{{ route('operations.repair-orders.payment-capture.store', $repairOrder) }}"
                class="mt-3 space-y-2"
                data-refresh-scope="rail"
                @submit.prevent="prepareAndSubmit($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="context_kind" value="{{ $defaultContext }}">
                <input type="hidden" name="capture_method" x-bind:value="method">
                <input type="hidden" name="device_ref" x-bind:value="method === 'terminal' ? deviceRef : ''">
                <input type="hidden" name="source_token" x-bind:value="sourceToken">
                @if (app()->environment('local', 'testing') && ($readiness['provider'] ?? null) !== 'square')
                    <input type="hidden" name="stub_scenario" value="{{ request('stub_scenario', 'immediate_success') }}">
                @endif

                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Amount</span>
                    <input
                        type="number"
                        name="amount"
                        step="0.01"
                        min="0.01"
                        value="{{ old('amount', $defaultAmount) }}"
                        class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-2 text-sm font-semibold tabular-nums"
                        required
                    >
                </label>

                <div class="flex gap-2 text-xs">
                    @if ($readiness['supports_terminal'] ?? false)
                        <button
                            type="button"
                            class="rounded-sm border px-2 py-1 font-semibold"
                            :class="method === 'terminal' ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-300 text-slate-600'"
                            @click="method = 'terminal'"
                        >Terminal</button>
                    @endif
                    @if ($readiness['supports_keyed'] ?? false)
                        <button
                            type="button"
                            class="rounded-sm border px-2 py-1 font-semibold"
                            :class="method === 'keyed' ? 'border-sky-700 bg-sky-50 text-sky-900' : 'border-slate-300 text-slate-600'"
                            @click="openKeyed()"
                        >Card entry</button>
                    @endif
                </div>

                <template x-if="method === 'terminal'">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Terminal</span>
                        <select
                            class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-2 text-sm"
                            x-model="deviceRef"
                        >
                            @foreach ($devices as $device)
                                @if ($device['ready'] ?? true)
                                    <option value="{{ $device['device_ref'] }}">{{ $device['label'] }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                </template>

                <template x-if="method === 'keyed' && publicConfig">
                    <div>
                        <div :id="cardContainerId" class="min-h-[48px] rounded-sm border border-slate-300 bg-white p-2"></div>
                        <p class="mt-1 text-[11px] text-slate-500" x-show="cardError" x-text="cardError"></p>
                    </div>
                </template>

                <button
                    type="submit"
                    class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                    :disabled="busy"
                >
                    <span x-text="busy ? 'Processing…' : 'Take payment'"></span>
                </button>
            </form>
        @endif

        @error('capture')
            <p class="mt-2 text-xs font-semibold text-red-700">{{ $message }}</p>
        @enderror
    </div>
@endif

@if (! empty($captureAttempts))
    <div class="border border-slate-100 bg-slate-50/80 p-3">
        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Recent captures</p>
        <div class="mt-1 divide-y divide-slate-200">
            @foreach ($captureAttempts as $attempt)
                <div class="flex items-start justify-between gap-2 py-1.5 text-xs">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900">
                            {{ $attempt['amount'] }}
                            · {{ $attempt['statusLabel'] }}
                        </p>
                        <p class="text-slate-500">{{ $attempt['context'] }} · {{ $attempt['method'] }}</p>
                        @if ($attempt['needsReconciliation'])
                            <p class="mt-0.5 font-semibold text-amber-900">Do not re-charge this amount until resolved.</p>
                        @endif
                    </div>
                    @if ($attempt['isOpen'])
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.payment-capture.refresh', [$repairOrder, $attempt['id']]) }}"
                            data-refresh-scope="rail"
                            @submit.prevent="submitWorksheetForm($event)"
                        >
                            @csrf
                            <button type="submit" class="shrink-0 text-[11px] font-bold text-sky-800 hover:underline">
                                Check status
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
