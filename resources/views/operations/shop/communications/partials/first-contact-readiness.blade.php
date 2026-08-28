@php
    /** @var array{checks: list<array{label: string, passed: bool}>, ready: bool, action_label: string, action_url: ?string} $firstContact */
    $passMark = static fn (bool $passed): string => $passed ? '✓' : '—';
    $passClass = static fn (bool $passed): string => $passed ? 'text-emerald-700' : 'text-rose-700';
@endphp

<section id="first-contact" class="space-y-3 rounded-sm border border-slate-200 bg-white p-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">First Contact</p>
            <p class="mt-1 text-xs leading-5 text-slate-600">
                Operator path from new device to Connected (bench gates G1–G7 for admins).
            </p>
        </div>
        @if ($firstContact['action_url'])
            <a
                href="{{ $firstContact['action_url'] }}"
                class="inline-flex min-h-8 items-center rounded-sm bg-slate-950 px-3 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800"
            >
                {{ $firstContact['action_label'] }}
            </a>
        @else
            <span class="inline-flex min-h-8 items-center rounded-sm border border-amber-200 bg-amber-50 px-3 text-xs font-bold uppercase tracking-wide text-amber-900">
                {{ $firstContact['action_label'] }}
            </span>
        @endif
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 text-left text-[10px] font-bold uppercase tracking-wide text-slate-400">
                <th class="pb-2 pr-3 font-bold">Check</th>
                <th class="pb-2 text-right font-bold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($firstContact['checks'] as $check)
                <tr>
                    <td class="py-2 pr-3 text-slate-800">{{ $check['label'] }}</td>
                    <td @class(['py-2 text-right font-black', $passClass($check['passed'])])>
                        {{ $passMark($check['passed']) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
