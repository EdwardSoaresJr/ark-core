@props([
    'followUps' => [],
    'tasks' => [],
    'redirect' => null,
])

@php
    $redirectUrl = $redirect ?? url()->current();
    $hasWork = $followUps !== [] || $tasks !== [];
@endphp

@if ($hasWork)
    <section data-advisor-work-context>
        @if ($followUps !== [])
            <div class="card-body pb-0 pt-2">
                <div class="subheader text-purple">Open Follow-Ups</div>
            </div>
            <div class="list-group list-group-flush" data-advisor-work-follow-ups>
                @foreach ($followUps as $row)
                    <div class="list-group-item py-2">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="flex-fill" style="min-width: 0">
                                @if (filled($row['customer_name'] ?? null))
                                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                                        <span class="fw-bold text-purple">{{ $row['customer_name'] }}</span>
                                        @if (filled($row['dollars_at_risk_label'] ?? null))
                                            <span class="fw-bold ark-money">{{ $row['dollars_at_risk_label'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                <div class="fw-medium">{{ $row['notes'] }}</div>
                                <div class="small text-secondary">
                                    <span class="fw-medium">Assigned to {{ $row['assigned_to_label'] }}</span>
                                    @if (filled($row['schedule_label'] ?? null))
                                        <span>·</span>
                                        <span class="fw-medium text-purple">{{ $row['schedule_label'] }}</span>
                                        @if (filled($row['due_time_label'] ?? null))
                                            <span>at {{ $row['due_time_label'] }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ $row['complete_url'] }}" class="flex-shrink-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect" value="{{ $redirectUrl }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Done</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($tasks !== [])
            <div @class(['card-body pb-0 pt-2', 'border-top' => $followUps !== []])>
                <div class="subheader text-green">Open Tasks</div>
            </div>
            <div class="list-group list-group-flush" data-advisor-work-tasks>
                @foreach ($tasks as $row)
                    <div class="list-group-item py-2">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="flex-fill" style="min-width: 0">
                                <div class="subheader text-green">Shop task</div>
                                <div class="fw-medium">{{ $row['notes'] }}</div>
                                <div class="small text-secondary">
                                    <span class="fw-medium">Assigned to {{ $row['assigned_to_label'] }}</span>
                                    @if (filled($row['schedule_label'] ?? null))
                                        <span>·</span>
                                        <span class="fw-medium text-green">{{ $row['schedule_label'] }}</span>
                                        @if (filled($row['due_time_label'] ?? null))
                                            <span>at {{ $row['due_time_label'] }}</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ $row['complete_url'] }}" class="flex-shrink-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="redirect" value="{{ $redirectUrl }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Done</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endif
