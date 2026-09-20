{{--
  Line presentation only. Authoring is modal-hosted via editing_line → workspace-modal/edit-line.
  Compatibility: editing_line query + editLine() continuity still open that modal — do not restore
  inline edit branches here (see docs/operations/ro-builder-workspace-modal-compatibility-debt.md).
--}}
@php
    $partStateOptions = $line->availableProcurementTransitions();
    $workGroup = $workGroup ?? null;
    $workGroupLaborCount = $workGroupLaborCount ?? null;
    $suppressLaborDescription = $workGroup !== null
        && $workGroupLaborCount !== null
        && \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::shouldSuppressWorksheetDescription(
            $line,
            $workGroup->title,
            (int) $workGroupLaborCount,
        );
    $worksheetLineTitle = $suppressLaborDescription
        ? \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::compactLaborSummary($line)
        : null;
@endphp

@include('operations.repair-orders._line-composition', [
    'line' => $line,
    'displayDescription' => $worksheetLineTitle,
    'suppressLaborDescription' => $suppressLaborDescription,
    'repairOrder' => $repairOrder,
    'totals' => $totals,
    'taxLabel' => $taxLabel,
    'isTerminal' => $isTerminal,
    'partStateOptions' => $partStateOptions,
    'showActions' => true,
    'estimateVersion' => $estimateVersion,
])
