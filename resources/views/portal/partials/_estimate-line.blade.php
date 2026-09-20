@if (($line['type'] ?? '') === 'note')
    <div class="px-4 py-2.5 lg:px-5 lg:py-3">
        @include('operations.documents.partials._customer-line-description', [
            'line' => $line,
            'grouped' => $grouped ?? false,
            'workGroupTitle' => $workGroupTitle ?? '',
            'variant' => 'portal',
        ])
    </div>
@else
    <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-3 px-4 py-2.5 lg:gap-4 lg:px-5 lg:py-3">
        @include('operations.documents.partials._customer-line-description', [
            'line' => $line,
            'grouped' => $grouped ?? false,
            'workGroupTitle' => $workGroupTitle ?? '',
            'variant' => 'portal',
        ])
        <p class="text-xs text-slate-500">{{ $line['quantity'] ?? '1' }}</p>
        <p class="text-right font-semibold tabular-nums text-slate-800">{{ $line['total'] ?? $line['line_total'] ?? $line['sell'] ?? '' }}</p>
    </div>
@endif
