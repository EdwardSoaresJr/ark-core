@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition $disposition */
    $disposition = $disposition ?? $concern->disposition;
@endphp

@if ($disposition->showsInScopeHeader())
    <span class="ops-scope-header-decision {{ $disposition->scopeHeaderDecisionClass() }}">
        @if ($disposition->decisionMark() !== '')
            <span class="ops-scope-header-decision-mark">{{ $disposition->decisionMark() }}</span>
        @endif
        {{ $disposition->scopeHeaderLabel() }}
    </span>
@endif
