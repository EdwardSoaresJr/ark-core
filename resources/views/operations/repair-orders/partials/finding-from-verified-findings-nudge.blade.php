@php
    use App\Ark\Operations\Inspections\InspectionCaptureLinks;

    $canRecordFinding = InspectionCaptureLinks::canRecord(auth()->user(), $repairOrder);
@endphp

@if ($canRecordFinding)
    <p class="mt-1 text-[11px] leading-4 text-slate-500">
        Faster with evidence?
        <a
            href="{{ InspectionCaptureLinks::captureUrl($repairOrder, $concern->id) }}"
            class="font-semibold text-sky-700 underline underline-offset-2 hover:text-sky-900"
        >Open Inspection</a>
        — photo and measurement beat prose alone.
    </p>
@endif
