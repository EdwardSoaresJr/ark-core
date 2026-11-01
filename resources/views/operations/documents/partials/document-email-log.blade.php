{{-- Email send history for a document. Props: $emailSends --}}
@php
    $emailSends = $emailSends ?? [];
@endphp

@if ($emailSends !== [])
    <div class="card d-print-none" data-document-email-log>
        <div class="card-header">
            <div>
                <h3 class="card-title">Email log</h3>
                <div class="card-subtitle">Who received this file</div>
            </div>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($emailSends as $send)
                <li class="list-group-item">
                    <div class="fw-bold text-break">{{ $send['recipient_email'] }}</div>
                    <div class="text-secondary small">
                        {{ $send['occurred_label'] }}
                        @if ($send['actor_name'] ?? null)
                            · {{ $send['actor_name'] }}
                        @endif
                    </div>
                    @if ($send['staff_note'] ?? null)
                        <div class="text-secondary small mt-1">Note: {{ $send['staff_note'] }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
