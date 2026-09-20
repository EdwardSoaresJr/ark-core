@php
    use App\Ark\Operations\Settings\ShopSettings;

    $posture = $concern->billing_posture;
    $laborRate = $laborRate ?? null;
    $shopSettings = ShopSettings::current();
    $authorViaModal = (bool) ($authorViaModal ?? false);
    $billingLabel = $shopSettings->billingPostureOptionPresentation($posture)['label'] ?? $posture->shortLabel();
@endphp

@if ($isTerminal ?? false)
    <div class="flex items-center gap-1">
        @if ($posture !== App\Ark\Operations\RepairOrders\ConcernBillingPosture::Default)
            <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $posture->shortLabel() }}</span>
        @endif
        @if ($laborRate)
            <span class="ops-concern-billing-rate">${{ $laborRate }}/hr</span>
        @endif
        <x-operations.help-tip
            :text="$posture->helpText()"
            label="Billing help for {{ $concern->summary }}"
        />
    </div>
@elseif ($authorViaModal)
    <button
        type="button"
        class="ops-builder-present-chip ops-builder-present-chip--quiet"
        title="Billing for this scope"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-billing', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $billingLabel }}
    </button>
@else
    <div class="flex items-center gap-1">
        <form
            method="POST"
            action="{{ route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]) }}"
            data-refresh-scope="worksheet"
            data-continuity-focus="#concern-{{ $concern->id }} select[name='billing_posture']"
            @submit.prevent="submitWorksheetForm($event)"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <label class="sr-only" for="concern-billing-{{ $concern->id }}">Billing for {{ $concern->summary }}</label>
            <select
                id="concern-billing-{{ $concern->id }}"
                name="billing_posture"
                class="ops-disposition-select ops-billing-posture-select"
                onchange="preserveRepairOrderConcernScroll({{ $concern->id }}); this.form.requestSubmit()"
            >
                @foreach (App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorSelectableCases() as $option)
                    @php
                        $billingOption = $shopSettings->billingPostureOptionPresentation($option);
                    @endphp
                    <option
                        value="{{ $option->value }}"
                        title="{{ $billingOption['title'] }}"
                        @selected($posture === $option)
                    >{{ $billingOption['label'] }}</option>
                @endforeach
            </select>
        </form>
        <x-operations.help-tip
            :items="App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorHelpOverviewItems()"
            title="Scope billing"
            label="Scope billing help"
            class="ops-help-tip--above"
        />
    </div>
@endif
