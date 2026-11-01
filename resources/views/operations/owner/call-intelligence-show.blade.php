@php
    $isSms = ($row['kind'] ?? 'call') === 'sms';
@endphp

<x-operations.app title="{{ $isSms ? 'SMS' : 'Call' }} · {{ $row['display_phone'] }}" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$row['display_phone'].($row['customer_name'] ? ' · '.$row['customer_name'] : '')"
        pretitle="Owner · Call & SMS intelligence"
    >
        <section class="card mb-3" data-call-intelligence-header>
            <div class="card-body p-2 p-md-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-xl">
                        <div class="small text-secondary">
                            {{ $row['started_at_label'] }}
                            · {{ $row['direction_label'] }}
                            · {{ $row['status_label'] }}
                            @if ($row['media_kind_label'])
                                · {{ $row['media_kind_label'] }}
                            @endif
                            · {{ $row['duration_label'] }}
                            @if ($row['staff_name'])
                                · {{ $row['staff_name'] }}
                            @endif
                            @if ($row['analyzed_at_label'])
                                · Analyzed {{ $row['analyzed_at_label'] }}
                            @endif
                        </div>
                    </div>
                    <div class="col-12 col-xl-auto">
                        <div class="btn-list align-items-center">
                            @if ($row['recording_url'])
                                <audio controls preload="none" class="mw-100" style="height: 2rem; width: 16rem">
                                    <source src="{{ $row['recording_url'] }}" type="audio/mpeg">
                                </audio>
                            @endif
                            @if ($row['customer_url'])
                                <a href="{{ $row['customer_url'] }}" class="btn btn-sm">Customer hub</a>
                            @endif
                            @if ($row['repair_order_url'])
                                <a href="{{ $row['repair_order_url'] }}" class="btn btn-sm">RO #{{ $row['repair_order_number'] }}</a>
                            @endif
                            @if ($row['can_reanalyze'])
                                <form method="POST" action="{{ $row['reanalyze_url'] }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm">Re-analyze</button>
                                </form>
                            @endif
                            @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                            @if (! $isSms)
                                <a
                                    href="{{ \App\Ark\Runtime\Ecosystem\EcosystemArkademyBridge::advisorIncomingCallsUrl() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-sm"
                                >ARKademy · phone floor</a>
                            @endif
                            @if ($row['coaching_pdf_url'])
                                <a href="{{ $row['coaching_pdf_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">
                                    <x-tabler.icon name="printer" />
                                    Print handout
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="alert alert-success mb-3">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        <section class="card mb-3" data-call-intelligence-analysis>
            <div class="card-body p-2 p-md-3">
                @include('operations.owner.partials.call-intelligence-call-details', ['row' => $row])

                @if ($row['summary'])
                    <div class="mt-3">
                        <h4 class="subheader mb-1">Summary</h4>
                        <p class="mb-0">{{ $row['summary'] }}</p>
                    </div>
                @elseif ($row['analysis_error'])
                    <div class="alert alert-danger mt-3 mb-0">{{ $row['analysis_error'] }}</div>
                @elseif ($row['analysis_status'] === 'pending' || $row['analysis_status'] === 'processing')
                    <p class="text-secondary mt-3 mb-0">{{ $row['analysis_status_label'] }} - check back shortly.</p>
                @endif

                @if (! empty($row['topics']))
                    <div class="mt-3">
                        <h4 class="subheader mb-1">Topics</h4>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($row['topics'] as $topic)
                                <span class="badge bg-secondary-lt">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($row['transcript'])
                    <div class="mt-3">
                        <h4 class="subheader mb-1">Transcript</h4>
                        <div class="border rounded p-3 overflow-auto" style="max-height: 32rem; white-space: pre-wrap" data-call-transcript>{{ $row['transcript'] }}</div>
                    </div>
                @else
                    <p class="text-secondary mt-3 mb-0">No transcript available for this {{ $isSms ? 'thread' : 'call' }}.</p>
                @endif
            </div>
        </section>

        @if ($showCoachingDebrief ?? true)
            <div class="mb-3">
                @include('operations.owner.partials.call-intelligence-coaching-debrief', [
                    'row' => $row,
                    'coachingStaffOptions' => $coachingStaffOptions,
                    'defaultCoachingStaffUserId' => $defaultCoachingStaffUserId,
                    'coachingLogs' => $coachingLogs,
                ])
            </div>
        @endif

        <div class="btn-list" data-call-intelligence-links>
            <a href="{{ $listUrl }}" class="btn">
                <x-tabler.icon name="arrow-left" />
                All communications
            </a>
            <a href="{{ route('operations.owner.day-review') }}" class="btn">Day Review</a>
        </div>
    </x-tabler.page>
</x-operations.app>
