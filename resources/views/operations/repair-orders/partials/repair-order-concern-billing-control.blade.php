@php
    use App\Ark\Operations\Settings\ShopSettings;

    $posture = $concern->billing_posture;
    $billingClassKey = $concern->billingClassKey();
    $billingClassLabel = $concern->billingClassLabel();
    $laborRate = $laborRate ?? null;
    $shopSettings = ShopSettings::current();
    $billingDetail = function (array $presentation): string {
        return '$'.$presentation['rate'].'/hr · '.$presentation['matrix_name'].' · '.$presentation['shop_fee_summary'];
    };
    $currentBilling = $shopSettings->billingPostureOptionPresentation($posture, $repairOrder ?? null);
    $canChangeBilling = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value) ?? false;
@endphp

@if ($isTerminal ?? false)
    <div class="d-flex align-items-center gap-1">
        @if ($billingClassKey !== App\Ark\Operations\RepairOrders\ConcernBillingPosture::Default->value)
            <span class="subheader">{{ $billingClassLabel }}</span>
        @endif
        @if ($laborRate)
            <span class="small text-secondary">${{ $laborRate }}/hr</span>
        @endif
        <x-operations.help-tip
            :text="$posture->helpText()"
            label="Billing help for {{ $concern->summary }}"
        />
    </div>
@elseif (! $canChangeBilling)
    <span class="d-inline-flex align-items-center gap-1 small" title="{{ $currentBilling['title'] }}" data-billing-readonly="{{ $billingClassKey }}">
        <x-tabler.icon name="receipt" class="text-secondary" />
        <span class="fw-medium">{{ $billingClassLabel }}</span>
        <span class="visually-hidden">: {{ $billingDetail($currentBilling) }}</span>
    </span>
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
        <input type="hidden" name="billing_posture" value="{{ $billingClassKey }}">
        <div class="dropdown" data-concern-billing-choices>
            <button
                type="button"
                class="btn btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Billing for {{ $concern->summary }}: {{ $billingClassLabel }}, {{ $billingDetail($currentBilling) }}"
                title="{{ $currentBilling['title'] }}"
                data-billing-current="{{ $billingClassKey }}"
            >
                <x-tabler.icon name="receipt" class="text-secondary me-1" />
                <span class="fw-medium">{{ $billingClassLabel }}</span>
            </button>
            <div class="dropdown-menu">
                <h6 class="dropdown-header">Billing</h6>
                @foreach (App\Ark\Operations\RepairOrders\ConcernBillingPosture::advisorSelectableCases() as $option)
                    @php
                        $billingOption = $shopSettings->billingPostureOptionPresentation($option, $repairOrder ?? null);
                        $isCurrent = $option->value === $billingClassKey;
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
                @foreach ($shopSettings->customLaborBillingClasses() as $customClass)
                    @php
                        $base = App\Ark\Operations\RepairOrders\ConcernBillingPosture::fromStored($customClass['base_posture']);
                        $billingOption = $shopSettings->billingPostureOptionPresentation($base, $repairOrder ?? null);
                        $customRate = number_format(
                            app(App\Ark\Operations\Labor\LaborAuthority::class)->policyRateCentsFor(
                                $shopSettings->defaultLaborCategory()['key'],
                                $customClass['key'],
                                $shopSettings,
                            ) / 100,
                            2,
                            '.',
                            '',
                        );
                        $isCurrent = $customClass['key'] === $billingClassKey;
                    @endphp
                    <button
                        type="button"
                        class="dropdown-item"
                        title="{{ $customClass['label'] }} · custom billing class · ${{ $customRate }}/hr"
                        data-billing-option="{{ $customClass['key'] }}"
                        @if ($isCurrent)
                            aria-current="true"
                        @else
                            @click="preserveRepairOrderConcernScroll({{ $concern->id }}); $el.form.elements.billing_posture.value = @js($customClass['key']); $el.form.requestSubmit()"
                        @endif
                    >
                        <span class="d-flex flex-column me-3">
                            <span>{{ $customClass['label'] }}</span>
                            <span class="small text-secondary">${{ $customRate }}/hr · {{ $billingOption['matrix_name'] }} · {{ $billingOption['shop_fee_summary'] }}</span>
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
