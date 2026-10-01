{{-- Email send history for a document. Props: $emailSends --}}
@php
    $emailSends = $emailSends ?? [];
@endphp

@if ($emailSends !== [])
    <div class="rounded-sm border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Email log</p>
            <p class="mt-0.5 text-xs text-slate-500">Who received this file</p>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($emailSends as $send)
                <li class="px-3 py-2.5">
                    <p class="text-sm font-semibold text-slate-950">{{ $send['recipient_email'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">
                        {{ $send['occurred_label'] }}
                        @if ($send['actor_name'] ?? null)
                            <span class="mx-1 text-slate-300">·</span>
                            {{ $send['actor_name'] }}
                        @endif
                    </p>
                    @if ($send['staff_note'] ?? null)
                        <p class="mt-1 text-xs text-slate-500">Note: {{ $send['staff_note'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
