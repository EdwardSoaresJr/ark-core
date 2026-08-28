@php
    $arrivalPosture = $arrivalPosture ?? (
        \App\Ark\Operations\OperationsFeatures::appointmentsEnabled()
            ? app(\App\Ark\Operations\Appointments\ArrivalPostureProjection::class)->forRepairOrder($repairOrder)
            : null
    );
    $inspectionPosture = $inspectionPosture
        ?? app(\App\Ark\Operations\Inspections\InspectionPostureProjection::class)->forRepairOrder($repairOrder);
    $showArrival = (bool) ($arrivalPosture?->present);
    $showInspection = $inspectionPosture !== null;
@endphp

@if ($showArrival || $showInspection)
    <div class="ops-review-toolbar-section ops-review-toolbar-section--visit-signals">
        <div class="ops-review-toolbar-row ops-visit-signals" role="group" aria-label="Appointment and inspection">
            @if ($showArrival)
                @include('operations.repair-orders.partials.repair-order-arrival-posture-inline', [
                    'arrivalPosture' => $arrivalPosture,
                    'variant' => 'toolbar',
                ])
            @endif

            @if ($showInspection)
                @include('operations.repair-orders.partials.repair-order-inspection-posture-inline', [
                    'inspectionPosture' => $inspectionPosture,
                    'variant' => 'toolbar',
                ])
            @endif
        </div>
    </div>
@endif
