@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition $disposition */
    $disposition = $disposition ?? (is_array($concern)
        ? \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::from($concern['disposition'])
        : $concern->disposition);
    $concernRegionId = is_object($concern) ? $concern->id : null;
@endphp

@if ($concernRegionId !== null)
<div id="concern-authorization-{{ $concernRegionId }}" data-continuity-region="concern-authorization">
@endif
    @if ($disposition->showsInScopeHeader())
        <span class="ops-scope-header-decision {{ $disposition->scopeHeaderDecisionClass() }}">
            @if ($disposition->decisionMark() !== '')
                <span class="ops-scope-header-decision-mark">{{ $disposition->decisionMark() }}</span>
            @endif
            {{ $disposition->scopeHeaderLabel() }}
        </span>
    @endif
@if ($concernRegionId !== null)
</div>
@endif
