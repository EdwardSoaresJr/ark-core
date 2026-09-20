@php
    use App\Ark\Operations\Documents\CustomerRepairActionIncludes;

    $workGroups = collect($concern['work_groups'] ?? [])->filter(fn (array $group): bool => count($group['lines'] ?? []) > 0);
    $ungroupedLines = collect($concern['lines'] ?? [])
        ->filter(fn (array $line): bool => blank($line['repair_order_work_group_id'] ?? null));
    $hasGroupedWork = $workGroups->isNotEmpty();
    $linesToRender = $hasGroupedWork ? $ungroupedLines : collect($concern['lines'] ?? [])->filter(fn ($line): bool => is_array($line));
@endphp

@if ($hasGroupedWork || $linesToRender->isNotEmpty())
    <div class="divide-y divide-slate-100 text-sm">
        @foreach ($workGroups as $workGroup)
            @php
                $groupHeading = CustomerRepairActionIncludes::groupHeading((string) ($workGroup['title'] ?? ''));
            @endphp
            <div class="border-b border-slate-100 bg-slate-50/60 px-4 py-2">
                <p class="text-xs font-semibold text-slate-600">{{ $groupHeading }}</p>
            </div>
            @foreach ($workGroup['lines'] ?? [] as $line)
                @if (is_array($line))
                    @include('portal.partials._estimate-line', [
                        'line' => $line,
                        'grouped' => true,
                        'workGroupTitle' => $workGroup['title'],
                    ])
                @endif
            @endforeach
        @endforeach

        @foreach ($linesToRender as $line)
            @include('portal.partials._estimate-line', ['line' => $line])
        @endforeach
    </div>
@endif
