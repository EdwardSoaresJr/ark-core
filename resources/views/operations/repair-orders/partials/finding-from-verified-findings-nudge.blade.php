@php
    use App\Ark\Operations\Inspections\InspectionCaptureLinks;

    $canRecordFinding = InspectionCaptureLinks::canRecord(auth()->user(), $repairOrder);
    $ui = $ui ?? 'legacy';
@endphp

@if ($canRecordFinding)
    @if ($ui === 'tabler')
        <p class="small text-secondary mt-2 mb-0">
            Faster with evidence?
            <a href="{{ InspectionCaptureLinks::captureUrl($repairOrder, $concern->id) }}">Open Inspection</a>
            - photo and measurement beat prose alone.
        </p>
    @else
        <p class="mt-1 text-[11px] leading-4 text-slate-500">
            Faster with evidence?
            <a
                href="{{ InspectionCaptureLinks::captureUrl($repairOrder, $concern->id) }}"
                class="font-semibold text-sky-700 underline underline-offset-2 hover:text-sky-900"
            >Open Inspection</a>
            - photo and measurement beat prose alone.
        </p>
    @endif
@endif
