@php
    /** @var array{count: int, for_day: int, rows: list<array<string, mixed>>} $bookingRequests */
    $rows = $bookingRequests['rows'] ?? [];
@endphp

@if ($rows !== [])
    <details class="card" data-schedule-booking-requests @if (($bookingRequests['for_day'] ?? 0) > 0 || count($rows) <= 5) open @endif>
        <summary class="card-header d-flex align-items-center gap-2" style="cursor: pointer; list-style: none">
            <h3 class="card-title mb-0">Online requests waiting</h3>
            <span class="badge bg-orange text-orange-fg" data-booking-request-count>{{ $bookingRequests['count'] }}</span>
            @if (($bookingRequests['for_day'] ?? 0) > 0)
                <span class="text-secondary small">{{ $bookingRequests['for_day'] }} for this day</span>
            @endif
            <span class="ms-auto small text-secondary">Not on the schedule until you pick a time</span>
        </summary>
        <div class="list-group list-group-flush">
            @foreach ($rows as $row)
                <div @class(['list-group-item', 'border-start border-3 border-orange' => $row['for_focus_day']]) data-booking-request-row="{{ $row['id'] }}">
                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-lg">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-bold">{{ $row['name'] }}</span>
                                <span class="text-secondary">{{ \App\Ark\Operations\PhoneNumber::display($row['phone']) ?? $row['phone'] }}</span>
                                <span class="badge bg-blue-lt">{{ $row['service'] }}</span>
                                @if ($row['location'])
                                    <span class="badge bg-azure-lt">{{ $row['location'] }}</span>
                                @endif
                                @if ($row['past_due'])
                                    <span class="badge bg-red-lt">Preferred day has passed</span>
                                @endif
                                @if ($row['unchecked'] !== [])
                                    <span class="badge bg-yellow-lt" data-booking-request-unchecked>Not checked online: {{ implode(', ', $row['unchecked']) }}</span>
                                @endif
                            </div>
                            <div class="small mt-1"><span class="fw-medium">Wants:</span> {{ $row['preference'] }} <span class="text-secondary">· received {{ $row['received'] }}</span></div>
                            <div class="small text-secondary mt-1" style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($row['concern'], 280) }}</div>
                            @if ($row['vehicles'] !== [])
                                <div class="small mt-1"><span class="fw-medium">{{ count($row['vehicles']) > 1 ? count($row['vehicles']).' vehicles:' : 'Vehicle:' }}</span> {{ implode('; ', $row['vehicles']) }}</div>
                            @endif
                            @if ($row['address'])
                                <div class="small mt-1"><span class="fw-medium">Address:</span> {{ $row['address'] }}</div>
                            @endif
                            @foreach ($row['answers'] as $answer)
                                <div class="small mt-1"><span class="fw-medium">{{ \App\Ark\Operations\Booking\AppointmentRequest::answerLabel($answer['label']) }}</span> {{ $answer['answer'] }}</div>
                            @endforeach
                        </div>
                        <div class="col-12 col-lg-auto d-flex flex-wrap gap-2 align-items-start">
                            <a href="{{ $row['schedule_url'] }}" class="btn btn-primary btn-sm" data-booking-request-schedule>Pick a time</a>
                            <details class="dropdown" data-booking-request-decline>
                                <summary class="btn btn-sm" style="list-style: none">Decline</summary>
                                <div class="card card-sm position-absolute end-0 mt-1 shadow" style="z-index: 5; min-width: 18rem">
                                    <form method="POST" action="{{ route('operations.appointment-requests.decline', $row['id']) }}" class="card-body">
                                        @csrf
                                        @if (filled($focusDate))
                                            <input type="hidden" name="day" value="{{ $focusDate }}">
                                        @endif
                                        <label class="form-label" for="decline-reason-{{ $row['id'] }}">Reason (staff only)</label>
                                        <input id="decline-reason-{{ $row['id'] }}" name="reason" maxlength="255" class="form-control form-control-sm" placeholder="Fully booked, outside service area…">
                                        <button type="submit" class="btn btn-sm btn-danger mt-2 w-100">Decline request</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </details>
@endif
