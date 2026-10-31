@php
    /** @var \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition $disposition */
    $disposition = $disposition ?? (is_array($concern)
        ? \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::from($concern['disposition'])
        : $concern->disposition);
    $concernRegionId = is_object($concern) ? $concern->id : null;
    $ui = $ui ?? 'legacy';
@endphp

@if ($concernRegionId !== null)
<div id="concern-authorization-{{ $concernRegionId }}" data-continuity-region="concern-authorization">
@endif
    @if ($disposition->showsInScopeHeader())
        @if ($ui === 'tabler')
            <span class="badge bg-{{ $disposition->tablerColor() }}-lt" data-concern-decision="{{ $disposition->value }}">
                @if ($disposition->decisionMark() !== '')
                    <span aria-hidden="true">{{ $disposition->decisionMark() }}</span>
                @endif
                {{ $disposition->scopeHeaderLabel() }}
            </span>
        @else
            <span class="ops-scope-header-decision {{ $disposition->scopeHeaderDecisionClass() }}">
                @if ($disposition->decisionMark() !== '')
                    <span class="ops-scope-header-decision-mark">{{ $disposition->decisionMark() }}</span>
                @endif
                {{ $disposition->scopeHeaderLabel() }}
            </span>
        @endif
    @endif
@if ($concernRegionId !== null)
</div>
@endif
