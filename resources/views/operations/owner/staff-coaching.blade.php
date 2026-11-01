<x-operations.app :title="$staffMember->name . ' · Coaching'" ui="tabler">
    <x-tabler.page layout="fluid" :title="$staffMember->name" pretitle="Owner · Team coaching">
        <section class="card mb-3" data-staff-coaching-profile>
            <div class="card-body p-2 p-md-3">
                <div class="text-secondary">
                    {{ $staffMember->email }}
                    @if ($staffMember->display_phone)
                        · {{ $staffMember->display_phone }}
                    @endif
                    · {{ $coachingLogCount }} {{ Str::plural('coaching log', $coachingLogCount) }}
                </div>
                @if ($staffMember->staffRoleLabels())
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach ($staffMember->staffRoleLabels() as $roleLabel)
                            <span class="badge bg-secondary-lt">{{ $roleLabel }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="card mb-3" data-staff-coaching-logs>
            <div class="card-header">
                <h3 class="card-title">Coaching debriefs</h3>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($coachingLogs as $log)
                    <article class="list-group-item">
                        <div class="small text-secondary fw-medium">
                            {{ $log['discussed_at_label'] }}
                            @if ($log['recorded_by_name'])
                                · logged by {{ $log['recorded_by_name'] }}
                            @endif
                        </div>
                        @if (! empty($log['call_url']))
                            <div class="small mt-1">
                                @if ($log['call_started_at_label'])
                                    Call {{ $log['call_started_at_label'] }}
                                @else
                                    Linked call
                                @endif
                                @if ($log['customer_name'])
                                    · {{ $log['customer_name'] }}
                                @endif
                                · <a href="{{ $log['call_url'] }}">Open call</a>
                            </div>
                            @if ($log['call_summary'])
                                <div class="small text-secondary mt-1">{{ $log['call_summary'] }}</div>
                            @endif
                        @endif
                        <p class="mt-2 mb-0" style="white-space: pre-wrap">{{ $log['notes'] }}</p>
                    </article>
                @empty
                    <div class="list-group-item text-secondary">No coaching debriefs logged for {{ $staffMember->name }} yet. Log one from a call on Call intelligence.</div>
                @endforelse
            </div>
        </section>

        <div class="btn-list" data-staff-coaching-links>
            <a href="{{ route('operations.owner.call-intelligence') }}" class="btn">Call intelligence</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'staff']) }}" class="btn">Team settings</a>
            <a href="{{ route('operations.owner.day-review') }}" class="btn">Day Review</a>
        </div>
    </x-tabler.page>
</x-operations.app>
