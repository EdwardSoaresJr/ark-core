@php
    $draft = $builder['draft'];
    $lines = static fn (array $items): string => implode("\n", $items);
@endphp

<x-operations.app title="Growth · Content Builder">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Content execution</p>
                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                    <span class="rounded-sm bg-slate-900 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">{{ $builder['opportunity']['action'] }}</span>
                    <h1 class="text-lg font-black text-slate-950">{{ $draft['title'] }}</h1>
                </div>
                <p class="mt-1 text-xs text-slate-500">ARK pre-fills the page from search evidence — review, tweak if needed, publish.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 px-3 py-2 text-[11px]">
                <span class="font-bold text-slate-600">{{ $builder['opportunity']['status'] }}</span>
                @if ($builder['opportunity']['is_validated'])
                    <span class="rounded-sm bg-emerald-600 px-1.5 py-0.5 font-black uppercase tracking-wide text-white">✓ Validated</span>
                @endif
                <span class="text-slate-500">Acceptance {{ $builder['acceptance_progress']['satisfied'] }}/{{ $builder['acceptance_progress']['required'] }}</span>
                @if ($builder['can_publish'])
                    <span class="font-bold text-emerald-700">Ready to publish</span>
                @endif
                @if ($builder['preview_url'])
                    <a href="{{ $builder['preview_url'] }}" target="_blank" rel="noopener" class="font-bold text-sky-800 hover:text-sky-950">Preview path →</a>
                @endif
                <a href="{{ route('growth.opportunities.index') }}" class="ml-auto font-bold text-slate-600 hover:text-slate-900">← Queue</a>
            </div>
        </div>

        @if (session('status'))
            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-950">{{ session('status') }}</div>
        @endif

        @if ($builder['can_publish'])
            <div class="border border-emerald-300 bg-emerald-50 px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-900">Ready to publish</p>
                <p class="mt-1 text-sm font-semibold text-emerald-950">{{ $draft['title'] }}</p>
                <p class="mt-1 text-xs text-emerald-900">{{ $draft['summary'] }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('growth.opportunities.status', $builder['opportunity']['id']) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="published">
                        <button type="submit" class="rounded-sm border border-emerald-800 bg-emerald-800 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-900">
                            Publish page →
                        </button>
                    </form>
                    @if ($builder['preview_url'])
                        <a href="{{ $builder['preview_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-sm border border-emerald-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-emerald-900 hover:border-emerald-400">
                            Preview path
                        </a>
                    @endif
                </div>
            </div>
        @endif

        @if (! empty($builder['report_card']))
            <div class="border border-slate-300 bg-white px-3 py-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Report card</p>
                @if ($builder['report_card']['awaiting_data'] ?? false)
                    <p class="mt-1 text-xs text-slate-600">Measuring since {{ $builder['report_card']['measuring_since'] ?? 'publish' }}. Results appear after the measurement window.</p>
                @else
                    @php $delta = $builder['report_card']['delta'] ?? []; @endphp
                    <ul class="mt-2 space-y-1 text-xs text-slate-800">
                        <li>+{{ number_format((int) ($delta['impressions'] ?? 0)) }} impressions</li>
                        <li>+{{ number_format((int) ($delta['clicks'] ?? 0)) }} clicks</li>
                        <li>+{{ number_format((int) ($delta['leads'] ?? 0)) }} leads</li>
                        <li>+{{ number_format((int) ($delta['repair_orders'] ?? 0)) }} repair orders</li>
                        <li>+${{ number_format(((int) ($delta['revenue_cents'] ?? 0)) / 100, 0) }} attributed revenue</li>
                    </ul>
                    @if ($builder['report_card']['roi_validated'] ?? false)
                        <p class="mt-2 text-[11px] font-black uppercase tracking-wide text-emerald-700">ROI · Validated</p>
                    @endif
                @endif
            </div>
        @endif

        <div class="grid gap-3 lg:grid-cols-[1fr_18rem]">
            <form method="POST" action="{{ route('growth.opportunities.content', $builder['opportunity']['id']) }}" class="space-y-3">
                @csrf
                @method('PATCH')

                <details class="border border-slate-300 bg-white" @if (! $builder['can_publish']) open @endif>
                    <summary class="cursor-pointer border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                        Edit page content
                    </summary>
                    <div class="space-y-3 px-3 py-3">
                        @foreach ($builder['sections'] as $section)
                            @php
                                $key = $section['key'];
                                $value = $draft[$key] ?? '';
                                $isComplete = ! in_array($key, $builder['incomplete_required'], true);
                            @endphp
                            <label class="block">
                                <div class="mb-1 flex items-baseline gap-2">
                                    <span @class(['text-[11px] font-black uppercase tracking-wide', 'text-emerald-700' => $isComplete, 'text-slate-500' => ! $isComplete])>{{ $isComplete ? '✓' : '·' }}</span>
                                    <span class="text-xs font-bold text-slate-900">{{ $section['label'] }}</span>
                                    @if ($section['required'])
                                        <span class="text-[10px] text-slate-400">required</span>
                                    @endif
                                </div>
                                @if ($section['hint'])
                                    <p class="mb-1 text-[10px] text-slate-500">{{ $section['hint'] }}</p>
                                @endif

                                @if ($section['type'] === 'textarea')
                                    <textarea name="{{ $key }}" rows="3" class="w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm">{{ $value }}</textarea>
                                @elseif ($section['type'] === 'lines')
                                    <textarea name="{{ $key }}" rows="4" class="w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm">{{ $lines(is_array($value) ? $value : []) }}</textarea>
                                @elseif ($section['type'] === 'faq')
                                    <div class="space-y-2">
                                        @foreach ((is_array($value) ? $value : []) as $index => $pair)
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <input type="text" name="faq[{{ $index }}][question]" value="{{ $pair['question'] ?? '' }}" placeholder="Question" class="rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                                                <input type="text" name="faq[{{ $index }}][answer]" value="{{ $pair['answer'] ?? '' }}" placeholder="Answer" class="rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                                            </div>
                                        @endforeach
                                        @for ($i = count(is_array($value) ? $value : []); $i < count(is_array($value) ? $value : []) + 2; $i++)
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <input type="text" name="faq[{{ $i }}][question]" placeholder="Question" class="rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                                                <input type="text" name="faq[{{ $i }}][answer]" placeholder="Answer" class="rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                                            </div>
                                        @endfor
                                    </div>
                                @else
                                    <input type="text" name="{{ $key }}" value="{{ $value }}" class="w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm">
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <div class="border-t border-slate-200 px-3 py-2">
                        <button type="submit" class="rounded-sm border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">Save changes</button>
                    </div>
                </details>
            </form>

            <aside class="space-y-3">
                <div class="border border-slate-300 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Publish checklist</p>
                    </div>
                    <ul class="divide-y divide-slate-100 px-3 py-1">
                        @foreach ($builder['acceptance_criteria'] as $criterion)
                            @if (($criterion['gate'] ?? 'publish') !== 'publish')
                                @continue
                            @endif
                            <li class="py-2">
                                @if ($criterion['kind'] === 'manual')
                                    <form method="POST" action="{{ route('growth.opportunities.content', $builder['opportunity']['id']) }}" class="flex items-start gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="toggle_criterion" value="1">
                                        <input type="hidden" name="criterion_key" value="{{ $criterion['key'] }}">
                                        <input type="checkbox" name="satisfied" value="1" class="mt-0.5 rounded border-slate-300" @checked($criterion['satisfied']) onchange="this.form.submit()">
                                        <span class="text-[11px] text-slate-800">{{ $criterion['label'] }}</span>
                                    </form>
                                @else
                                    <div class="flex items-start gap-2 text-[11px]">
                                        <span @class(['font-bold', 'text-emerald-700' => $criterion['satisfied'], 'text-slate-400' => ! $criterion['satisfied']])>{{ $criterion['satisfied'] ? '✓' : '·' }}</span>
                                        <span class="text-slate-800">{{ $criterion['label'] }}</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="border border-slate-300 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">After publish</p>
                    </div>
                    <ul class="divide-y divide-slate-100 px-3 py-1">
                        @foreach ($builder['acceptance_criteria'] as $criterion)
                            @if (($criterion['gate'] ?? 'publish') !== 'measuring')
                                @continue
                            @endif
                            <li class="py-2">
                                @if ($criterion['kind'] === 'manual')
                                    <form method="POST" action="{{ route('growth.opportunities.content', $builder['opportunity']['id']) }}" class="flex items-start gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="toggle_criterion" value="1">
                                        <input type="hidden" name="criterion_key" value="{{ $criterion['key'] }}">
                                        <input type="checkbox" name="satisfied" value="1" class="mt-0.5 rounded border-slate-300" @checked($criterion['satisfied']) onchange="this.form.submit()">
                                        <span class="text-[11px] text-slate-800">{{ $criterion['label'] }}</span>
                                    </form>
                                @else
                                    <div class="flex items-start gap-2 text-[11px]">
                                        <span @class(['font-bold', 'text-emerald-700' => $criterion['satisfied'], 'text-slate-400' => ! $criterion['satisfied']])>{{ $criterion['satisfied'] ? '✓' : '·' }}</span>
                                        <span class="text-slate-800">{{ $criterion['label'] }}</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="border border-slate-300 bg-white px-3 py-3 text-[11px] text-slate-600">
                    <p class="font-bold uppercase tracking-wide text-slate-500">Schema · Preview</p>
                    <p class="mt-1">Path: <span class="font-mono text-slate-900">{{ $builder['preview_path'] ?? '—' }}</span></p>
                    <p class="mt-1">FAQPage + BreadcrumbList emitted by <code class="rounded bg-slate-100 px-1">SeoEngine</code> on publish.</p>
                </div>

                @if ($builder['transitions'] !== [])
                    <form method="POST" action="{{ route('growth.opportunities.status', $builder['opportunity']['id']) }}" class="border border-slate-300 bg-white px-3 py-3">
                        @csrf
                        @method('PATCH')
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</label>
                        <select name="status" class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-xs font-bold" onchange="this.form.submit()">
                            <option value="{{ $builder['opportunity']['status_value'] }}" selected>{{ $builder['opportunity']['status'] }}</option>
                            @foreach ($builder['transitions'] as $transition)
                                <option value="{{ $transition }}">{{ ucfirst($transition) }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </aside>
        </div>
    </section>
</x-operations.app>
