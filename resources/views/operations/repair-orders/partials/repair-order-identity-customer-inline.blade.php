@php
    $customer = $repairOrder->customer;
    $serviceLaneLayout = $serviceLaneLayout ?? false;
@endphp

<div class="ops-identity-present" data-identity-present="customer">
    <div @class([
        'flex flex-wrap items-center gap-x-2 gap-y-0.5',
        'mt-0.5' => ! $serviceLaneLayout,
    ])>
        <span class="inline-flex min-w-0 items-center gap-1">
            <button
                type="button"
                @class([
                    'ops-identity-title-link text-left',
                    'ops-service-lane-customer-name' => $serviceLaneLayout,
                ])
                title="Open to edit customer"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'customer-identity', invokeEl: $event.currentTarget } }))"
            >
                {{ $identity['customer']['title'] }}
            </button>
        </span>
        @unless ($serviceLaneLayout)
            <x-operations.help-tip class="ops-help-tip--align-start" :text="$billingClassHelp" label="Billing class">
                <x-slot:trigger>
                    <span class="{{ $billingClassPillClass ?? 'ops-billing-class-pill ops-billing-class-pill--slate' }}">{{ $billingClass ?? ($identity['customer']['type'] ?? ($customer->customer_type ?: 'Retail')) }}</span>
                </x-slot:trigger>
            </x-operations.help-tip>
        @endunless
    </div>

    @if ($serviceLaneLayout)
        @php
            $recognitionParts = [];
            $reachLine = collect($identity['customer']['lines'] ?? [])->firstWhere('label', 'Reach via');
            if (filled($reachLine['value'] ?? null)) {
                $recognitionParts[] = ['key' => 'reach', 'value' => $reachLine['value'], 'muted' => true, 'href' => null];
            }
            $phoneLine = collect($identity['customer']['lines'] ?? [])->firstWhere('label', 'Phone');
            if (filled($phoneLine['value'] ?? null)) {
                $recognitionParts[] = ['key' => 'phone', 'value' => $phoneLine['value'], 'href' => $phoneLine['href'] ?? null];
            }
            $recognitionParts[] = ['key' => 'billing', 'value' => $identity['customer']['type'] ?? ($customer->customer_type ?: 'Retail')];
            $referralLine = collect($identity['customer']['lines'] ?? [])->firstWhere('label', 'Referral');
            if (filled($referralLine['value'] ?? null)) {
                $recognitionParts[] = ['key' => 'referral', 'value' => $referralLine['value'], 'muted' => true, 'href' => null];
            }
        @endphp
        @if (count($recognitionParts) > 0)
            <p class="ops-service-lane-recognition-meta">
                @foreach ($recognitionParts as $index => $part)
                    @if ($index > 0)<span class="ops-service-lane-sep">·</span>@endif
                    @if (! empty($part['href']))
                        <a href="{{ $part['href'] }}" class="text-inherit hover:text-slate-950">{{ $part['value'] }}</a>
                    @else
                        <span @class(['text-slate-400' => ! empty($part['muted'])])>{{ $part['value'] }}</span>
                    @endif
                @endforeach
            </p>
        @endif
    @else
        @if ($customer->identityPressure()->showsChip())
            <div class="mt-1">
                @include('operations.repair-orders.partials.repair-order-customer-identity-pressure', [
                    'customer' => $customer,
                    'showMissingFields' => true,
                ])
            </div>
        @endif

        <dl class="mt-1.5 space-y-0.5">
            @foreach ($identity['customer']['lines'] as $line)
                <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] gap-x-2 text-xs leading-4">
                    <dt class="font-semibold text-slate-500">{{ $line['label'] }}</dt>
                    <dd class="min-w-0 break-words font-semibold text-slate-800">
                        @if (($line['href'] ?? null))
                            <a href="{{ $line['href'] }}" class="text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-slate-950">{{ $line['value'] }}</a>
                        @else
                            <span class="block">{{ $line['value'] }}</span>
                            @if (filled($line['secondary_value'] ?? null))
                                <span class="block">{{ $line['secondary_value'] }}</span>
                            @endif
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @include('operations.repair-orders.partials.repair-order-identity-customer-actions', [
            'scheduleFromRoHref' => $scheduleFromRoHref ?? null,
            'newRoFromExistingHref' => $newRoFromExistingHref ?? null,
        ])
    @endif
</div>
