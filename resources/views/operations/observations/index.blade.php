<x-operations.app title="Operational Observations">
    <section class="ops-index space-y-2">
        <div class="ops-board-shell">
            <div class="ops-page-toolbar">
                <div>
                    <p class="ops-eyebrow">Observation layer</p>
                    <h1 class="text-lg font-black text-slate-950">Operational observations</h1>
                    <p class="mt-1 max-w-3xl text-sm text-slate-600">
                        Admin debug view — observations derived from timeline events. Not pressure. Not tasks. No authority writes.
                    </p>
                </div>
                <div class="ops-page-toolbar-actions">
                    <a href="{{ route('operations.index') }}" class="ops-page-link">Work</a>
                    <a href="{{ route('operations.settings.shop.edit') }}" class="ops-page-link">Settings</a>
                </div>
            </div>

            <div class="border-t border-slate-100 px-3 py-2 text-xs text-slate-600">
                <span class="font-bold text-slate-900">{{ $counts['total'] ?? 0 }}</span> observations
                @if (filled($counts['by_severity'] ?? null))
                    ·
                    @foreach ($counts['by_severity'] as $severity => $count)
                        <span class="capitalize">{{ $severity }} {{ $count }}</span>@if (! $loop->last) · @endif
                    @endforeach
                @endif
            </div>
        </div>

        <div class="ops-board-shell overflow-x-auto">
            @if (($rows ?? []) === [])
                <p class="px-3 py-8 text-sm text-slate-600">No observations resolved from current open conversations and repair orders.</p>
            @else
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-[0.06em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Observation</th>
                            <th class="px-3 py-2">Severity</th>
                            <th class="px-3 py-2">Source</th>
                            <th class="px-3 py-2">Entity</th>
                            <th class="px-3 py-2">Age</th>
                            <th class="px-3 py-2">Source events</th>
                            <th class="px-3 py-2">Metadata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr class="align-top">
                                <td class="px-3 py-2">
                                    <p class="font-bold text-slate-950">{{ $row['headline'] }}</p>
                                    <p class="mt-0.5 text-slate-600">{{ $row['description'] }}</p>
                                    <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-400">
                                        {{ $row['type_label'] }} · {{ $row['category'] }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'inline-flex rounded-sm px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.04em]',
                                        'bg-rose-100 text-rose-800' => ($row['severity'] ?? '') === 'high',
                                        'bg-amber-100 text-amber-900' => ($row['severity'] ?? '') === 'medium',
                                        'bg-slate-100 text-slate-700' => ($row['severity'] ?? '') === 'low',
                                    ])>
                                        {{ $row['severity_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-[11px] text-slate-700">{{ $row['source'] }}</td>
                                <td class="px-3 py-2 text-slate-800">{{ $row['entity'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-slate-500">{{ $row['age_label'] }}</td>
                                <td class="px-3 py-2 font-mono text-[10px] text-slate-500">
                                    @if (filled($row['source_events'] ?? null))
                                        <ul class="space-y-1">
                                            @foreach ($row['source_events'] as $sourceEvent)
                                                <li>
                                                    {{ $sourceEvent['headline'] ?? '' }}
                                                    @ {{ $sourceEvent['occurred_at_label'] ?? '' }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-mono text-[10px] text-slate-500">
                                    <pre class="max-w-xs whitespace-pre-wrap break-all">{{ json_encode($row['metadata'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</x-operations.app>
