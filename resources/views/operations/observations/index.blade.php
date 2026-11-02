<x-operations.app title="Operational Observations" ui="tabler">
    <x-tabler.page
        layout="fluid"
        pretitle="Observation layer"
        title="Operational observations"
        subtitle="Admin debug view - observations derived from timeline events. Not pressure. Not tasks. No authority writes."
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.index')" icon="layout-grid" label="Work" />
            <x-tabler.page-action :href="route('operations.settings.shop.edit')" icon="settings" label="Settings" />
        </x-slot:actions>

        <div class="card" data-operational-observations>
            <div class="card-header">
                <div class="small text-secondary" data-operational-observations-counts>
                    <span class="fw-bold text-reset">{{ $counts['total'] ?? 0 }}</span> observations
                    @if (filled($counts['by_severity'] ?? null))
                        ·
                        @foreach ($counts['by_severity'] as $severity => $count)
                            <span class="text-capitalize">{{ $severity }} {{ $count }}</span>@if (! $loop->last) · @endif
                        @endforeach
                    @endif
                </div>
            </div>

            @if (($rows ?? []) === [])
                <div class="card-body text-secondary">No observations resolved from current open conversations and repair orders.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Observation</th>
                                <th>Severity</th>
                                <th>Source</th>
                                <th>Entity</th>
                                <th>Age</th>
                                <th>Source events</th>
                                <th>Metadata</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="align-top">
                                    <td style="min-width: 16rem">
                                        <div class="fw-bold">{{ $row['headline'] }}</div>
                                        <div class="text-secondary">{{ $row['description'] }}</div>
                                        <div class="subheader mt-1">{{ $row['type_label'] }} · {{ $row['category'] }}</div>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'bg-red-lt' => ($row['severity'] ?? '') === 'high',
                                            'bg-yellow-lt' => ($row['severity'] ?? '') === 'medium',
                                            'bg-secondary-lt' => ! in_array($row['severity'] ?? '', ['high', 'medium'], true),
                                        ])>
                                            {{ $row['severity_label'] }}
                                        </span>
                                    </td>
                                    <td class="font-monospace small">{{ $row['source'] }}</td>
                                    <td>{{ $row['entity'] }}</td>
                                    <td class="text-nowrap text-secondary">{{ $row['age_label'] }}</td>
                                    <td class="font-monospace small text-secondary">
                                        @if (filled($row['source_events'] ?? null))
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($row['source_events'] as $sourceEvent)
                                                    <li>
                                                        {{ $sourceEvent['headline'] ?? '' }}
                                                        @ {{ $sourceEvent['occurred_at_label'] ?? '' }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="font-monospace small">
                                        <pre class="mb-0 text-break" style="max-width: 20rem; white-space: pre-wrap">{{ json_encode($row['metadata'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
