@props(['step' => 1, 'title' => ''])

@php
    $steps = [
        1 => 'Your Shop',
        2 => 'Workspace',
        3 => 'Your Account',
        4 => 'Ready',
    ];
@endphp

<div class="mx-auto max-w-2xl px-5 sm:px-8 py-14 sm:py-20">
    <div class="mb-12">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">
                Free trial · {{ $step }} of 4
            </p>
            <span class="inline-flex items-center rounded-full border border-[var(--cloud-cerulean)]/30 bg-[var(--cloud-mist)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--cloud-cerulean-deep)]">
                Free trial
            </span>
        </div>
        <div class="mt-5 flex gap-2.5">
            @foreach ($steps as $n => $label)
                <div class="flex-1">
                    <div @class([
                        'h-1.5 rounded-full transition-colors',
                        'bg-[var(--cloud-cerulean)]' => $n <= $step,
                        'bg-[var(--cloud-line)]' => $n > $step,
                    ])></div>
                    <p @class([
                        'mt-2.5 text-xs sm:text-sm font-medium',
                        'text-[var(--cloud-ink)]' => $n <= $step,
                        'text-[var(--cloud-muted)]' => $n > $step,
                    ])>{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="cloud-stage rounded-3xl border border-[var(--cloud-line)] bg-white p-9 sm:p-12 shadow-[0_40px_100px_-48px_rgba(11,18,32,0.5)]">
        @if ($title !== '')
            <h1 class="cloud-display text-3xl sm:text-4xl font-semibold leading-tight">{{ $title }}</h1>
        @endif
        {{ $slot }}
    </div>
</div>
