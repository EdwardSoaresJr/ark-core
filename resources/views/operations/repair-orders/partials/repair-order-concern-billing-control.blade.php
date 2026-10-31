@php
    use App\Ark\Operations\Settings\ShopSettings;

    $posture = $concern->billing_posture;
    $laborRate = $laborRate ?? null;
    $shopSettings = ShopSettings::current();
    $billingDetail = function (array $presentation): string {
        return '$'.$presentation['rate'].'/hr · '.$presentation['matrix_name'].' · '.$presentation['shop_fee_summary'];
    };
    $currentBilling = $shopSettings->billingPostureOptionPresentation($posture);
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
@else
    <form
        method="POST"
        action="{{ route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]) }}"
        data-refresh-scope="worksheet"
        data-continuity-focus="#concern-{{ $concern->id }} [data-concern-billing-choices] .dropdown-toggle"
        @submit.prevent="submitWorksheetForm($event)"
    >
        @csrf
        @method('PATCH')
        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
        <input type="hidden" name="billing_posture" value="{{ $posture->value }}">
        <div class="dropdown" data-concern-billing-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Billing for {{ $concern->summary }}: {{ $posture->label() }}, {{ $billingDetail($currentBilling) }}"
                title="{{ $currentBilling['title'] }}"
                data-billing-current="{{ $posture->value }}"
            >
                <x-tabler.icon name="receipt" class="text-secondary me-1" />
                <span class="fw-medium">{{ $posture->label() }}</span>
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Billing</h6>
                @foreach (App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorSelectableCases() as $option)
                    @php
                        $billingOption = $shopSettings->billingPostureOptionPresentation($option);
                        $isCurrent = $option === $posture;
                    @endphp
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $billingOption['title'] }}"
                        data-billing-option="{{ $option->value }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.billing_posture.value = @js($option->value); $el.form.requestSubmit()"
                        @endif
                    >
                        <span class="d-flex flex-column me-3">
                            <span>{{ $option->label() }}</span>
                            <span class="small text-secondary">{{ $billingDetail($billingOption) }}</span>
                        </span>
                        @if ($isCurrent)
                            <x-tabler.icon name="check" class="ms-auto text-primary" />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </form>
@endif
