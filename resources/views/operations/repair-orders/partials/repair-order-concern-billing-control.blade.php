@php
    use App\Ark\Operations\Settings\ShopSettings;

    $posture = $concern->billing_posture;
    $laborRate = $laborRate ?? null;
    $shopSettings = ShopSettings::current();
    $authorViaModal = (bool) ($authorViaModal ?? false);
    $billingLabel = $shopSettings->billingPostureOptionPresentation($posture)['label'] ?? $posture->shortLabel();
@endphp

@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1">
        @if ($posture !== App\Ark\Operations\RepairOrders\ConcernBillingPosture::Default)
            <span class="subheader">{{ $posture->shortLabel() }}</span>
        @endif
        @if ($laborRate)
            <span class="small text-secondary">${{ $laborRate }}/hr</span>
        @endif
        <x-operations.help-tip
            :text="$posture->helpText()"
            label="Billing help for {{ $concern->summary }}"
        />
    </div>
@elseif ($authorViaModal)
    <button
        type="button"
        class="btn btn-sm btn-ghost-secondary"
        title="Billing for this scope"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'concern-billing', context: { concernId: {{ $concern->id }} }, invokeEl: $event.currentTarget } }))"
    >
        {{ $billingLabel }}
    </button>
@else
    <div class="d-flex align-items-center gap-1">
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
            <label class="visually-hidden" for="concern-billing-{{ $concern->id }}">Billing for {{ $concern->summary }}</label>
            <select
                id="concern-billing-{{ $concern->id }}"
                name="billing_posture"
                class="form-select form-select-sm"
                style="width: auto"
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
